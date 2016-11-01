<?php

require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";
require_once __DIR__."/src/model/SyncResource.php";
require_once __DIR__."/src/controller/ScheduledSyncController.php";

/*
Plugin Name: Remote Sync
Plugin URI: http://github.com/tunapanda/wp-remote-sync
Description: Sync content with a remote site in a similar way to a distributed version control system.
Version: 0.1.23
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
	switch ($_REQUEST["case"]) {
		case "syncpreview":
			require __DIR__."/tests/view/resourcelisttest.php";
			break;

		case "sync":
			require __DIR__."/tests/view/synctest.php";
			break;

		default:
			echo "No such test case.";
			exit;
	}
}

add_action('admin_menu','rs_admin_menu');

/**
 * Activate.
 */
function rs_activate() {
	if (!function_exists("curl_init"))
		trigger_error("wp-remote-sync requires the cURL module",E_USER_ERROR);

	RemoteSyncPlugin::instance()->install();
}

/**
 * Deactivate.
 */
function rs_deactivate() {
	wp_clear_scheduled_hook('rs_sheduled_sync');
}

/**
 * Uninstall.
 */
function rs_uninstall() {
	SyncResource::uninstall();

	delete_option("rs_remote_site_url");
	delete_option("rs_access_key");
	delete_option("rs_download_access_key");
	delete_option("rs_upload_access_key");
	delete_option("rs_resulotion_strategy");
}

register_activation_hook(__FILE__,'rs_activate');
register_deactivation_hook(__FILE__,'rs_deactivate');
register_uninstall_hook(__FILE__,'rs_uninstall');

/**
 * Wp cli interface.
 */
if (class_exists("WP_CLI")) {
	require_once __DIR__."/src/controller/WpCliController.php";

	WP_CLI::add_command("remote status",array(WpCliController::instance(),'status'));
	WP_CLI::add_command("remote sync",array(WpCliController::instance(),'sync'));
	WP_CLI::add_command("remote revert",array(WpCliController::instance(),'revert'));
}

add_action("rs_scheduled_sync",array(ScheduledSyncController::instance(),"run"));
