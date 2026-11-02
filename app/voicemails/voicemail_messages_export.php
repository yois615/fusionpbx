<?php

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/voicemail_message_export.php';

$language = new text;
$text = $language->get();
$settings = new settings(['domain_uuid' => $_SESSION['domain_uuid'], 'user_uuid' => $_SESSION['user_uuid']]);
$action = $_GET['export_action'] ?? '';

function voicemail_export_response(array $data, int $status = 200): void {
	http_response_code($status);
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	echo json_encode($data, JSON_THROW_ON_ERROR);
	exit;
}

if (!permission_exists('voicemail_message_view') || $settings->get('voicemail', 'storage_type') == 'base64') {
	voicemail_export_response(['error' => $text['message-export_access_denied']], 403);
}
if (!in_array($action, ['start', 'status', 'download'], true)) {
	voicemail_export_response(['error' => $text['message-export_invalid_request']], 400);
}
if ($action == 'start') {
	if ($_SERVER['REQUEST_METHOD'] != 'POST') {
		voicemail_export_response(['error' => $text['message-export_invalid_request']], 405);
	}
	$token = new token;
	if (!$token->validate(PROJECT_PATH.'/app/voicemails/voicemail_messages.php')) {
		voicemail_export_response(['error' => $text['message-invalid_token']], 403);
	}
}

$scope = $_GET['id'] ?? '';
if (!is_string($scope) || ($scope !== '' && !is_uuid($scope) && !ctype_digit($scope))) {
	voicemail_export_response(['error' => $text['message-export_invalid_request']], 400);
}
$vm = new voicemail;
$vm->domain_uuid = $_SESSION['domain_uuid'];
if (is_uuid($scope)) {
	$vm->voicemail_uuid = $scope;
}
elseif ($scope !== '') {
	$vm->voicemail_id = $scope;
}
$mailboxes = $vm->voicemails();
if ($mailboxes === false) {
	voicemail_export_response(['error' => $text['message-export_failed']], 500);
}
$mailbox_uuids = array_column($mailboxes, 'voicemail_uuid');
$owner = hash('sha256', $_SESSION['domain_uuid'].':'.$_SESSION['user_uuid']);
$domain_name = $_SESSION['domain_name'];
$domain_uuid = $_SESSION['domain_uuid'];

// Neither the worker nor the download should hold the user's session lock.
session_write_close();

try {
	$export = new voicemail_message_export(voicemail_message_export::root().'/'.$owner);
	$export->expire();
	$state = $export->status();
	if ($action == 'start') {
		if ($state && in_array($state['stage'], ['queued', 'preparing', 'finalizing'], true)) {
			if ($state['scope'] !== $scope || array_diff($state['mailboxes'], $mailbox_uuids)) {
				voicemail_export_response(['error' => $text['message-export_busy']], 409);
			}
		}
		else {
			if (!$mailbox_uuids) {
				voicemail_export_response(['error' => $text['message-messages_not_found']], 404);
			}
			// Retrieve only metadata, never the message_base64 column or audio contents.
			$parameters = ['domain_uuid' => $domain_uuid];
			$placeholders = [];
			foreach ($mailbox_uuids as $i => $uuid) {
				$placeholders[] = ':mailbox_'.$i;
				$parameters['mailbox_'.$i] = $uuid;
			}
			$sql = "select m.voicemail_message_uuid, v.voicemail_id from v_voicemail_messages m ";
			$sql .= "join v_voicemails v on v.voicemail_uuid = m.voicemail_uuid and v.domain_uuid = m.domain_uuid ";
			$sql .= "where m.domain_uuid = :domain_uuid and m.voicemail_uuid in (".implode(',', $placeholders).") ";
			$sql .= "order by v.voicemail_id, m.created_epoch, m.voicemail_message_uuid";
			$messages = $database->select($sql, $parameters, 'all');
			if ($messages === false) {
				throw new RuntimeException('Unable to retrieve voicemail export metadata.');
			}
			$files = [];
			foreach ($messages as $message) {
				if (!ctype_digit((string) $message['voicemail_id']) || !is_uuid($message['voicemail_message_uuid'])) {
					throw new RuntimeException('Invalid voicemail export metadata.');
				}
				$files[] = ['name' => $message['voicemail_id'].'/msg_'.$message['voicemail_message_uuid'].'.wav'];
			}
			if (!$files) {
				voicemail_export_response(['error' => $text['message-messages_not_found']], 404);
			}
			$root = realpath($settings->get('switch', 'voicemail', '/var/lib/freeswitch/storage/voicemail').'/default/'.$domain_name);
			if ($root === false) {
				throw new RuntimeException('Voicemail storage directory was not found.');
			}
			$state = $export->start($files, $mailbox_uuids, $scope, $root);
		}
	}
	elseif ($state && ($state['scope'] !== $scope || array_diff($state['mailboxes'], $mailbox_uuids))) {
		if ($action == 'download') {
			voicemail_export_response(['error' => $text['message-export_access_denied']], 403);
		}
		voicemail_export_response(['state' => null]);
	}
	if ($action == 'download') {
		$id = $_GET['export_id'] ?? '';
		if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
			voicemail_export_response(['error' => $text['message-export_invalid_request']], 400);
		}
		$export->download($id);
		exit;
	}
	if ($state) {
		unset($state['mailboxes'], $state['scope']);
	}
	voicemail_export_response(['state' => $state]);
}
catch (Throwable $e) {
	error_log('Voicemail export: '.$e->getMessage());
	if (headers_sent()) {
		exit;
	}
	header_remove('Content-Disposition');
	header_remove('Content-Length');
	voicemail_export_response(['error' => $text['message-export_failed']], 500);
}
