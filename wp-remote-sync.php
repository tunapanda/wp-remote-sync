<?php

require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";
require_once __DIR__."/src/model/SyncResource.php";

/*
Plugin Name: Remote Sync
Plugin URI: http://github.com/tunapanda/wp-remote-sync
Description: Sync content with a remote site in a similar way to a distributed version control system.
Version: 0.1.7
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
		'rs_main',
		'rs_main'
	);

	add_submenu_page(
		'options.php',
		'Remote Sync',
		'Remote Sync',
		'manage_options',
		'rs_sync_preview',
		'rs_sync_preview'
	);

	add_submenu_page(
		'options.php',
		'Remote Sync',
		'Remote Sync',
		'manage_options',
		'rs_sync',
		'rs_sync'
	);

	// test
	add_submenu_page(
		'options.php',
		'Remote Sync Operations',
		'Remote Sync Operations',
		'manage_options',
		'rs_view_test',
		'rs_view_test'
	);
}

/**
 * Show the sync preview page.
 */
function rs_sync_preview() {
	require_once __DIR__."/src/controller/RemoteSyncPageController.php";

	$controller=new RemoteSyncPageController();
	$controller->showSyncPreview();
}

/**
 * Show main page.
 */
function rs_main() {
	require_once __DIR__."/src/controller/RemoteSyncPageController.php";

	$controller=new RemoteSyncPageController();
	$controller->showMain();
}

/**
 * The sync page.
 */
function rs_sync() {
	echo "<style>";
	require __DIR__."/wp-remote-sync.css";
	echo "</style>";

	require_once __DIR__."/src/controller/RemoteSyncPageController.php";

	$controller=new RemoteSyncPageController();
	$controller->showSync();
}

/**
 * Test view.
 */
function rs_view_test() {
	require __DIR__."/tests/view/resourcelisttest.php";
}

add_action('admin_menu','rs_admin_menu');

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
