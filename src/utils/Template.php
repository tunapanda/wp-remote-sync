<?php

namespace remotesync;

/**
 * Simple template renderer.
 */
class Template {

	/**
	 * Render the template to a string.
	 */
	public static function render($fn, $vars=array()) {
		foreach ($vars as $key=>$value)
			$$key=$value;

		ob_start();
		require $fn;
		return ob_get_clean();
	}

	/**
	 * Echo out the rendered template.
	 */
	public static function print($fn, $vars=array()) {
		foreach ($vars as $key=>$value)
			$$key=$value;

		require $fn;
	}
}