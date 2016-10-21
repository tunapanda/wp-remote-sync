<?php

require_once __DIR__."/ALogger.php";

/**
 * Print message to the sync window log.
 */
class JobOutputLogger {

	private $taskMessage;

	/**
	 * Show a message.
	 */
	public function log($message) {
		$m=$message;
		$m=addslashes($m);
		$m=str_replace("\n", '\n', $m);

		echo "<script>jobl('".$m."');</script>\n";
	}

	/**
	 * Begin a task.
	 */
	function task($message) {
		$this->taskMessage=$message;
		echo "<script>jobs('".addslashes($message)."');</script>\n";
	}

	/**
	 * Progress the task.
	 */
	function taskProgress($percent) {
		if (!$this->taskMessage)
			$this->taskMessage="Please wait";

		$percent=round($percent);
		echo "<script>jobs('".addslashes($this->taskMessage).": ".$percent."%');</script>\n";
	}

	/**
	 * The task is done.
	 */
	public function taskDone() {
		$this->taskMessage=NULL;
		echo "<script>jobs('');</script>\n";
	}

	/**
	 * The whole job is done.
	 */
	public function done() {
		echo "<script>jobdone();</script>\n";
	}
}