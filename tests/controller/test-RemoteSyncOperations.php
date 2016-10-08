<?php

require_once __DIR__."/../../src/controller/RemoteSyncOperations.php";
require_once __DIR__."/../../src/utils/MockJob.php";

class RemoteSyncOperationsTest extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		Curl::initMock();

		RemoteSyncPlugin::instance()->setLogger(new MockJob());
	}

	function test_status() {
		RemoteSyncPlugin::instance()->install();

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>"5","weight"=>"")
		));
		Curl::mockResult(array());

		update_option("rs_remote_site_url","http://example.com/");

		$op=new RemoteSyncOperations();
		$op->status();

		$messages=RemoteSyncPlugin::instance()->getLogger()->getMessages();
		$this->assertEquals(3,sizeof($messages));
		$this->assertEquals($messages[0],"Status: post");
		$this->assertEquals($messages[1],"  New remote items:       1");
		$this->assertEquals($messages[2],"Status: attachment");
	}

	function test_pull() {
		RemoteSyncPlugin::instance()->install();

		$data=array(
			"post_name"=>"the-slug",
			"post_title"=>"title",
			"post_type"=>"page",
			"post_content"=>"hello",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>"",
			"menu_order"=>0,
			"meta"=>array()
		);

		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>$rev,"weight"=>"")
		));
		Curl::mockResult(array(
			"slug"=>"the-slug",
			"revision"=>$rev,
			"type"=>"post",
			"data"=>$data,
			"attachments"=>array(),
			"binary"=>FALSE
		));
		Curl::mockResult(array());

		update_option("rs_remote_site_url","http://example.com/");

		$op=new RemoteSyncOperations();
		$op->pull();

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any"
		));
		$this->assertEquals(1,sizeof($q->get_posts()));

		$messages=RemoteSyncPlugin::instance()->getLogger()->getMessages();
		$this->assertEquals(3,sizeof($messages));

		/**** Updated data. ****/
		$data=array(
			"post_name"=>"the-slug",
			"post_title"=>"title",
			"post_type"=>"page",
			"post_content"=>"some new content",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>"",
			"menu_order"=>0,
			"meta"=>array()
		);

		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>$rev,"weight"=>"")
		));
		Curl::mockResult(array(
			"slug"=>"the-slug",
			"revision"=>$rev,
			"type"=>"post",
			"data"=>$data,
			"attachments"=>array(),
			"binary"=>FALSE
		));
		Curl::mockResult(array());

		RemoteSyncPlugin::instance()->setLogger(new MockJob());

		$op=new RemoteSyncOperations();
		$op->pull();

		$messages=RemoteSyncPlugin::instance()->getLogger()->getMessages();
		$this->assertEquals(3,sizeof($messages));
		$this->assertEquals($messages[1],"  the-slug: Updated local.");

		/**** Deleted data. ****/
		Curl::initMock();
		Curl::mockResult(array());
		Curl::mockResult(array());

		RemoteSyncPlugin::instance()->setLogger(new MockJob());

		$op=new RemoteSyncOperations();
		$op->pull();

		$messages=RemoteSyncPlugin::instance()->getLogger()->getMessages();
		$this->assertEquals(3,sizeof($messages));
		$this->assertEquals($messages[1],"  the-slug: Deleted local.");

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any"
		));
		$this->assertEquals(0,sizeof($q->get_posts()));
	}

	function test_push() {
		RemoteSyncPlugin::instance()->install();

		$postId=wp_insert_post(array(
			'post_content'=>'content',
			'post_name'=>'the-slug',
			'post_title'=>"Hello Post"
		));

		update_option("rs_remote_site_url","http://example.com/");

		Curl::mockResult(array());
		Curl::mockResult(array());
		Curl::mockResult(array());

		$op=new RemoteSyncOperations();
		$op->push();

		wp_trash_post($postId);
		Curl::initMock();
		Curl::mockResult(array(
			array("slug"=>'the-slug','revision'=>"hello","weight"=>"")
		));
		Curl::mockResult(array());
		Curl::mockResult(array());

		$op=new RemoteSyncOperations();
		$op->push();
	}
}
