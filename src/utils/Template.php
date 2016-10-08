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
	 * Display the rendered template.
	 */
	public static function display($fn, $vars=array()) {
		if (!$vars)
			$vars=array();

		foreach ($vars as $key=>$value)
			$$key=$value;

		require $fn;
	}
}