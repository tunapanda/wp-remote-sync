<?php

/**
 * Mock logging class.
 */
class MockJob {

	private $lines=array();

	/**
	 * Log a message.
	 */
	public function log($message) {
		$this->lines[]=$message;
	}

	/**
	 * Get logged messages.
	 */
	public function getMessages() {
		return $this->lines;
	}
}