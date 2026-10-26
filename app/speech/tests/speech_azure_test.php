<?php

// Run with php -n so the transport stubs cannot contact Azure or use credentials.
if (extension_loaded('curl')) {
	throw new RuntimeException('Run this test with php -n (without the cURL extension).');
}

foreach ([
	'CURLOPT_RETURNTRANSFER', 'CURLOPT_HTTPHEADER', 'CURLOPT_USERAGENT',
	'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_SSL_VERIFYPEER',
	'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLINFO_HTTP_CODE',
] as $index => $name) {
	define($name, $index + 1);
}

$responses = [];
$requests = [];

function curl_init($url) {
	return (object)['url' => $url, 'options' => []];
}

function curl_setopt_array($handle, $options) {
	$handle->options = $options + $handle->options;
	return true;
}

function curl_setopt($handle, $option, $value) {
	$handle->options[$option] = $value;
	return true;
}

function curl_exec($handle) {
	global $responses, $requests;
	if (!$responses) {
		throw new RuntimeException('Unexpected HTTP request.');
	}
	$requests[] = $handle;
	$handle->response = array_shift($responses);
	return $handle->response['body'];
}

function curl_error($handle) {
	return $handle->response['error'] ?? '';
}

function curl_getinfo($handle, $option) {
	return $handle->response['status'];
}

function curl_close($handle) {
}

class settings {
	private $values;

	public function __construct(array $values = []) {
		$this->values = $values + ['engine' => 'azure', 'api_key' => 'test-key', 'region' => 'eastus'];
	}

	public function get($category, $name, $default = null) {
		return $this->values[$name] ?? $default;
	}
}

require_once __DIR__.'/../resources/interfaces/speech_interface.php';
require_once __DIR__.'/../resources/classes/speech_azure.php';
require_once __DIR__.'/../resources/classes/speech.php';

function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function respond($body, $status = 200, $error = '') {
	global $responses;
	$responses[] = ['body' => $body, 'status' => $status, 'error' => $error];
}

$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'speech-azure-'.bin2hex(random_bytes(8));
mkdir($directory);
$previous_log = ini_set('error_log', $directory.'/errors.log');
$previous_reporting = error_reporting(E_ALL);
$audio_file = $directory.'/greeting.wav';
$wav = 'RIFF'.pack('V', 38).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16)
	.'data'.pack('V', 2)."\0\0";

