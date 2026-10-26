<?php

/**
 * speech_azure class
 *
 */
class speech_azure implements speech_interface {

	private $api_key;
	private $region;
	private $path = '';
	private $filename = '';
	private $voice = '';
	private $message = '';
	private $language = '';
	private $speed = 1.0;

	public function __construct($settings) {
		$this->api_key = trim((string)$settings->get('speech', 'api_key'));
		$this->region = strtolower(trim((string)$settings->get('speech', 'region')));
	}

	public function set_path(string $audio_path) {
		$this->path = $audio_path;
	}

	public function set_filename(string $audio_filename) {
		$this->filename = $audio_filename;
	}

	public function set_voice(string $audio_voice) {
		$this->voice = $audio_voice;
	}

	public function set_message(string $audio_message) {
		$this->message = $audio_message;
	}

	public function set_language(string $audio_language) {
		$this->language = $audio_language;
	}

	public function set_speed(float $audio_speed): void {
		if (!is_finite($audio_speed) || $audio_speed < 0.5 || $audio_speed > 2.0) {
			throw new InvalidArgumentException('Azure TTS speed must be between 0.5 and 2.0.');
		}
		$this->speed = $audio_speed;
	}

	public function set_model(string $audio_model): void {
		if ($audio_model !== '') {
			throw new InvalidArgumentException('Azure TTS selects the model through the voice.');
		}
	}

	public function get_format() : string {
		return 'wav';
	}

	public function get_models(): array {
		return [];
	}

	public function is_model_enabled(): bool {
		return false;
	}

	public function is_language_enabled() : bool {
		return false;
	}

	public function is_translate_enabled() : bool {
		return false;
	}

	public function get_languages() : array {
		return [];
	}

	public function is_speed_enabled() : bool {
		return true;
	}

	public function get_speed_options() : array {
		return [
			'0.5'  => '0.5x',
			'0.75' => '0.75x',
			'1.0'  => '1.0x (Normal)',
			'1.25' => '1.25x',
			'1.5'  => '1.5x',
			'2.0'  => '2.0x',
		];
	}

	public function get_voices() : array {
		$response = $this->request('/cognitiveservices/voices/list');
		if ($response === false) {
			return [];
		}

		$voices = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($voices) || array_values($voices) !== $voices) {
			error_log('Azure TTS returned an invalid voice list.');
			return [];
		}

		$result = [];
		foreach ($voices as $voice) {
			if (!is_array($voice) || !isset($voice['ShortName'], $voice['Locale'], $voice['Gender'])
				|| !is_string($voice['ShortName']) || $voice['ShortName'] === ''
				|| !is_string($voice['Locale']) || !is_string($voice['Gender'])) {
				error_log('Azure TTS returned an invalid voice entry.');
				return [];
			}
			if ($voice['Locale'] !== 'en-US') {
				continue;
			}
			$name = $voice['DisplayName'] ?? $voice['ShortName'];
			if (!is_string($name)) {
				error_log('Azure TTS returned an invalid voice display name.');
				return [];
			}
			$result[$voice['ShortName']] = $name.' ('.$voice['Gender'].', '.$voice['Locale'].')';
		}
		asort($result);
		return $result;
	}

	public function speech() : bool {
		if (trim($this->message) === '' || $this->voice === '' || $this->path === '' || $this->filename === '') {
			error_log('Azure TTS requires a message, voice, output path, and filename.');
			return false;
		}

		$language = $this->language;
		if ($language === '') {
			if (!preg_match('/^([a-z]{2,3}(?:-[A-Z][a-z]{3})?-(?:[A-Z]{2}|[0-9]{3}))-/', $this->voice, $matches)) {
				error_log('Azure TTS requires a voice short name such as en-US-JennyNeural.');
				return false;
			}
			$language = $matches[1];
		}

		// Treat input as plain text, never as caller-supplied SSML.
		$escape = function ($value) {
			return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
		};
		$rate = rtrim(rtrim(number_format($this->speed, 2, '.', ''), '0'), '.');
		$ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="'.$escape($language).'">'
			.'<voice name="'.$escape($this->voice).'"><prosody rate="'.$rate.'">'
			.$escape($this->message).'</prosody></voice></speak>';

		$response = $this->request('/cognitiveservices/v1', $ssml);
		if ($response === false) {
			return false;
		}
		if (strlen($response) < 12 || substr($response, 0, 4) !== 'RIFF' || substr($response, 8, 4) !== 'WAVE') {
			error_log('Azure TTS did not return WAV audio.');
			return false;
		}

		$filename = pathinfo($this->filename, PATHINFO_FILENAME).'.wav';
		$written = file_put_contents($this->path.'/'.$filename, $response);
		if ($written === false || $written !== strlen($response)) {
			error_log('Azure TTS could not save the complete audio file.');
			return false;
		}
		return true;
	}

	private function request(string $path, ?string $ssml = null) {
		if ($this->api_key === '' || preg_match('/[\r\n]/', $this->api_key)
			|| !preg_match('/^[a-z][a-z0-9]+$/', $this->region)) {
			error_log('Azure TTS requires a valid speech/api_key and speech/region.');
			return false;
		}

		$url = 'https://'.$this->region.'.tts.speech.microsoft.com'.$path;
		$headers = ['Ocp-Apim-Subscription-Key: '.$this->api_key];
		if ($ssml !== null) {
			$headers[] = 'Content-Type: application/ssml+xml; charset=utf-8';
			$headers[] = 'X-Microsoft-OutputFormat: riff-24khz-16bit-mono-pcm';
		}
		else {
			$headers[] = 'Accept: application/json';
		}

		$ch = curl_init($url);
		if ($ch === false) {
			error_log('Azure TTS could not initialize cURL.');
			return false;
		}
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_USERAGENT => 'FusionPBX-Speech',
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 50,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);
		if ($ssml !== null) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $ssml);
		}

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false) {
			error_log('Azure TTS cURL error: '.$error);
			return false;
		}
		if ($http_code !== 200) {
			error_log('Azure TTS request failed with HTTP status '.$http_code.'.');
			return false;
		}
		return $response;
	}
}
