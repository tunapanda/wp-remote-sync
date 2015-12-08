<?php

/**
 * Flush to browser and make as much effort as possible to turn off
 * output buffering. Initiates a long running operation.
 */
function jobStart() {
	// Turn off output buffering
	ini_set('output_buffering', 'off');
	// Turn off PHP output compression
	ini_set('zlib.output_compression', false);

	//Flush (send) the output buffer and turn off output buffering
	//ob_end_flush();
	while (@ob_end_flush());
         
	// Implicitly flush the buffer(s)
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);

	//prevent apache from buffering it for deflate/gzip
	header("Content-type: text/plain");
	header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

	for($i = 0; $i < 1000; $i++) {
	    echo ' ';
	}

	ob_flush();
	flush();
}

/**
 * Log a line for a job.
 */
function jobLog($message) {
	echo "<pre>";
	echo $message;
	echo "</pre>";
	ob_flush();
	flush();
}