<?php

require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";
require_once __DIR__."/src/model/SyncResource.php";

/*
Plugin Name: Remote Sync
Plugin URI: http://github.com/tunapanda/wp-remote-sync
Description: Sync content with a remote site in a similar way to a distributed version control system.
Version: 0.0.12
GitHub Plugin URI: https://github.com/tunapanda/wp-remote-sync
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

	add_submenu_page(
		'options.php',
		'Remote Sync Operations',
		'Remote Sync Operations',
		'manage_options',
		'rs_operations',
		'rs_create_operations_page'
	);
}

/**
 * Admin init.
 */
function rs_admin_init() {
	register_setting("rs","rs_remote_site_url");
	register_setting("rs","rs_merge_strategy");
	register_setting("rs","rs_access_key");
}

/**
 * Create settings page.
 */
function rs_create_settings_page() {
	require __DIR__."/tpl/settings.tpl.php";
}

/**
 * Create operations page.
 */
function rs_create_operations_page() {
	echo "<style>";
	require __DIR__."/wp-remote-sync.css";
	echo "</style>";

	echo "<script>";
	require __DIR__."/wp-remote-sync.js";
	echo "</script>";

	require __DIR__."/tpl/operations.tpl.php";

	$url=plugins_url()."/wp-remote-sync/op.php?action=".$_REQUEST["action"];

	echo "<script>startSyncOperation('$url');</script>";

//	RemoteSyncPlugin::instance()->getOperations()->handleOperation($_REQUEST["action"]);
}

add_action('admin_menu','rs_admin_menu');
add_action('admin_init','rs_admin_init');

/**
 * Activation hook.
 */
function rs_activate() {
	RemoteSyncPlugin::instance()->install();
}

/**
 * Uninstall.
 */
function rs_uninstall() {
	SyncResource::uninstall();
}

register_activation_hook(__FILE__,'rs_activate');
register_uninstall_hook(__FILE__,'rs_uninstall');
