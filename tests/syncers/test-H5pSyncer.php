<?php

require_once __DIR__."/../../src/syncers/H5pSyncer.php";
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class H5PSyncerTest extends WP_UnitTestCase {

	private function createH5pTables() {
		global $wpdb;

		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}h5p_contents ( ".
			"id INTEGER NOT NULL auto_increment, ".
			"slug VARCHAR(255) not null, ".
			"PRIMARY KEY(id))"
		);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);
	}

	function test_getResourceAttachments() {
		global $wpdb;
		$wpdb->show_errors();
		$this->createH5pTables();

		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (slug) VALUES ('test-slug')",NULL);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$id=$wpdb->insert_id;

		$uploadBasedir=wp_upload_dir()["basedir"];
		if (!file_exists($uploadBasedir."/h5p/content/{$id}/images/"))
			mkdir($uploadBasedir."/h5p/content/{$id}/images/",0777,TRUE);

		file_put_contents($uploadBasedir."/h5p/content/{$id}/images/test.txt","hello");

		$h5pSyncer=new H5pSyncer();
		$attachments=$h5pSyncer->getResourceAttachments("test-slug");

		$this->assertEquals(1,sizeof($attachments));
		$this->assertEquals($attachments[0],"images/test.txt");
	}

	function test_getResourceSlugs() {
		global $wpdb;
		$wpdb->show_errors();
		$this->createH5pTables();

		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (slug) VALUES ('test-slug')",NULL);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$h5pSyncer=new H5pSyncer();
		$a=$h5pSyncer->listResourceSlugs();

		$this->assertEquals($a,array("test-slug"));
	}
}

