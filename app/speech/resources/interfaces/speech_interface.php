<?php

/**
 * speech_interface class
 *
*/
interface speech_interface {
	public function get_languages() : array;
	public function get_models(): array;
	public function get_voices() : array;
	public function get_format() : string;
	public function is_language_enabled() : bool;
	public function is_speed_enabled() : bool;
	public function get_speed_options() : array;
	public function set_filename(string $audio_filename);
	public function set_language(string $audio_language);
	public function set_message(string $audio_message);
	public function set_model(string $audio_model): void;
	public function set_path(string $audio_path);
	public function set_speed(float $audio_speed): void;
	public function set_voice(string $audio_voice);
	public function speech() : bool;
}