try {
	$engine = new speech_azure(new settings());
	check($engine instanceof speech_interface, 'Engine must implement the speech interface.');
	check($engine->get_format() === 'wav', 'Expected WAV format.');
	check(!$engine->is_language_enabled() && !$engine->is_translate_enabled(), 'Voice determines language; translation is unsupported.');
	check(!$engine->is_model_enabled() && $engine->get_models() === [], 'Voice determines model.');
	check($engine->get_languages() === [], 'No separate language selector is required.');
	check($engine->is_speed_enabled(), 'Speed control must be enabled.');
	foreach ($engine->get_speed_options() as $speed => $label) {
		$engine->set_speed((float)$speed);
	}
	foreach ([0.49, 2.01, NAN, INF] as $speed) {
		try {
			$engine->set_speed($speed);
			throw new RuntimeException('Invalid speed was accepted.');
		}
		catch (InvalidArgumentException $exception) {
		}
	}
	$engine->set_model('');
	try {
		$engine->set_model('tts-1');
		throw new RuntimeException('Unsupported model was accepted.');
	}
	catch (InvalidArgumentException $exception) {
	}

	respond(json_encode([
		['ShortName' => 'en-US-JennyNeural', 'DisplayName' => 'Jenny', 'Gender' => 'Female', 'Locale' => 'en-US'],
		['ShortName' => 'de-DE-ConradNeural', 'DisplayName' => 'Conrad', 'Gender' => 'Male', 'Locale' => 'de-DE'],
		['ShortName' => 'en-GB-SoniaNeural', 'DisplayName' => 'Sonia', 'Gender' => 'Female', 'Locale' => 'en-GB'],
		['ShortName' => 'en-US-AriaNeural', 'DisplayName' => 'Aria', 'Gender' => 'Female', 'Locale' => 'en-US'],
	]));
	$voices = $engine->get_voices();
	check($voices['en-US-JennyNeural'] === 'Jenny (Female, en-US)', 'Voice short names must be returned as keys.');
	check(array_keys($voices) === ['en-US-AriaNeural', 'en-US-JennyNeural'], 'Only en-US voices should be returned, sorted by label.');
	respond(json_encode([
		['ShortName' => 'de-DE-ConradNeural', 'DisplayName' => 'Conrad', 'Gender' => 'Male', 'Locale' => 'de-DE'],
	]));
	check($engine->get_voices() === [], 'A voice list without en-US voices must return an empty array.');
	$request = end($requests);
	check($request->url === 'https://eastus.tts.speech.microsoft.com/cognitiveservices/voices/list', 'Incorrect voice-list URL.');
	check(!isset($request->options[CURLOPT_POST]), 'Voice discovery must use GET.');
	check(in_array('Ocp-Apim-Subscription-Key: test-key', $request->options[CURLOPT_HTTPHEADER], true), 'Missing subscription key header.');

	$engine->set_path($directory);
	$engine->set_filename('greeting.mp3');
	$engine->set_voice('en-US-JennyNeural');
	$engine->set_message('Hello & <break/> "world"');
	$engine->set_speed(1.25);
	respond($wav);
	check($engine->speech(), 'Synthesis should succeed.');
	check(file_get_contents($audio_file) === $wav, 'WAV bytes must be saved with the correct extension.');
	$request = end($requests);
	check($request->url === 'https://eastus.tts.speech.microsoft.com/cognitiveservices/v1', 'Incorrect synthesis URL.');
	check($request->options[CURLOPT_POST] === true, 'Synthesis must use POST.');
	check(in_array('X-Microsoft-OutputFormat: riff-24khz-16bit-mono-pcm', $request->options[CURLOPT_HTTPHEADER], true), 'Incorrect output encoding.');
	check(in_array('Content-Type: application/ssml+xml; charset=utf-8', $request->options[CURLOPT_HTTPHEADER], true), 'Incorrect SSML content type.');
	check($request->options[CURLOPT_SSL_VERIFYPEER] && $request->options[CURLOPT_SSL_VERIFYHOST] === 2, 'TLS verification must be enabled.');
	check($request->options[CURLOPT_CONNECTTIMEOUT] > 0 && $request->options[CURLOPT_TIMEOUT] > 0, 'Requests must have timeouts.');
	$ssml = $request->options[CURLOPT_POSTFIELDS];
	check(strpos($ssml, 'xml:lang="en-US"') !== false, 'Locale must be inferred from the voice.');
	check(strpos($ssml, '<prosody rate="1.25">') !== false, 'Speed must be sent to Azure.');
	check(strpos($ssml, 'Hello &amp; &lt;break/&gt; &quot;world&quot;') !== false, 'Text must be XML-escaped.');
	$xml = new DOMDocument();
	check($xml->loadXML($ssml), 'Generated SSML must be well-formed XML.');
	check($xml->documentElement->namespaceURI === 'http://www.w3.org/2001/10/synthesis', 'SSML requires the synthesis namespace.');
	check($xml->getElementsByTagName('prosody')->item(0)->textContent === 'Hello & <break/> "world"', 'XML parsing must preserve the original plain text.');
	check($xml->getElementsByTagName('break')->length === 0, 'Caller text must not become SSML elements.');
	foreach ([0.5, 1.0, 2.0] as $speed) {
		$engine->set_speed($speed);
		respond($wav);
		check($engine->speech(), 'Boundary and normal speeds must synthesize successfully.');
		$xml->loadXML(end($requests)->options[CURLOPT_POSTFIELDS]);
		check((float)$xml->getElementsByTagName('prosody')->item(0)->getAttribute('rate') === $speed, 'Speed multiplier must be preserved.');
	}

	foreach (['sr-Latn-RS-NicholasNeural' => 'sr-Latn-RS', 'es-MX-DaliaNeural' => 'es-MX'] as $voice => $locale) {
		$engine->set_voice($voice);
		respond($wav);
		check($engine->speech(), 'Non-English synthesis should succeed.');
		check(strpos(end($requests)->options[CURLOPT_POSTFIELDS], 'xml:lang="'.$locale.'"') !== false, 'Incorrect non-English locale.');
	}
	$engine->set_language('fr-FR');
	respond($wav);
	check($engine->speech(), 'Explicit language should be accepted.');
	check(strpos(end($requests)->options[CURLOPT_POSTFIELDS], 'xml:lang="fr-FR"') !== false, 'Explicit language was ignored.');
	$engine->set_language('');

	foreach ([[false, 0, 'Connection timed out'], ['{"error":"denied"}', 401, ''], ['limited', 429, ''], ['unavailable', 503, ''], ['', 200, ''], ['{"error":"not audio"}', 200, '']] as $failure) {
		respond(...$failure);
		check(!$engine->speech(), 'Transport, HTTP, and invalid-audio failures must return false.');
		check(file_get_contents($audio_file) === $wav, 'Failures must not overwrite existing audio.');
	}
	foreach (['not json', '{"error":"bad"}', '[{}]', '[{"ShortName":123,"Gender":"Female","Locale":"en-US"}]'] as $invalid) {
		respond($invalid);
		check($engine->get_voices() === [], 'Malformed voice lists must be rejected.');
	}
	respond('denied', 403);
	check($engine->get_voices() === [], 'Voice-list HTTP errors must be rejected.');
	respond(false, 0, 'Connection timed out');
	check($engine->get_voices() === [], 'Voice-list transport errors must be rejected.');

	$count = count($requests);
	foreach ([['api_key' => ''], ['api_key' => "key\r\nInjected: header"], ['region' => ''], ['region' => 'eastus/invalid']] as $invalid) {
		check((new speech_azure(new settings($invalid)))->get_voices() === [], 'Invalid configuration must fail.');
	}
	$engine->set_voice('invalid');
	check(!$engine->speech(), 'Invalid voice must fail.');
	$engine->set_message('');
	check(!$engine->speech(), 'Empty input must fail.');
	check(count($requests) === $count, 'Invalid inputs must not send requests.');

	$engine->set_message('Hello');
	$engine->set_voice('en-US-JennyNeural');
	$engine->set_path($directory.'/missing');
	respond($wav);
	set_error_handler(function ($severity, $message) {
		check(strpos($message, 'file_put_contents') !== false, 'Unexpected PHP warning: '.$message);
		return true;
	});
	try {
		check(!$engine->speech(), 'File-write errors must return false.');
	}
	finally {
		restore_error_handler();
	}

	$facade = new speech(new settings());
	respond('[]');
	check($facade->get_voices() === [] && $facade->get_format() === 'wav', 'Facade must resolve the Azure engine.');
	$facade->audio_path = $directory;
	$facade->audio_filename = 'greeting.wav';
	$facade->audio_voice = 'en-US-JennyNeural';
	$facade->audio_message = 'Facade test';
	$facade->audio_speed = 0.5;
	respond($wav);
	$facade->speech();
	check(strpos(end($requests)->options[CURLOPT_POSTFIELDS], '<prosody rate="0.5">Facade test') !== false, 'Facade must forward synthesis parameters.');
	check(file_get_contents($audio_file) === $wav, 'Facade must generate the requested output.');
	check($responses === [], 'Not all expected requests were made.');
	$log = file_get_contents($directory.'/errors.log');
	check(strpos($log, 'HTTP status 401') !== false && strpos($log, 'could not save') !== false, 'Failures must be logged.');
	check(strpos($log, 'test-key') === false, 'Logs must not expose credentials.');
	$x = 0;
	$apps = [];
	require __DIR__.'/../app_config.php';
	$region_settings = array_filter($apps[$x]['default_settings'], function ($setting) {
		return $setting['default_setting_category'] === 'speech' && $setting['default_setting_subcategory'] === 'region';
	});
	check(count($region_settings) === 1, 'Upgrade configuration must register exactly one Speech region setting.');
	echo "Azure speech tests passed.\n";
}
finally {
	ini_set('error_log', $previous_log);
	error_reporting($previous_reporting);
	foreach ([$audio_file, $directory.'/errors.log'] as $file) {
		if (is_file($file)) {
			unlink($file);
		}
	}
	rmdir($directory);
}
