<?php

require_once __DIR__."/ALogger.php";

/**
 * Cli logger.
 */
class WpCliLogger extends ALogger {

	/**
	 * Log a message.
	 */
	public function log($message) {
		WP_CLI::log($message);
	}
}