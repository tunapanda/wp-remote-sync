<?php

require_once __DIR__."/ALogger.php";

/**
 * Mock logging class.
 */
class DebugLogger extends ALogger {

	private $lines=array();
	private $echo=FALSE;

	/**
	 * Log a message.
	 */
	public function log($message) {
		if ($this->echo)
			echo $message."\n";

		$this->lines[]=$message;
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