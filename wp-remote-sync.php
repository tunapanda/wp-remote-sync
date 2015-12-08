<?php

/*
Plugin Name: Remote Sync
Plugin URI: http://github.com/tunapanda/wp-remote-sync
Description: Sync content with a remote site in a similar way to a distributed version control system.
Version: 0.0.5
*/

/**
 * Create the admin menu.
 */
function rs_admin_menu() {
	add_options_page(
		'Remote Sync',
		'Remote Sync',
		'manage_options',
		'rs_settings',
		'rs_create_settings_page'
	);
}

/**
 * Admin init.
 */
function rs_admin_init() {
	register_setting("rs","rs_remote_site_url");
}

/**
 * Create settings page.
 */
function rs_create_settings_page() {
	require_once __DIR__."/settings.php";
}

add_action('admin_menu','rs_admin_menu');
add_action('admin_init','rs_admin_init');
