<?php

require_once __DIR__."/utils.php";
require_once __DIR__."/operations.php";

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
	require __DIR__."/settingspage.php";
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
	require __DIR__."/operationspage.php";
	rsJobStart();

	set_exception_handler("rsOperationExceptionHandler");

	$action=$_REQUEST["action"];

	switch ($action) {
		case "Pull":
			rsPull();
			break;

		case "Push":
			rsPush();
			break;

		case "Status":
			rsStatus();
			break;

		case "Sync":
			rsSync();
			break;

		default:
			rsJobLog("Unknown operation: ".$action);
			break;
	}

	rsJobDone();
}

add_action('admin_menu','rs_admin_menu');
add_action('admin_init','rs_admin_init');

/**
 * Activation hook.
 */
function rs_activate() {
	$q=new WP_Query(array(
		"post_type"=>"any",
		"post_status"=>"any",
		"posts_per_page"=>-1
	));

	$pages=$q->get_posts();

	foreach ($pages as $page) {
		if (!get_post_meta($page->ID,"_rs_id",TRUE))
			update_post_meta($page->ID,"_rs_id",uniqid());

		update_post_meta($page->ID,"_rs_rev",uniqid());
	}
}

register_activation_hook(__FILE__,'rs_activate');

/**
 * Page saved.
 */
function rs_save_post($pageId) {
	if (wp_is_post_revision($pageId))
		return;

	if (wp_is_post_autosave($pageId))
		return;

	if (!get_post_meta($pageId,"_rs_id",TRUE))
		update_post_meta($pageId,"_rs_id",uniqid());

	update_post_meta($pageId,"_rs_rev",uniqid());
}

add_action('save_post','rs_save_post');

/**
 * Page trashed.
 */
function rs_trash_post($pageId) {
//	exit("trash post callback: ".$pageId);
	update_post_meta($pageId,"_rs_rev",uniqid());
}

add_action('wp_trash_post','rs_trash_post');
