<?php

 /**
 * speech_local class
 *
 */
class speech_local implements speech_interface {

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

		//use the settings object get method
		$this->api_url = $settings->get('speech', 'api_url', 'http://localhost:8001/speech');

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
		return false;
	}

	public function get_speed_options() : array {
		return [];
	}

	public function is_translate_enabled() : bool {
		//return the whether engine is able to translate
		return false;
	}

	public function get_voices() : array {
		$voices = array (
			"af_heart" => "Heart - American Female",
			"af_aoede" => "Aoede - American Female",
			"af_bella" => "Bella - American Female",
			"af_alloy" => "Alloy - American Female",
			"af_jessica" => "Jessica - American Female",
			"af_kore" => "Kore - American Female",
			"af_nicole" => "Nicole - American Female",
			"af_nova" => "Nova - American Female",
			"af_river" => "River - American Female",
			"af_sarah" => "Sarah - American Female",
			"af_sky" => "Sky - American Female",
			"am_adam" => "Adam - American Male",
			"am_echo" => "Echo - American Male",
			"am_eric" => "Eric - American Male",
			"am_fenrir" => "Fenrir - American Male",
			"am_liam" => "Liam - American Male",
			"am_michael" => "Michael - American Male",
			"am_onyx" => "Onyx - American Male",
			"am_puck" => "Puck - American Male",
			"am_santa" => "Santa - American Male",
			"bf_alice" => "Alice - Brittish Female",
			"bf_emma" => "Emma - Brittish Female",
			"bf_isabella" => "Isabella - Brittish Female",
			"bf_lily" => "Lily - Brittish Female",
			"bm_daniel" => "Daniel - Brittish Male",
			"bm_fable" => "Fable - Brittish Male",
			"bm_george" => "George - Brittish Male",
			"bm_lewis" => "Lewis - Brittish Male",
			"ef_dora" => "Dora - Spanish Female",
			"em_alex" => "Alex - Spanish Male",
			"em_santa" => "Santa - Spanish Male"
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
		$languages = array (
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
			'Content-Type: application/json'
		];

		// set the http data
		$data['model'] = 'tts-1-hd';
		$data['input'] = $this->message;
		$data['voice'] = $this->voice;

		//$data['response_format'] = 'wav';
		//if (isset($this->language)) {
		//	$data['language'] = $this->language;
		//}
		//if (isset($this->translate)) {
		//	$data['task'] = 'translate';
		//}

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
			error_log("Local TTS cURL error: " . $curl_error);
			return false;
		}
		else {
			// save the audio file
			//if (file_exists($this->path.'/'.$this->filename)) {
			//	unlink($this->path.'/'.$this->filename);
			//}
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
