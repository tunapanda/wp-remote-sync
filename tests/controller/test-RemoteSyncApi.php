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

	function test_get_attachment() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"attachment",
			"post_content"=>"something"
		));

		update_post_meta($id,"_wp_attached_file","helloworld");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType("attachment");
		$attachments=$syncer->getResourceAttachments($id);
		$this->assertEquals($attachments,array(
			"helloworld"
		));

		$res=$api->ls(array("type"=>"attachment"));
		$this->assertCount(1,$res);

		$globalId=$res[0]["globalId"];
		$resource=$api->get(array("globalId"=>$globalId));

		$this->assertEquals($resource["attachments"],array(
			"helloworld"
		));
	}

	function test_doApiCall(){
		RemoteSyncPlugin::instance()->install();
		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"post",
			"post_content"=>"something"
		));

		update_option('rs_access_key', "test");
		$api = new RemoteSyncApi();

		// test listing with the right key
		$ls_res1 = array();
		$args = array("key"=>"test", "type"=>"post");
		$ls_res1 = $api->doApiCall("ls", $args);
		print_r($ls_res1);

		//test listing with the wrong key
		$ls_res2 = array();
		$args = array("key"=>"Wrong Key", "type"=>"post");
		$ls_res2 = $api->doApiCall("ls", $args);
		print_r($ls_res2);
		
		// test deleting with wrong key
		$del_res1 = array();
		$args = array("key"=>"wrong key", "type"=>"post", "globalId"=>$ls_res1[0]["globalId"]);
		$del_res1 = $api->doApiCall("del", $args);
		print_r($del_res1);

		// test deleting with right key
		$del_res1 = array();
		$args = array("key"=>"test", "type"=>"post", "globalId"=>$ls_res1[0]["globalId"]);
		$del_res1 = $api->doApiCall("del", $args);
		print_r($del_res1);
	}
}

