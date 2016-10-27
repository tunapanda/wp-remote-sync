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
			if (php_sapi_name()=="cli")
				$path=$_SERVER["PWD"];

			else
				$path=$_SERVER['SCRIPT_FILENAME'];

			while (1) {
				if (file_exists($path."/wp-load.php"))
					return $path."/wp-load.php";

				$last=$path;
				$path=dirname($path);

				if ($last==$path)
					throw new \Exception("Not inside a wordpress install.");
			}
		}

		/**
		 * Get applicable taxonomies by post type.
		 */
		public static function getTaxonomiesByPostType($postType) {
			$taxonomies=get_taxonomies(array(
				"object_type"=>array($postType)
			));

			return array_keys($taxonomies);
		}
	}
}