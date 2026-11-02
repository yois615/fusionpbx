<?php

class voicemail_message_export {

	const lifetime = 86400;
	private $directory;

	public function __construct(string $directory) {
		$this->directory = $directory;
		foreach ([dirname($directory), $directory] as $path) {
			if (is_link($path) || (!is_dir($path) && !mkdir($path, 0700))) {
				throw new RuntimeException('Unable to create private voicemail export directory.');
			}
			if (PHP_OS_FAMILY != 'Windows' && (fileperms($path) & 0077) !== 0) {
				throw new RuntimeException('Voicemail export directory must be private (0700).');
			}
		}
	}

	public static function root(): string {
		return sys_get_temp_dir().'/fusionpbx-voicemail-exports';
	}

	private function write(string $name, array $data): void {
		$lock = $this->metadata_lock(LOCK_EX);
		try {
			$json = json_encode($data, JSON_THROW_ON_ERROR);
			if (file_put_contents($this->directory.'/'.$name.'.new', $json) !== strlen($json)
				|| !rename($this->directory.'/'.$name.'.new', $this->directory.'/'.$name)) {
				throw new RuntimeException('Unable to write voicemail export state.');
			}
		}
		finally {
			fclose($lock);
		}
	}

	private function read(string $name): ?array {
		$lock = $this->metadata_lock(LOCK_SH);
		try {
			$path = $this->directory.'/'.$name;
			if (!is_file($path)) {
				return null;
			}
			return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		}
		finally {
			fclose($lock);
		}
	}

	private function metadata_lock(int $operation) {
		$lock = fopen($this->directory.'/metadata.lock', 'ce');
		if ($lock === false || !flock($lock, $operation)) {
			throw new RuntimeException('Unable to lock voicemail export metadata.');
		}
		return $lock;
	}

	private function lock() {
		// The CLI worker must not inherit the launching request's lock.
		$lock = fopen($this->directory.'/worker.lock', 'ce');
		if ($lock === false) {
			throw new RuntimeException('Unable to open voicemail export lock.');
		}
		return $lock;
	}

	public function status(): ?array {
		$state = $this->read('state.json');
		if ($state === null) {
			return null;
		}
		if (in_array($state['stage'], ['queued', 'preparing', 'finalizing'], true)) {
			$lock = $this->lock();
			if (flock($lock, LOCK_EX | LOCK_NB)) {
				$state = $this->read('state.json');
				// A queued worker has a short grace period to acquire its lock.
				if ($state && (in_array($state['stage'], ['preparing', 'finalizing'], true)
					|| ($state['stage'] == 'queued' && time() - $state['created'] > 30))) {
					$state['stage'] = 'failed';
					error_log('Voicemail export worker stopped or failed to start: '.$state['id']);
					$this->write('state.json', $state);
				}
				flock($lock, LOCK_UN);
			}
			fclose($lock);
		}
		return $state;
	}

