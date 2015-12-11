<?php

	namespace remotesync;

	/**
	 * Wordpress utils.
	 */
	if (!class_exists("remotesync\\WpUtil")) {
		class WpUtil {

			/**
			 * Bootstrap from inside a plugin.
			 */
			public static function getWpLoadPath() {
				$path=$_SERVER['SCRIPT_FILENAME'];

				for ($i=0; $i<4; $i++)
					$path=dirname($path);

				return $path."/wp-load.php";
			}
		}
	}