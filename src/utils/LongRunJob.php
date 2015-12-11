<?php

/**
 * Manage a long running job
 */
class LongRunJob {

	/**
	 * Start the job.
	 */
	public function start() {
		// Turn off output buffering
		ini_set('output_buffering', 'off');
		// Turn off PHP output compression
		if (!headers_sent())
			ini_set('zlib.output_compression', false);

		//Flush (send) the output buffer and turn off output buffering
		//ob_end_flush();
		while (@ob_end_flush());
	         
		// Implicitly flush the buffer(s)
		ini_set('implicit_flush', true);
		ob_implicit_flush(true);

		//prevent apache from buffering it for deflate/gzip
		if (!headers_sent()) {
			header("Content-type: text/plain");
			header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
		}

		for($i = 0; $i < 1000; $i++) {
		    echo ' ';
		}

		ob_flush();
		flush();
	}

	/**
	 * Log a line.
	 */
	public function log($message) {
		echo "<pre>";
		echo $message;
		echo "</pre>";
		ob_flush();
		flush();
	}

	/**
	 * Set text to show when the job is complete.
	 */
	public function setAfterText($text) {
		$this->afterText=$text;
	}

	/**
	 * Done.
	 */
	public function done() {
		if ($this->afterText)
			echo $this->afterText;
	}
}