	public function start(array $files, array $mailboxes, string $scope, string $root): array {
		if (!class_exists('ZipArchive') || !function_exists('exec') || PHP_OS_FAMILY == 'Windows') {
			throw new RuntimeException('Voicemail export requires PHP ZipArchive, exec and a Unix PHP CLI.');
		}
		$php = PHP_BINDIR.'/php';
		if (!is_executable($php)) {
			throw new RuntimeException('PHP CLI was not found at '.$php);
		}
		$lock = $this->lock();
		if (!flock($lock, LOCK_EX | LOCK_NB)) {
			fclose($lock);
			throw new RuntimeException('A voicemail export is already running.');
		}
		try {
			$previous = $this->read('state.json');
			if ($previous && $previous['stage'] == 'queued' && time() - $previous['created'] <= 30) {
				throw new RuntimeException('A voicemail export is already queued.');
			}
			$state = [
				'id' => bin2hex(random_bytes(16)),
				'scope' => $scope,
				'mailboxes' => $mailboxes,
				'created' => time(),
				'stage' => 'queued',
				'total' => count($files),
				'processed' => 0,
				'added' => 0,
				'skipped' => 0
			];
			foreach (['archive.zip', 'worker.log'] as $name) {
				if (is_file($this->directory.'/'.$name) && !unlink($this->directory.'/'.$name)) {
					throw new RuntimeException('Unable to remove previous voicemail export.');
				}
			}
			$this->write('manifest.json', ['root' => $root, 'files' => $files]);
			$this->write('state.json', $state);
			$worker = dirname(__DIR__).'/jobs/voicemail_message_export.php';
			$command = escapeshellarg($php).' '.escapeshellarg($worker).' '.escapeshellarg($this->directory);
			exec($command.' > '.escapeshellarg($this->directory.'/worker.log').' 2>&1 & echo $!', $output, $result);
			if ($result !== 0 || empty($output[0]) || !ctype_digit(trim($output[0]))) {
				$state['stage'] = 'failed';
				$this->write('state.json', $state);
				throw new RuntimeException('Unable to launch voicemail export worker.');
			}
			return $state;
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function run(): void {
		$lock = $this->lock();
		if (!flock($lock, LOCK_EX)) {
			throw new RuntimeException('Unable to lock voicemail export.');
		}
		$state = $this->read('state.json');
		if (!$state || $state['stage'] != 'queued') {
			fclose($lock);
			return;
		}
		$complete = false;
		register_shutdown_function(function () use (&$complete, &$state) {
			if (!$complete) {
				$state['stage'] = 'failed';
				error_log('Voicemail export did not complete: '.$state['id']);
				$this->write('state.json', $state);
			}
		});
		try {
			$manifest = $this->read('manifest.json');
			$zip = new ZipArchive;
			if ($zip->open($this->directory.'/archive.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException('Unable to create voicemail ZIP.');
			}
			$state['stage'] = 'preparing';
			$this->write('state.json', $state);
			$updated = time();
			foreach ($manifest['files'] as $file) {
				$path = realpath($manifest['root'].'/'.$file['name']);
				if ($path === false || !str_starts_with($path, $manifest['root'].DIRECTORY_SEPARATOR)
					|| !is_file($path) || !is_readable($path)) {
					$state['skipped']++;
				}
				else {
					if (!$zip->addFile($path, $file['name'])
						|| !$zip->setCompressionName($file['name'], ZipArchive::CM_STORE)) {
						throw new RuntimeException('Unable to add a voicemail recording to ZIP.');
					}
					$state['added']++;
				}
				$state['processed']++;
				if ($state['processed'] % 100 == 0 || time() != $updated) {
					$this->write('state.json', $state);
					$updated = time();
				}
			}
			if ($state['added'] == 0) {
				throw new RuntimeException('No readable WAV recordings were found.');
			}
			// addFile defers reading the recordings until close; do not report ready yet.
			$state['stage'] = 'finalizing';
			$this->write('state.json', $state);
			if (!$zip->close()) {
				throw new RuntimeException('Unable to finalize voicemail ZIP.');
			}
			$state['stage'] = 'ready';
			$this->write('state.json', $state);
			$complete = true;
		}
		catch (Throwable $e) {
			error_log('Voicemail export '.$state['id'].': '.$e->getMessage());
			// Dispose of libzip's pending work before releasing the worker lock.
			unset($zip);
			$state['stage'] = 'failed';
			$this->write('state.json', $state);
			$complete = true;
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function download(string $id): void {
		$lock = $this->lock();
		if (!flock($lock, LOCK_SH | LOCK_NB)) {
			fclose($lock);
			throw new RuntimeException('Voicemail export is not ready.');
		}
		try {
			$state = $this->read('state.json');
			if (!$state || !hash_equals($state['id'], $id) || $state['stage'] != 'ready'
				|| time() - $state['created'] >= self::lifetime) {
				throw new RuntimeException('Voicemail export is not ready or has expired.');
			}
			$path = $this->directory.'/archive.zip';
			$stream = fopen($path, 'rb');
			if ($stream === false) {
				throw new RuntimeException('Unable to open voicemail export.');
			}
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="voicemails-'.date('Y-m-d', $state['created']).'.zip"');
			header('Content-Length: '.filesize($path));
			header('Cache-Control: no-store');
			header('X-Content-Type-Options: nosniff');
			while (ob_get_level()) {
				ob_end_clean();
			}
			try {
				if (fpassthru($stream) === false) {
					throw new RuntimeException('Unable to stream voicemail export.');
				}
			}
			finally {
				fclose($stream);
			}
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function expire(): void {
		$lock = $this->lock();
		if (!flock($lock, LOCK_EX | LOCK_NB)) {
			fclose($lock);
			return;
		}
		try {
			$state = $this->read('state.json');
			if ($state && time() - $state['created'] >= self::lifetime) {
				$metadata_lock = $this->metadata_lock(LOCK_EX);
				try {
					foreach (['archive.zip', 'manifest.json', 'state.json', 'state.json.new', 'manifest.json.new', 'worker.log'] as $name) {
						if (is_file($this->directory.'/'.$name) && !unlink($this->directory.'/'.$name)) {
							throw new RuntimeException('Unable to expire voicemail export.');
						}
					}
				}
				finally {
					fclose($metadata_lock);
				}
			}
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
}
