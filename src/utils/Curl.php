<?php

/**
 * Object oriented wrapper for curl.
 */
class Curl {

	/**
	 * Constructor.
	 */
	public function __construct($url=NULL) {
		if ($url)
			$this->curl=curl_init($url);

		else
			$this->curl=curl_init();
	}

	/**
	 * Exec.
	 */
	public function exec() {
		return curl_exec($this->curl);
	}

	/**
	 * Setopt.
	 */
	public function setopt($option, $value) {
		return curl_setopt($this->curl,$option,$value);
	}

	/**
	 * Getinfo.
	 */
	public function getinfo($option) {
		return curl_getinfo($this->curl,$option);
	}

	/**
	 * Close.
	 */
	public function close() {
		curl_close($this->curl);
	}

	/**
	 * Get error.
	 */
	public function error() {
		return curl_error($this->curl);
	}
}