<?php

require_once __DIR__."/ALogger.php";

/**
 * Log to a file.
 */
class FileLogger extends ALogger {

	/**
	 * File logger.
	 */
	public function __construct($fn) {
		$this->f=fopen($fn,"a");

		if (!$this->f)
			throw new Exception("Unable to open log file");
	}

	/**
	 * Log a message.
	 */
	public function log($message) {
		fputs($this->f,$message."\n");
		fflush($this->f);
	}

	/**
	 * Done.
	 */
	public function done() {
		fclose($this->f);
		$this->f=NULL;
	}
}