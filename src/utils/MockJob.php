<?php

/**
 * Mock logging class.
 */
class MockJob {

	private $lines=array();
	private $echo=FALSE;

	/**
	 *
	 */
	public function message($message) {
		$tihs->log($message);
	}

	/**
	 * Log a message.
	 */
	public function log($message) {
		if ($this->echo)
			echo $message."\n";

		$this->lines[]=$message;
	}

	/**
	 * Status.
	 */
	public function status($message) {
	}

	/**
	 * Get logged messages.
	 */
	public function getMessages() {
		return $this->lines;
	}

	/**
	 * Set echo.
	 */
	public function setEcho($echo) {
		$this->echo=$echo;
	}
}