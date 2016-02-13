<?php

require_once __DIR__."/../../src/plugin/RemoteSyncPlugin.php";

class TestSyncer {

	public function listResourceSlugs() {
		return array("a","b","c","d");
	}

	public function getResource($slug) {
		return array(
			"hello"=>"world"
		);
	}
	
	public function getResourceAttachments($slug) {
		return array("attachment.txt");
	}

	public function getAttachmentDirectory($slug) {
		return __DIR__;
	}
}

class RemoteSyncPluginTest extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		SyncResource::install();
	}

	function testPluggable() {
		add_filter("remote-syncers",function($syncers) {
			$syncers[]=new TestSyncer();
			return $syncers;
		});

		RemoteSyncPlugin::instance()->syncers=array();
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();
		$this->assertEquals(3,sizeof($syncers));

		$res=RemoteSyncPlugin::instance()->getApi()->ls(array("type"=>"TestSyncer"));
		$this->assertEquals(4,sizeof($res));

		$res=RemoteSyncPlugin::instance()->getApi()->get(array("type"=>"TestSyncer","slug"=>"c"));
		$this->assertEquals("c",$res["slug"]);
		$this->assertEquals(15,$res["attachments"][0]["fileSize"]);
	}
}

