<?php
if (PHP_SAPI != 'cli') {
	http_response_code(403);
	exit;
}

$repository = dirname(__DIR__, 4);
$fixture = sys_get_temp_dir().'/fusionpbx-voicemail-api-test-'.bin2hex(random_bytes(6));
mkdir($fixture.'/app/voicemails/resources/classes', 0700, true);
mkdir($fixture.'/resources', 0700);
foreach (['voicemail_messages_export.php', 'voicemail_messages.php', 'app_languages.php'] as $name) {
	copy($repository.'/app/voicemails/'.$name, $fixture.'/app/voicemails/'.$name);
}
copy($repository.'/app/voicemails/resources/classes/voicemail_message_export.php', $fixture.'/app/voicemails/resources/classes/voicemail_message_export.php');
$bootstrap = <<<'PHP'
<?php
define('PROJECT_PATH', '');
function permission_exists($name) { global $config; return $name === 'voicemail_message_view' && ($config['view'] ?? true); }
function is_uuid($value) { return is_string($value) && preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i', $value); }
class settings {
	function __construct($options = []) {}
	function get($category, $key, $default = null) {
		global $config;
		return $category === 'voicemail' && $key === 'storage_type' ? ($config['storage'] ?? 'file') : $default;
	}
}
class text {
	function get() {
		include getcwd().'/app/voicemails/app_languages.php';
		$text['message-invalid_token']['en-us'] = 'Invalid token';
		foreach ($text as &$value) { $value = $value['en-us'] ?? ''; }
		return $text;
	}
}
class token {
	function validate($key) { global $config; return $config['token'] ?? true; }
	function create($key) { return ['name'=>'csrf', 'hash'=>'value']; }
}
#[AllowDynamicProperties]
class voicemail {
	function voicemails() { return [['voicemail_uuid'=>'00000000-0000-4000-8000-000000000001']]; }
	function messages() { return []; }
}
class button {
	static function create($values) { return '<button id="'.($values['id'] ?? '').'"></button>'; }
}
function escape($value) { return htmlspecialchars($value); }
PHP;
file_put_contents($fixture.'/resources/require.php', $bootstrap);
foreach (['check_auth.php', 'header.php', 'footer.php'] as $name) {
	file_put_contents($fixture.'/resources/'.$name, '<?php');
}
file_put_contents($fixture.'/resources/paging.php', '<?php function paging(...$args) { return ["", 50]; }');
$runner = <<<'PHP'
<?php
$config = json_decode($argv[1], true);
$_GET = $config['query'] ?? ['export_action'=>'status', 'id'=>'1000'];
$_REQUEST = $_GET;
$_POST = [];
$_SERVER['REQUEST_METHOD'] = $config['method'] ?? 'GET';
$_SERVER['PHP_SELF'] = '/app/voicemails/'.($config['page'] ?? 'voicemail_messages_export.php');
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
session_save_path(getcwd());
session_start();
$_SESSION['domain_uuid'] = 'domain';
$_SESSION['user_uuid'] = 'user';
$_SESSION['domain_name'] = 'example.test';
register_shutdown_function(function () { echo "\nHTTP_STATUS=".(http_response_code() ?: 200); });
require getcwd().$_SERVER['PHP_SELF'];
PHP;
file_put_contents($fixture.'/runner.php', $runner);
function scenario(array $config): array {
	global $fixture;
	$process = proc_open([PHP_BINARY, '-d', 'sys_temp_dir="'.str_replace('\\', '/', $fixture).'"', $fixture.'/runner.php', json_encode($config)], [
		0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']
	], $pipes, $fixture);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$code = proc_close($process);
	if ($code || $errors || !preg_match('/\nHTTP_STATUS=(\d+)$/', $output, $match)) {
		throw new RuntimeException('Request failed: '.$output.$errors);
	}
	return [(int) $match[1], substr($output, 0, -strlen($match[0]))];
}
function expect_status(array $config, int $expected): string {
	[$status, $output] = scenario($config);
	if ($status !== $expected) { throw new RuntimeException("Expected $expected, got $status: $output"); }
	return $output;
}
try {
	foreach (['status', 'start', 'download'] as $action) {
		expect_status(['storage'=>'base64', 'query'=>['export_action'=>$action, 'id'=>'1000']], 403);
	}
	expect_status(['view'=>false], 403);
	expect_status(['query'=>['export_action'=>'unknown']], 400);
	expect_status(['query'=>['export_action'=>'status', 'id'=>['not','scalar']]], 400);
	expect_status(['query'=>['export_action'=>'status', 'id'=>'../other']], 400);
	expect_status(['query'=>['export_action'=>'start']], 405);
	expect_status(['method'=>'POST', 'token'=>false, 'query'=>['export_action'=>'start']], 403);
	expect_status([], 200);
	$owner = hash('sha256', 'domain:user');
	$directory = $fixture.'/fusionpbx-voicemail-exports/'.$owner;
	$state = ['id'=>str_repeat('a',32), 'scope'=>'1000', 'mailboxes'=>['unauthorized'], 'created'=>time(), 'stage'=>'ready', 'total'=>1, 'processed'=>1, 'added'=>1, 'skipped'=>0];
	file_put_contents($directory.'/state.json', json_encode($state));
	$body = expect_status([], 200);
	if (json_decode($body, true)['state'] !== null) { throw new RuntimeException('Unauthorized job state was disclosed.'); }
	expect_status(['query'=>['export_action'=>'download', 'id'=>'1000', 'export_id'=>$state['id']]], 403);
	$base64 = expect_status(['storage'=>'base64', 'page'=>'voicemail_messages.php', 'query'=>[]], 200);
	if (str_contains($base64, 'btn_export_wavs') || str_contains($base64, 'voicemail_export_banner')) {
		throw new RuntimeException('Export UI must not be rendered in base64 mode.');
	}
	$filesystem = expect_status(['page'=>'voicemail_messages.php', 'query'=>[]], 200);
	if (!str_contains($filesystem, 'btn_export_wavs') || !str_contains($filesystem, 'voicemail_export_banner')) {
		throw new RuntimeException('Export UI must be rendered in filesystem mode.');
	}
	echo "PASS: actual page base64/filesystem rendering; API storage, permissions, CSRF, method/input guards and revoked mailbox access.\n";
}
finally {
	$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($entries as $entry) {
		$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
	}
	rmdir($fixture);
}
