<?php

require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";

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
}

/**
 * Create settings page.
 */
function rs_create_settings_page() {
	require __DIR__."/tpl/settings.tpl.php";
}

/**
 * Handle exceptions during an operation.
 */
function rsOperationExceptionHandler($exception) {
	rsJobLog("** Error **");
	rsJobLog($exception->getMessage());
	rsJobDone();
	exit();
}

/**
 * Create operations page.
 */
function rs_create_operations_page() {
	require __DIR__."/tpl/operations.tpl.php";

	$plugin=new RemoteSyncPlugin();
	$plugin->getOperations()->handleOperation($_REQUEST["action"]);
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

/**
 * Post saved.
 */
function rs_save_post($id) {
	if (wp_is_post_revision($id))
		return;

	if (wp_is_post_autosave($id))
		return;

	$status=get_post_status($id);
	if ($status=="auto-draft")
		return;

	RemoteSyncPlugin::instance()->getSyncerByType("post")->notifyLocalChange($id);
}

add_action('save_post','rs_save_post');

/**
 * Post trashed.
 */
function rs_trash_post($id) {
	RemoteSyncPlugin::instance()->getSyncerByType("post")->notifyLocalChange($id);
}

add_action('wp_trash_post','rs_trash_post');
