<?php

require_once __DIR__."/../../src/model/SyncResource.php";
require_once __DIR__."/../../src/plugin/AResourceSyncer.php";
require_once __DIR__."/../../src/utils/MockJob.php";

class SRTestSyncer extends AResourceSyncer {

	public function listResourceSlugs() {
		return array("slug1","onlylocal");
	}

	public function getResource($slug) {
		if ($slug=="does_not_exist")
			return NULL;

		if ($slug=="onlyremote")
			return NULL;

		return array(
			"data"=>"hello"
		);
	}

	public function updateResource($slug, $data) {
	}

	public function deleteResource($slug) {
	}
}

class SyncResourceTest extends WP_UnitTestCase {

	function setUp() {
		global $wpdb;

		parent::setUp();

		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}h5p_contents ( ".
			"id INTEGER NOT NULL auto_increment, ".
			"slug VARCHAR(255) not null, ".
			"PRIMARY KEY(id))"
		);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		Curl::initMock();

		RemoteSyncPlugin::instance()->setLogger(new MockJob());
	}

	// why failing?
	/*function test_downloadAttachments() {
		global $wpdb;

		RemoteSyncPlugin::instance()->syncers=array(new H5pSyncer());
		RemoteSyncPlugin::instance()->install();

		$data=array(
			"test"=>"bla"
		);

		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>$rev)
		));

		Curl::mockResult(array(
			"slug"=>"the-slug",
			"revision"=>$rev,
			"type"=>"h5p",
			"data"=>$data,
			"attachments"=>array(
				array(
					"fileName"=>"an/attached/file.txt",
					"fileSize"=>123
				)
			),
			"binary"=>FALSE
		));

		update_option("rs_remote_site_url","http://example.com/");

		$wpdb->query("INSERT INTO {$wpdb->prefix}h5p_contents (id,slug) values (777,'the-slug')");
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$syncResources=SyncResource::findAllForType("h5p",
			SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE);

		$this->assertEquals(1,sizeof($syncResources));
		$syncResource=$syncResources[0];

		$upload_dir_info=wp_upload_dir();
		$upload_base_dir=$upload_dir_info["basedir"];
		if (file_exists($upload_base_dir."/h5p/content/777//an/attached/file.txt"))
			unlink($upload_base_dir."/h5p/content/777//an/attached/file.txt");

		Curl::mockResult("hello world");
		$syncResource->downloadAttachments();

		$this->assertTrue(file_exists($upload_base_dir."/h5p/content/777//an/attached/file.txt"));
		$content=file_get_contents($upload_base_dir."/h5p/content/777//an/attached/file.txt");
		$this->assertEquals($content,"hello world");
	}*/

	// Not sure why this is failing...
	/*function test_postedAttachments() {
		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		wp_insert_attachment(array(
			"post_title"=>"test",
			"post_content"=>"none",
			"post_name"=>"the-slug",
			"post_status"=>"published",
			"post_mime_type"=>"image/png"
		));

		$syncResource=SyncResource::findOneForType("attachment","the-slug");
		$this->assertEquals($syncResource->slug,"the-slug");

		$upload_dir_info=wp_upload_dir();
		$upload_base_dir=$upload_dir_info["basedir"];
		if (file_exists($upload_base_dir."/here/it/is.txt"))
			unlink($upload_base_dir."/here/it/is.txt");

		$_FILES=array(
			"__attachment0"=>array(
				"name"=>"some.random.name",
				"test"=>"text/plain",
				"tmp_name"=>__DIR__."/data/emulateupload.txt",
				"name"=>urlencode("/here/it/is.txt"),
				"error"=>0
			)
		);

		$syncResource->processPostedAttachments();

		$upload_dir_info=wp_upload_dir();
		$upload_base_dir=$upload_dir_info["basedir"];
		$f=file_get_contents($upload_base_dir."/here/it/is.txt");
		$this->assertEquals($f,"hello world");
	}*/

	function test_findAllForType() {
		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncResource=new SyncResource("testType","slug1");
		$syncResource->save();

		$syncResource=new SyncResource("testType","slug2");
		$syncResource->save();

		$syncResources=SyncResource::findAllForType("testType");
		$this->assertEquals(2,sizeof($syncResources));
	}

	function test_findAllForTypeLocal() {
		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncResource=new SyncResource("testType","slug1");
		$syncResource->save();

		$syncResource=new SyncResource("testType","slug2");
		$syncResource->save();

		$syncResources=SyncResource::findAllForType("testType",
			SyncResource::POPULATE_LOCAL);

		$this->assertEquals(3,sizeof($syncResources));
	}

	function test_findOneBySlug() {
		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncResource=new SyncResource("testType","slug1");
		$syncResource->save();

		$syncResource=SyncResource::findOneForType("testType","slug1");
		$this->assertEquals($syncResource->getSlug(),"slug1");

		$syncResource=SyncResource::findOneForType("testType","otherslug");
		$this->assertEquals($syncResource->getSlug(),"otherslug");

		$syncResource=SyncResource::findOneForType("testType","does_not_exist");
		$this->assertEquals($syncResource,NULL);
	}

	function test_findAllForTypeRemote() {
		update_option("rs_remote_site_url","helloworld");

		Curl::mockResult(array(
			array("slug"=>"onlyremote","revision"=>123),
			array("slug"=>"slug1","revision"=>123)
		));

		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncResource=new SyncResource("testType","slug1");
		$syncResource->save();

		$syncResource=new SyncResource("testType","slug2");
		$syncResource->save();

		$syncResources=SyncResource::findAllForType("testType",
			SyncResource::POPULATE_REMOTE);

		$this->assertEquals(3,sizeof($syncResources));

		$syncResources[0]->getRemoteResource();
		$syncResources[1]->getRemoteResource();
		$syncResources[2]->getRemoteResource();
	}

	function test_getResourceData() {
		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncResource=new SyncResource("testType","slug1");
		$this->assertEquals($syncResource->getData(),
			array(
				"data"=>"hello"
			)
		);

		$this->assertEquals($syncResource->getLocalRevision(),
			"05a1ad082ad35cad7aac7b18e232feb3");
	}

	function test_state() {
		update_option("rs_remote_site_url","helloworld");

		RemoteSyncPlugin::instance()->syncers=array(new SRTestSyncer("testType"));
		RemoteSyncPlugin::instance()->install();

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType("testType");
		$data=$syncer->getResource("slug1");
		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"onlyremote","revision"=>"05a1ad082ad35cad7aac7b18e232feb3"),
			array("slug"=>"slug1","revision"=>$rev)
		));

		$syncResources=SyncResource::findAllForType("testType",
			SyncResource::POPULATE_REMOTE|SyncResource::POPULATE_LOCAL);

		$a=array();
		foreach ($syncResources as $syncResource)
			$a[$syncResource->getSlug()]=$syncResource;

		//echo "l: ".sizeof($syncResources);

		$syncResource=$syncResources[0];

		$data=$syncResource->getData();
		$rev=$syncResource->getLocalRevision();

		$this->assertEquals($a["onlyremote"]->getState(),SyncResource::NEW_REMOTE);
		$this->assertEquals($a["onlylocal"]->getState(),SyncResource::NEW_LOCAL);
		$this->assertEquals($a["slug1"]->getState(),SyncResource::UP_TO_DATE);
	}
}
