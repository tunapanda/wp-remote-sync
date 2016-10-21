<?php

/**
 * Something that can log stuff.
 */
abstract class ALogger {

	/**
	 * Log a message.
	 */
	abstract function log($message);

	/**
	 * Begin working on a task that might take some time.
	 */
	function task($message) {
	}

	/**
	 * Indicate percentage for the task.
	 */
	function taskProgress($percent) {
	}

	/**
	 * The current task is done.
	 */
	function taskDone() {
	}

	/**
	 * The whole job is done.
	 */
	function done() {
	}
}