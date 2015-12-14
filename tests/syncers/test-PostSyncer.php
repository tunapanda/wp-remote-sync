<?php

require_once __DIR__."/../../src/syncers/PostSyncer.php";

class PostSyncerTest extends WP_UnitTestCase {

	function test_ls() {
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(0,$res);

		$id1=wp_insert_post(array(
			"post_title"=>"post one",
		));

		$id2=wp_insert_post(array(
			"post_title"=>"post two",
			"post_parent"=>$id1
		));

		$id3=wp_insert_post(array(
			"post_title"=>"post three",
			"post_parent"=>$id2
		));

		RemoteSyncPlugin::instance()->getSyncerByType("post")->updateSyncResources();

		$gid1=RemoteSyncPlugin::instance()->getSyncerByType("post")->localToGlobal($id1);
		$gid2=RemoteSyncPlugin::instance()->getSyncerByType("post")->localToGlobal($id2);
		$gid3=RemoteSyncPlugin::instance()->getSyncerByType("post")->localToGlobal($id3);

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(3,$res);

		$this->assertEquals($gid1,$res[0]["globalId"]);
		$this->assertEquals($gid2,$res[1]["globalId"]);
		$this->assertEquals($gid3,$res[2]["globalId"]);

		$post1=get_post($id1);
		$post1->post_parent=NULL;
		wp_update_post($post1);

		$post2=get_post($id2);
		$post2->post_parent=NULL;
		wp_update_post($post2);

		$post3=get_post($id3);
		$post3->post_parent=NULL;
		wp_update_post($post3);

		$post1=get_post($id1);
		$post1->post_parent=$id2;
		wp_update_post($post1);

		$post2=get_post($id2);
		$post2->post_parent=$id3;
		wp_update_post($post2);

		$post3=get_post($id3);
		$post3->post_parent=NULL;
		wp_update_post($post3);

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(3,$res);

		$this->assertEquals($gid3,$res[0]["globalId"]);
		$this->assertEquals($gid2,$res[1]["globalId"]);
		$this->assertEquals($gid1,$res[2]["globalId"]);
	}
}

