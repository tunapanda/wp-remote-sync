<?php

require_once __DIR__."/../../src/syncers/PostSyncer.php";

class PostSyncerTest extends WP_UnitTestCase {

	function test_ls() {
		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		$api=new RemoteSyncApi();

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(0,$res);

		$id1=wp_insert_post(array(
			"post_title"=>"post one",
			"post_name"=>"post-one"
		));

		$id2=wp_insert_post(array(
			"post_title"=>"post two",
			"post_parent"=>$id1,
			"post_name"=>"post-two"
		));

		$id3=wp_insert_post(array(
			"post_title"=>"post three",
			"post_parent"=>$id2,
			"post_name"=>"post-three"
		));

		$res=$api->ls(array("type"=>"post"));
		$this->assertCount(3,$res);

		$this->assertEquals("post-one",$res[0]["slug"]);
		$this->assertEquals("post-two",$res[1]["slug"]);
		$this->assertEquals("post-three",$res[2]["slug"]);

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

		$this->assertEquals("post-three",$res[0]["slug"]);
		$this->assertEquals("post-two",$res[1]["slug"]);
		$this->assertEquals("post-one",$res[2]["slug"]);
	}
}

