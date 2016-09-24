<?php

require_once __DIR__."/../../src/syncers/H5pSyncer.php";
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class H5PSyncerTest extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		global $wpdb;
		$wpdb->show_errors();

		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}h5p_contents ( ".
			"id INTEGER NOT NULL auto_increment, ".
			"slug VARCHAR(255) not null, ".
			"title VARCHAR(255) not null, ".
			"parameters VARCHAR(255) not null, ".
			"filtered VARCHAR(255) not null, ".
			"embed_type VARCHAR(255) not null, ".
			"disable INTEGER not null, ".
			"content_type VARCHAR(255) not null, ".
			"license VARCHAR(255) not null, ".
			"keywords VARCHAR(255) not null, ".
			"description VARCHAR(255) not null, ".
			"library_id INTEGER not null,".
			"PRIMARY KEY(id))"
		);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}h5p_libraries ( ".
			"id INTEGER NOT NULL auto_increment, ".
			"name VARCHAR(255) not null, ".
			"major_version INTEGER not null, ".
			"minor_version INTEGER not null, ".
			"patch_version INTEGER not null, ".
			"PRIMARY KEY(id))"
		);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}h5p_contents_libraries ( ".
			"content_id INTEGER NOT NULL, ".
			"library_id INTEGER NOT NULL, ".
			"dependency_type VARCHAR(255) not null, ".
			"weight INTEGER NOT NULL, ".
			"drop_css INTEGER NOT NULL, ".
			"PRIMARY KEY(content_id,library_id,dependency_type))"
		);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();
	}

	function test_getResourceSlugs() {
		global $wpdb;

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (slug) VALUES ('test-slug')",NULL);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$h5pSyncer=new H5pSyncer();
		$a=$h5pSyncer->listResourceSlugs();

		$this->assertEquals($a,array("test-slug"));
	}
}

