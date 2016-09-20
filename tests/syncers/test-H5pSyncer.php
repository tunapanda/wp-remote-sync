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

	function test_getResourceAttachments() {
		global $wpdb;

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (slug) VALUES ('test-slug')",NULL);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$id=$wpdb->insert_id;

		$upload_dir_info=wp_upload_dir();
		$uploadBasedir=$upload_dir_info["basedir"];
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

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (slug) VALUES ('test-slug')",NULL);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$h5pSyncer=new H5pSyncer();
		$a=$h5pSyncer->listResourceSlugs();

		$this->assertEquals($a,array("test-slug"));
	}

	function test_updateLibraries() {
		global $wpdb;

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_libraries (name, major_version, minor_version, patch_version) VALUES ('TestLib',1,1,0),('TestStable',1,0,0),('TestUpgrade',1,0,0),('TestUpgrade',1,0,1)");
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$h5pSyncer=new H5pSyncer();

		$h5pData=array(
			"title"=>"Test Content",
			"parameters"=>"this_is_json... :)",
			"filtered"=>"this_is_also_json... :)",
			"slug"=>"h5p-test",
			"embed_type"=>"mumble mumble...",
			"disable"=>"0",
			"content_type"=>"something",
			"keywords"=>"a b c",
			"description"=>"this is just a test",
			"license"=>"open source",
			"library"=>array("name"=>"TestLib","major_version"=>"1","minor_version"=>"1","patch_version"=>"0"),
			"libraries"=>array(
				array(
					"name"=>"TestStable",
					"minor_version"=>"0",
					"major_version"=>"1",
					"patch_version"=>"0",
					"dependency_type"=>"test",
					"weight"=>"3",
					"drop_css"=>"0",
				),
				array(
					"name"=>"TestUpgrade",
					"minor_version"=>"0",
					"major_version"=>"1",
					"patch_version"=>"0",
					"dependency_type"=>"test",
					"weight"=>"3",
					"drop_css"=>"0",
				)
			)
		);

		$h5pSyncer->createResource("h5p-test",$h5pData);

		$h5pData["libraries"][1]["patch_version"]="1";
		$h5pSyncer->updateResource("h5p-test",$h5pData);


	}
}

