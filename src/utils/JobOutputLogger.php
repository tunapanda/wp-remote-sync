<?php

/**
 * Print message to the sync window log.
 */
class JobOutputLogger {

	/**
	 * Show a message.
	 */
	public function message($message) {
		$m=$message;
		$m=addslashes($m);
		$m=str_replace("\n", '\n', $m);

		echo "<script>jobl('".$m."');</script>\n";
	}

	/**
	 * Show status.
	 */
	public function status($message) {
		echo "<script>jobs('".addslashes($message)."');</script>\n";
	}

	/**
	 * Show status.
	 */
	public function done() {
		echo "<script>jobdone();</script>\n";
	}
}