<?php

if (PHP_SAPI != 'cli') {
	http_response_code(403);
	exit;
}

require_once dirname(__DIR__).'/classes/voicemail_message_export.php';

umask(0077);
set_time_limit(0);

try {
	if (($argv[1] ?? '') == '--cleanup') {
		foreach (glob(voicemail_message_export::root().'/*', GLOB_ONLYDIR) as $directory) {
			if (preg_match('/^[a-f0-9]{64}$/', basename($directory))) {
				(new voicemail_message_export($directory))->expire();
			}
		}
	}
	elseif (!empty($argv[1]) && is_dir($argv[1])) {
		(new voicemail_message_export($argv[1]))->run();
	}
	else {
		throw new RuntimeException('Usage: php voicemail_message_export.php DIRECTORY | --cleanup');
	}
}
catch (Throwable $e) {
	error_log('Voicemail export worker: '.$e->getMessage());
	exit(1);
}
