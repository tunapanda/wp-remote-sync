<?php

require_once __DIR__."/../../src/controller/RemoteSyncApi.php";

class RemoteSyncApiTest extends WP_UnitTestCase {

	function test_ls() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(0,$res);

		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"post",
			"post_content"=>"something"
		));

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(1,$res);

		wp_trash_post($id);

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(0,$res);
	}

	function test_get() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"post",
			"post_content"=>"something"
		));

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(1,$res);

		$globalId=$res[0]["globalId"];
		$resource=$api->get(array("globalId"=>$globalId));

		$this->assertEquals("something",$resource["data"]["post_content"]);
	}
}

