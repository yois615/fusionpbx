<?php

 /**
 * speech class
 *
 */
class speech_openai implements speech_interface {

	/**
	 * declare private variables
	 */
	private $api_key;
	private $api_url;
	private $path;
	private $filename;
	private $format;
	private $voice;
	private $message;
	private $model;
	private $speed;
	private $language;
	private $translate;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {

		//build the setting object and get the recording path
		$this->api_key = $settings->get('speech', 'api_key');
		$this->api_url = $settings->get('speech', 'api_url', 'https://api.openai.com/v1/audio/speech');

		//set the audio file format
		$this->format = 'wav';

	}

	public function set_path(string $audio_path) {
		$this->path = $audio_path;
	}

	public function set_filename(string $audio_filename) {
		$this->filename = $audio_filename;
	}

	public function set_speed(float $audio_speed): void {
		$this->speed = $audio_speed;
	}

	public function set_voice(string $audio_voice) {
		$this->voice = $audio_voice;
	}

	public function set_language(string $audio_language) {
		$this->language = $audio_language;
	}

	public function set_translate(string $audio_translate) {
		$this->translate = $audio_translate;
	}

	public function set_message(string $audio_message) {
		$this->message = $audio_message;
	}

	public function is_language_enabled() : bool {
		//return the whether engine is handles languages
		return false;
	}

	public function is_speed_enabled() : bool {
		return true;
	}

	public function get_speed_options() : array {
		return [
			'0.25' => '0.25x',
			'0.5'  => '0.5x',
			'0.75' => '0.75x',
			'1.0'  => '1.0x (Normal)',
			'1.25' => '1.25x',
			'1.5'  => '1.5x',
			'2.0'  => '2.0x',
			'3.0'  => '3.0x',
			'4.0'  => '4.0x',
		];
	}

	public function is_translate_enabled() : bool {
		//return the whether engine is able to translate
		return false;
	}

	public function get_voices() : array {
		$voices = array(
			"alloy" => "alloy",
			"ash" => "ash",
			"coral" => "coral",
			"echo" => "echo",
			"fable" => "fable",
			"nova" => "nova",
			"onyx" => "onyx",
			"sage" => "sage",
			"shimmer" => "shimmer"
		);

		//return the languages array
		return $voices;
	}

	public function get_format() : string {
		//return the audio file format
		return $this->format;
	}

	public function get_languages() : array {
		//create the languages array
		$languages = array(
			"af" => "Afrikaans",
			"ar" => "Arabic",
			"hy" => "Armenian",
			"az" => "Azerbaijani",
			"be" => "Belarusian",
			"bs" => "Bosnian",
			"bg" => "Bulgarian",
			"ca" => "Catalan",
			"zh" => "Chinese",
			"hr" => "Croatian",
			"cs" => "Czech",
			"da" => "Danish",
			"nl" => "Dutch",
			"en" => "English",
			"et" => "Estonian",
			"fi" => "Finnish",
			"fr" => "French",
			"gl" => "Galician",
			"de" => "German",
			"el" => "Greek",
			"he" => "Hebrew",
			"hi" => "Hindi",
			"hu" => "Hungarian",
			"is" => "Icelandic",
			"id" => "Indonesian",
			"it" => "Italian",
			"ja" => "Japanese",
			"kn" => "Kannada",
			"kk" => "Kazakh",
			"ko" => "Korean",
			"lv" => "Latvian",
			"lt" => "Lithuanian",
			"mk" => "Macedonian",
			"ms" => "Malay",
			"mr" => "Marathi",
			"mi" => "Maori",
			"ne" => "Nepali",
			"no" => "Norwegian",
			"fa" => "Persian",
			"pl" => "Polish",
			"pt" => "Portuguese",
			"ro" => "Romanian",
			"ru" => "Russian",
			"sr" => "Serbian",
			"sk" => "Slovak",
			"sl" => "Slovenian",
			"es" => "Spanish",
			"sw" => "Swahili",
			"sv" => "Swedish",
			"tl" => "Tagalog",
			"ta" => "Tamil",
			"th" => "Thai",
			"tr" => "Turkish",
			"uk" => "Ukrainian",
			"ur" => "Urdu",
			"vi" => "Vietnamese",
			"cy" => "Welsh"
		);

		//return the languages array
		return $languages;
	}

	/**
	 * speech - text to speech
	 */
	public function speech() : bool {

		// set the request headers
		$headers = [
			'Authorization: Bearer ' . $this->api_key,
			'Content-Type: application/json'
		];

		// set the http data
		$data['model'] = 'tts-1-hd';
		$data['input'] = $this->message;
		$data['voice'] = $this->voice;
		$data['response_format'] = 'wav';
		if (isset($this->speed)) {
			$data['speed'] = $this->speed;
		}
		if (isset($this->language)) {
			$data['language'] = $this->language;
		}
		if (isset($this->translate)) {
			$data['task'] = 'translate';
		}

		// initialize curl handle
		$ch = curl_init($this->api_url);

		// set the curl options
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		// set the connection timeout and the overall maximum curl run time
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);

		// follow any "Location: " header the server sends as part of the HTTP header.
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		// automatically set the Referer: field in requests where it follows a Location: redirect.
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);

		// set whether to verify SSL peer
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

		// run the curl request and get the response
		$response = curl_exec($ch);
		$curl_error = curl_error($ch);

		// close the handle
		curl_close($ch);

		// check for errors
		if ($response === false) {
			error_log("OpenAI TTS cURL error: " . $curl_error);
			return false;
		}
		else {
			// save the audio file
			file_put_contents($this->path.'/'.$this->filename, $response);
			return true;
		}

	}

	public function set_model(string $model): void {
		if (array_key_exists($model, $this->get_models())) {
			$this->model = $model;
		}
	}

	public function get_models(): array {
		return [
			'tts-1-hd' => 'tts-1-hd'
		];
	}

}
