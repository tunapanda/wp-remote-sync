<?php

require_once __DIR__."/../../src/controller/RemoteSyncApi.php";

class RemoteSyncApiTest extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		delete_option("download_access_key");
		delete_option("upload_access_key");
	}

	function test_ls() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(0,$res);

		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"post",
			"post_content"=>"something",
			"post_name"=>"hello-slug"
		));

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(1,$res);

		$syncResources=
			SyncResource::findAllForType(
				"post",
				SyncResource::POPULATE_LOCAL
			);

		$this->assertCount(1,$syncResources);
		$this->assertEquals(NULL,$syncResources[0]->id);
		$res0=$syncResources[0];
		$res0->save();
		$this->assertNotEquals(NULL,$syncResources[0]->id);

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
			"post_content"=>"something",
			"post_name"=>"hello-slug"
		));

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(1,$res);

		$slug=$res[0]["slug"];
		$this->assertEquals("hello-slug",$slug);

		$resource=$api->get(array("type"=>"post","slug"=>$slug));

		$this->assertEquals("something",$resource["data"]["post_content"]);
	}

	function test_add() {
		update_option("rs_upload_access_key","may_i_upload_please");

		RemoteSyncPlugin::instance()->install();
		$api=RemoteSyncPlugin::instance()->getApi();

		$data=array(
			"post_name"=>"some-slug",
			"post_type"=>"page",
			"post_content"=>"hello",
			"post_title"=>"title",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>"",
			"menu_order"=>0,
			"meta"=>array()
		);

		$api->add(array(
			"slug"=>"some-slug",
			"type"=>"post",
			"data"=>json_encode($data),
			"key"=>"may_i_upload_please"
		));

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"post_name"=>"some-slug"
		));

		$posts=$q->get_posts();
		$this->assertEquals(1,sizeof($posts));

		try {
			$api->add(array(
				"slug"=>"some-slug",
				"type"=>"post",
				"data"=>json_encode($data),
				"key"=>"may_i_upload_please"
			));
		}

		catch (Exception $e) {
			$exceptionMessage=$e->getMessage();
		}

		$this->assertEquals($exceptionMessage,"Already exists!");
	}

	function test_put() {
		update_option("rs_upload_access_key","may_i_upload");

		RemoteSyncPlugin::instance()->install();
		$api=RemoteSyncPlugin::instance()->getApi();

		$data=array(
			"post_name"=>"some-slug",
			"post_type"=>"page",
			"post_content"=>"hello",
			"post_title"=>"title",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>NULL,
			"menu_order"=>0
		);

		try {
			$api->put(array(
				"baseRevision"=>"123",
				"slug"=>"some-slug",
				"type"=>"post",
				"data"=>json_encode($data),
				"key"=>"may_i_upload"
			));
		}

		catch (Exception $e) {
			$exceptionMessage=$e->getMessage();
		}

		$this->assertEquals($exceptionMessage,"Doesn't exist locally");

		$data=array(
			"post_name"=>"some-slug",
			"post_type"=>"page",
			"post_content"=>"hello",
			"post_title"=>"title",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>"",
			"menu_order"=>0,
			"meta"=>array()
		);

		$api->add(array(
			"slug"=>"some-slug",
			"type"=>"post",
			"data"=>json_encode($data),
			"key"=>"may_i_upload"
		));

		$resData=$api->get(array(
			"type"=>"post",
			"slug"=>"some-slug"
		));

		$this->assertEquals("98caf22f76c2d40eed50cf642db03e8b",$resData["revision"]);

		$data["post_content"]="some new content";

		try {
			$api->put(array(
				"slug"=>"some-slug",
				"type"=>"post",
				"baseRevision"=>"wrong",
				"data"=>json_encode($data),
				"key"=>"may_i_upload"
			));
		}

		catch (Exception $e) {
			$exceptionMessage=$e->getMessage();
		}

		$this->assertEquals($exceptionMessage,"Wrong base revision, please pull.");

		$api->put(array(
			"slug"=>"some-slug",
			"type"=>"post",
			"baseRevision"=>"98caf22f76c2d40eed50cf642db03e8b",
			"data"=>json_encode($data),
			"key"=>"may_i_upload"
		));

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"post_name"=>"some-slug"
		));

		$posts=$q->get_posts();
		$this->assertEquals(1,sizeof($posts));
		$this->assertEquals($posts[0]->post_content,"some new content");
	}

	function test_get_attachment() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"attachment",
			"post_content"=>"something",
			"post_name"=>"test-attachment"
		));

		update_post_meta($id,"_wp_attached_file","helloworld");

		$upload_dir_info=wp_upload_dir();
		file_put_contents($upload_dir_info["basedir"]."/helloworld","content");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType("attachment");
		$attachments=$syncer->getResourceAttachments("test-attachment");
		$this->assertEquals($attachments,array(
			"helloworld"
		));

		$res=$api->ls(array("type"=>"attachment"));
		$this->assertCount(1,$res);
		$this->assertEquals($res[0]["slug"],"test-attachment");

		$resource=$api->get(array("slug"=>"test-attachment","type"=>"attachment"));

		$this->assertEquals(sizeof($resource["attachments"]),1);
		$this->assertEquals(
			$resource["attachments"][0],
			array(
				"fileName"=>"helloworld",
				"fileSize"=>7
			)
		);
	}

	function test_doApiCall(){
		RemoteSyncPlugin::instance()->install();
		$id=wp_insert_post(array(
			"post_title"=>"hello",
			"post_type"=>"post",
			"post_content"=>"something",
			"post_name"=>"the-name"
		));

		update_option('rs_upload_access_key', "test");
		$api = new RemoteSyncApi();

		// test listing with the right key
		$ls_res1 = array();
		$args = array("key"=>"test", "type"=>"post", "version"=>4);
		$ls_res1 = $api->doApiCall("ls", $args);
		//print_r($ls_res1);

		//test listing with the wrong key
		$ls_res2 = array();
		$args = array("key"=>"Wrong Key", "type"=>"post","version"=>4);
		$ls_res2 = $api->doApiCall("ls", $args);
		//print_r($ls_res2);
		
		// test deleting with wrong key
		$del_res1 = array();
		$args = array("key"=>"wrong key", "type"=>"post", "slug"=>$ls_res1[0]["slug"],"version"=>4);
		$caught=NULL;
		try {
			$del_res1 = $api->doApiCall("del", $args);
		}

		catch (Exception $e) {
			$caught=$e;
		}
		$this->assertEquals($caught->getMessage(),"Not allowed to perform this action.");

		//print_r($del_res1);

		// test deleting with right key
		$del_res1 = array();
		$args = array("key"=>"test", "type"=>"post", "slug"=>$ls_res1[0]["slug"], "version"=>4);
		$del_res1 = $api->doApiCall("del", $args);
		//print_r($del_res1);
	}

	function test_accessLevel() {
		$api=new RemoteSyncApi();

		$this->assertEquals($api->getAccessLevel(array()),"download");

		update_option("rs_download_access_key","hello");
		$this->assertEquals($api->getAccessLevel(array()),NULL);
		$this->assertEquals($api->getAccessLevel(array("key"=>"hello")),"download");

		update_option("rs_upload_access_key","world");
		$this->assertEquals($api->getAccessLevel(array()),NULL);
		$this->assertEquals($api->getAccessLevel(array("key"=>"hello")),"download");
		$this->assertEquals($api->getAccessLevel(array("key"=>"world")),"upload");
	}

	function test_accessLevel2() {
		$api=new RemoteSyncApi();
		$api->requireAccessLevel(array("key"=>"something_random"),"download");
	}

	/**
	 * @expectedException Exception
	 */
	function test_accessLevel3() {
		$api=new RemoteSyncApi();
		update_option("rs_download_access_key","hello");
		$api->requireAccessLevel(array("key"=>"something_random"),"download");
	}

	/**
	 * @expectedException Exception
	 */
	function test_accessLevel4() {
		$api=new RemoteSyncApi();
		update_option("rs_download_access_key","hello");
		update_option("rs_upload_access_key","world");
		$api->requireAccessLevel(array("key"=>"hello"),"upload");
	}

	function test_accessLevel5() {
		$api=new RemoteSyncApi();
		update_option("rs_download_access_key","hello");
		update_option("rs_upload_access_key","world");
		$api->requireAccessLevel(array("key"=>"world"),"upload");
	}
}

