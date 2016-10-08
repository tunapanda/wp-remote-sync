<?php
	/**
	 * Apache utilities.
	 */
	class ApacheUtil {
		/**
		 * Make as much effort as we can to disable output buffering.
		 * Contents currently in the buffer will be discarded.
		 */
		public static function disableBuffering() {
			//header('X-Accel-Buffering: no');

			/*if (function_exists("apache_setenv"))
				@apache_setenv('no-gzip', 1);*/

			@ini_set('zlib.output_compression', 0);
			@ini_set('implicit_flush', 1);
			$levels=ob_get_level();
			for ($i=0; $i<$levels; $i++) {
				ob_end_flush();
			}
			ob_implicit_flush(TRUE);
			flush();
		}
	} 