<?php

if (PHP_SAPI != 'cli') {
	http_response_code(403);
	exit;
}

require_once dirname(__DIR__).'/classes/voicemail_message_export.php';

if (!class_exists('ZipArchive')) {
	throw new RuntimeException('Run this test with PHP CLI and the zip extension.');
}

function check(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$fixture = sys_get_temp_dir().'/fusionpbx-voicemail-export-test-'.bin2hex(random_bytes(8));
mkdir($fixture, 0700);
mkdir($fixture.'/audio', 0700);
mkdir($fixture.'/audio/1000', 0700);
$process = null;

function queued_export(string $name, array $files): array {
	global $fixture;
	$directory = $fixture.'/jobs/'.$name;
	$export = new voicemail_message_export($directory);
	$state = [
		'id' => bin2hex(random_bytes(16)), 'scope' => '1000', 'mailboxes' => ['mailbox'],
		'created' => time(), 'stage' => 'queued', 'total' => count($files),
		'processed' => 0, 'added' => 0, 'skipped' => 0
	];
	file_put_contents($directory.'/state.json', json_encode($state));
	file_put_contents($directory.'/manifest.json', json_encode(['root' => realpath($fixture.'/audio'), 'files' => $files]));
	return [$export, $directory, $state];
}

try {
	$files = [];
	$pcm = str_repeat("\0", 262144);
	$wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16);
	$wav .= 'data'.pack('V', strlen($pcm)).$pcm;
	for ($i = 0; $i < 205; $i++) {
		$name = '1000/msg_'.sprintf('%08d-0000-4000-8000-000000000000', $i).'.wav';
		file_put_contents($fixture.'/audio/'.$name, $wav);
		$files[] = ['name' => $name];
	}
	$files[] = ['name' => '1000/missing.wav'];
	file_put_contents($fixture.'/audio/1000/mp3-only.mp3', 'not converted');
	$files[] = ['name' => '1000/mp3-only.wav'];
	file_put_contents($fixture.'/outside.wav', 'must not be exported');
	$files[] = ['name' => '../outside.wav'];

	[$export, $directory, $initial] = queued_export('success', $files);
	$command = [
		PHP_BINARY, '-n', '-d', 'extension_dir='.ini_get('extension_dir'), '-d', 'extension=zip',
		dirname(__DIR__).'/jobs/voicemail_message_export.php', $directory
	];
	$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $fixture.'/stdout', 'w'], 2 => ['file', $fixture.'/stderr', 'w']], $pipes);
	check(is_resource($process), 'Worker must launch.');
	fclose($pipes[0]);
	$observed = [];
	$deadline = microtime(true) + 30;
	do {
		$state = $export->status();
		$observed[$state['stage']] = true;
		check($state['processed'] <= $state['total'], 'Progress must not exceed the snapshot total.');
		check($state['processed'] == $state['added'] + $state['skipped'], 'Progress must use real counts.');
		if (in_array($state['stage'], ['ready', 'failed'], true)) {
			break;
		}
		usleep(1000);
	} while (microtime(true) < $deadline);
	check($state['stage'] == 'ready', 'Worker must finish: '.file_get_contents($fixture.'/stderr').file_get_contents($fixture.'/stdout'));
	check(isset($observed['finalizing']), 'Finalization must be published before the ZIP is ready.');
	check($state['total'] == 208 && $state['processed'] == 208, 'All pages and more than 100 files must be processed.');
	check($state['added'] == 205 && $state['skipped'] == 3, 'Missing, non-WAV and out-of-root files must be counted.');
	proc_close($process);
	$process = null;
	$zip = new ZipArchive;
	check($zip->open($directory.'/archive.zip') === true, 'Ready ZIP must open.');
	check($zip->numFiles == 205, 'Exactly the eligible WAV files must be exported.');
	check($zip->getFromName($files[0]['name']) === $wav, 'Exported WAV bytes must be unchanged.');
	check($zip->statName($files[0]['name'])['comp_method'] == ZipArchive::CM_STORE, 'Audio must not require compression.');
	check($zip->locateName('../outside.wav') === false, 'Out-of-root data must never be exported.');
	$zip->close();
	check((new voicemail_message_export($directory))->status()['stage'] == 'ready', 'A page reload must retain the ready export.');
	$download = proc_open([
		PHP_BINARY, '-r',
		'require $argv[1]; (new voicemail_message_export($argv[2]))->download($argv[3]);',
		dirname(__DIR__).'/classes/voicemail_message_export.php', $directory, $state['id']
	], [0 => ['pipe', 'r'], 1 => ['file', $fixture.'/download.zip', 'w'], 2 => ['file', $fixture.'/download-errors', 'w']], $download_pipes);
	check(is_resource($download), 'Download process must launch.');
	fclose($download_pipes[0]);
	check(proc_close($download) === 0, 'Download must complete.');
	check(hash_file('sha256', $fixture.'/download.zip') === hash_file('sha256', $directory.'/archive.zip'), 'Downloaded ZIP must be byte-for-byte identical.');
	try {
		$export->download(str_repeat('0', 32));
		throw new LogicException('A stale download ID must not be accepted.');
	}
	catch (RuntimeException $e) {
		check(str_contains($e->getMessage(), 'not ready'), 'Stale ID must be rejected explicitly.');
	}

	$state['created'] = time() - voicemail_message_export::lifetime;
	file_put_contents($directory.'/state.json', json_encode($state));
	$lock = fopen($directory.'/worker.lock', 'c');
	flock($lock, LOCK_EX);
	try {
		$export->download($state['id']);
		throw new LogicException('An active worker must prevent a download without waiting.');
	}
	catch (RuntimeException $e) {
		check(str_contains($e->getMessage(), 'not ready'), 'An active worker must reject stale download requests.');
	}
	flock($lock, LOCK_SH);
	$export->expire();
	check(is_file($directory.'/archive.zip'), 'Cleanup must not interrupt a download.');
	flock($lock, LOCK_UN);
	fclose($lock);
	$export->expire();
	check(!is_file($directory.'/archive.zip') && $export->status() === null, 'Expired archives and manifests must be removed.');
	check(!is_file($directory.'/manifest.json'), 'Expired source metadata must be removed.');

	[$failed, $failed_directory] = queued_export('no-wavs', [['name' => '1000/not-present.wav']]);
	$failed->run();
	check($failed->status()['stage'] == 'failed', 'No WAVs must be an explicit failure, not an empty success.');
	[$stale, $stale_directory, $stale_state] = queued_export('stale', []);
	$stale_state['created'] -= 31;
	file_put_contents($stale_directory.'/state.json', json_encode($stale_state));
	check($stale->status()['stage'] == 'failed', 'A worker that never starts must be reported.');
	[$interrupted, $interrupted_directory, $interrupted_state] = queued_export('interrupted', []);
	$interrupted_state['stage'] = 'finalizing';
	file_put_contents($interrupted_directory.'/state.json', json_encode($interrupted_state));
	check($interrupted->status()['stage'] == 'failed', 'An interrupted finalization must be reported.');

	if (PHP_OS_FAMILY != 'Windows') {
		symlink($fixture.'/outside.wav', $fixture.'/audio/1000/link.wav');
		[$symlinked] = queued_export('symlink', [['name' => '1000/link.wav']]);
		$symlinked->run();
		check($symlinked->status()['skipped'] == 1, 'Out-of-storage symlinks must be skipped.');
	}
	echo "PASS: real worker, 205 WAVs, progress/finalizing, ZIP contents, skipped files, expiry, locking and failure states.\n";
}
finally {
	if (is_resource($process)) {
		proc_terminate($process);
		proc_close($process);
	}
	$entries = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($entries as $entry) {
		if ($entry->isDir() && !$entry->isLink()) {
			rmdir($entry->getPathname());
		}
		else {
			unlink($entry->getPathname());
		}
	}
	rmdir($fixture);
}
