<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * speech_inworld class
 *
 * @method null download
 */
if (!class_exists('speech_inworld')) {
	class speech_inworld implements speech_interface {

		/**
		 * declare private variables
		 */
		private $app_name;
		private $app_uuid;
		private $settings;

		/**
		 * declare public variables
		 */
		public $domain;
		public $text;
		public $file;
		public $path;
		public $filename;
		public $message;
		public $language;
		public $voice_id;
		public $api_key;
		public $api_secret;
		public $workspace_id;
		public $character_id;
		public $debug;

		/**
		 * called when the object is created
		 */
		public function __construct($settings = null) {
			//assign private variables
				$this->app_name = 'speech';
				$this->app_uuid = 'f9a4f42e-2e31-4c24-8da2-0d0349611e3f';

			//set defaults
				$this->domain = 'api.inworld.ai'; // Inworld API domain

			//store settings object
				$this->settings = $settings;

			//get API key from settings if available
			//FusionPBX uses a generic 'api_key' setting that's shared across all speech providers
				if ($settings !== null) {
					$this->api_key = $settings->get('speech', 'api_key');
				}
		}

		/**
		 * called when there are no references to a particular object
		 * unset the variables used in the class
		 */
		public function __destruct() {
			foreach ($this as $key => $value) {
				unset($this->$key);
			}
		}

		/**
		 * get the audio format
		 */
		public function get_format() : string {
			//Inworld returns PCM 16kHz format
			return 'wav';
		}

		/**
		 * get available models (Inworld doesn't use models, return empty)
		 */
		public function get_models() : array {
			return [];
		}

		/**
		 * is_translate_enabled
		 */
		public function is_translate_enabled() : bool {
			return false;
		}

		/**
		 * is_language_enabled
		 */
		public function is_language_enabled() : bool {
			return true;
		}

		/**
		 * get_languages
		 */
		public function get_languages() : array {
			return [
				'en' => 'English',
				'es' => 'Spanish',
				'fr' => 'French',
				'de' => 'German',
				'it' => 'Italian',
				'pt' => 'Portuguese',
				'pl' => 'Polish',
				'zh' => 'Chinese',
				'ja' => 'Japanese',
				'ko' => 'Korean',
				'nl' => 'Dutch',
				'ru' => 'Russian'
			];
		}

		/**
		 * get available voices from Inworld API
		 */
		public function get_voices() : array {
			// Initialize voices array
			$voices = [];

			// Only try API if we have an api_key set
			if (!empty($this->api_key)) {
				try {
					// Inworld API endpoint for listing voices
					$url = "https://api.inworld.ai/tts/v1/voices";

					$headers = [
						'Authorization: Basic ' . $this->api_key,
						'Accept: application/json'
					];

					// Make the API request
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
					curl_setopt($ch, CURLOPT_TIMEOUT, 5);
					curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

					$response = curl_exec($ch);
					$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$curl_error = curl_error($ch);
					unset($ch);

					// Parse successful response
					if ($http_code == 200 && $response) {
						$json_response = json_decode($response, true);

						// Inworld returns: {"voices": [{"voiceId": "...", "displayName": "...", ...}]}
						if (isset($json_response['voices']) && is_array($json_response['voices'])) {
							foreach ($json_response['voices'] as $voice) {
								$voice_id = $voice['voiceId'] ?? $voice['name'] ?? '';
								$display_name = $voice['displayName'] ?? $voice['name'] ?? $voice_id;
								$description = $voice['description'] ?? '';
								$languages = $voice['languages'] ?? [];

								// If displayName is empty, use voiceId
								if (empty($display_name)) {
									$display_name = $voice_id;
								}

								if (!empty($voice_id)) {
									// Build detailed voice info
									$voice_info = $display_name;

									// Add description if available
									if (!empty($description)) {
										$voice_info .= ' - ' . $description;
									}

									// Build a multi-dimensional voices array
									if (!empty($languages) && is_array($languages)) {
										foreach ($languages as $language_code) {
											$voices[$language_code][$voice_id] = $voice_info;
										}
									}
								}
							}

							error_log("Inworld API: Successfully fetched " . count($voices) . " voices");
						}
					} else {
						error_log("Inworld API Error: HTTP $http_code" . ($curl_error ? " - $curl_error" : ""));
						if ($response) {
							error_log("Response: " . substr($response, 0, 500));
						}
					}
				} catch (Exception $e) {
					error_log("Inworld API Exception: " . $e->getMessage());
				}
			}

			// If no voices were fetched, return empty array (like ElevenLabs does)
			// This way the dropdown will be empty if API key is invalid/missing
			return $voices;
		}

		/**
		 * download the audio file from Inworld AI
		 */
		public function download() {
			//set default language if not provided
				if (!isset($this->language) || empty($this->language)) {
					$this->language = 'en';
				}

			//validate required parameters
				if (empty($this->text)) {
					throw new Exception("Text is required");
				}
				if (empty($this->voice_id)) {
					throw new Exception("Voice ID is required");
				}
				if (empty($this->api_key)) {
					throw new Exception("API key is required - set the Basic (Base64) key from Inworld");
				}

			//prepare the API request
				// Inworld TTS synthesize endpoint (non-streaming)
				$url = "https://api.inworld.ai/tts/v1/voice";

				$headers = [
					'Content-Type: application/json',
					'Authorization: Basic ' . $this->api_key
				];

				// Build request data according to Inworld API spec
				$data = [
					'text' => $this->text,
					'voiceId' => $this->voice_id,
					'modelId' => 'inworld-tts-1'
				];

				//debug output
				if ($this->debug) {
					echo "Inworld API Request:\n";
					echo "URL: $url\n";
					echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
				}

			//make the API request
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);

				$response = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curl_error = curl_error($ch);
				unset($ch);

				//debug output
				if ($this->debug) {
					echo "HTTP Code: $http_code\n";
					if ($curl_error) {
						echo "cURL Error: $curl_error\n";
					}
				}

			//handle the response
				if ($http_code == 200 && $response) {
					// Inworld returns JSON with base64 audio in 'audioContent' field
					$json_response = json_decode($response, true);

					// Debug: log what we got
					error_log("Inworld API response keys: " . ($json_response ? implode(', ', array_keys($json_response)) : 'NOT JSON'));

					if (isset($json_response['audioContent'])) {
						// Decode base64 audio data
						$audio_data = base64_decode($json_response['audioContent']);

						// Save to file
						if (file_put_contents($this->file, $audio_data)) {
							if ($this->debug) {
								echo "Audio saved successfully to: " . $this->file . "\n";
							}
							error_log("Inworld: Audio saved successfully to " . $this->file);
							return true;
						} else {
							throw new Exception("Failed to write audio file to: " . $this->file);
						}
					} else {
						error_log("Inworld API response (first 500 chars): " . substr($response, 0, 500));
						throw new Exception("No audioContent in response. Keys found: " . ($json_response ? implode(', ', array_keys($json_response)) : 'NOT JSON'));
					}
				} else {
					$error_msg = "HTTP Error $http_code";
					if ($response) {
						$error_response = json_decode($response, true);
						if (isset($error_response['error'])) {
							$error_msg .= ": " . (isset($error_response['error']['message']) ? $error_response['error']['message'] : json_encode($error_response['error']));
						} else {
							$error_msg .= ": " . substr($response, 0, 200);
						}
					}
					if ($curl_error) {
						$error_msg .= " (cURL: $curl_error)";
					}
					throw new Exception($error_msg);
				}
		}

		/**
		 * speech_interface implementation methods
		 */

		/**
		 * set_path - set the file path
		 */
		public function set_path(string $path) : void {
			$this->path = $path;
		}

		/**
		 * set_filename - set the filename
		 */
		public function set_filename(string $filename) : void {
			$this->filename = $filename;
			$this->file = $this->path . '/' . $filename;
		}

		/**
		 * set_voice - set the voice ID
		 */
		public function set_voice(string $voice_id) : void {
			$this->voice_id = $voice_id;
		}

		/**
		 * set_language - set the language
		 */
		public function set_language(string $language) : void {
			$this->language = $language;
		}

		/**
		 * is_speed_enabled
		 */
		public function is_speed_enabled() : bool {
			return false;
		}

		/**
		 * get_speed_options
		 */
		public function get_speed_options() : array {
			return [];
		}

		/**
		 * set_speed - set the speech speed (Inworld doesn't support speed control)
		 */
		public function set_speed(float $audio_speed) : void {
			// Inworld doesn't support speed control
		}

		/**
		 * set_model - set the model (Inworld doesn't use models, so this is a no-op)
		 */
		public function set_model(string $model) : void {
			// Inworld doesn't use models, so we just ignore this
		}

		/**
		 * set_message - set the message text
		 */
		public function set_message(string $message) : void {
			$this->message = $message;
			$this->text = $message;
		}

		/**
		 * speech - generate the speech (calls download)
		 */
		public function speech() : bool {
			try {
				error_log("Inworld speech() called - voice: {$this->voice_id}, text: " . substr($this->text, 0, 50));
				$this->download();
				return true;
			} catch (Exception $e) {
				error_log("Inworld speech generation error: " . $e->getMessage());
				return false;
			}
		}

	}
}

?>
