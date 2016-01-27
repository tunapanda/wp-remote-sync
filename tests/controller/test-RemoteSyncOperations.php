<?php

require_once __DIR__."/../../src/controller/RemoteSyncOperations.php";
require_once __DIR__."/../../src/utils/MockJob.php";

class RemoteSyncOperationsTest extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		Curl::initMock();
	}

	function test_status() {
		RemoteSyncPlugin::instance()->install();

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>"5")
		));
		Curl::mockResult(array());

		update_option("rs_remote_site_url","http://example.com/");

		$job=new MockJob();

		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->status();

		$messages=$job->getMessages();
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
			"post_parent"=>NULL,
			"menu_order"=>0
		);

		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>$rev)
		));
		Curl::mockResult(array(
			"slug"=>"the-slug",
			"revision"=>$rev,
			"type"=>"post",
			"data"=>$data,
			"attachments"=>array()
		));
		Curl::mockResult(array());

		update_option("rs_remote_site_url","http://example.com/");

		$job=new MockJob();
		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->pull();

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any"
		));
		$this->assertEquals(1,sizeof($q->get_posts()));

		$messages=$job->getMessages();
		$this->assertEquals(3,sizeof($messages));
		//print_r($messages);

		/**** Updated data. ****/
		$data=array(
			"post_name"=>"the-slug",
			"post_title"=>"title",
			"post_type"=>"page",
			"post_content"=>"some new content",
			"post_excerpt"=>"ex",
			"post_status"=>"published",
			"post_parent"=>NULL,
			"menu_order"=>0
		);

		$rev=md5(json_encode($data));

		Curl::mockResult(array(
			array("slug"=>"the-slug","revision"=>$rev)
		));
		Curl::mockResult(array(
			"slug"=>"the-slug",
			"revision"=>$rev,
			"type"=>"post",
			"data"=>$data,
			"attachments"=>array()
		));
		Curl::mockResult(array());

		$job=new MockJob();
		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->pull();

		$messages=$job->getMessages();
		$this->assertEquals(3,sizeof($messages));
		$this->assertEquals($messages[1],"  the-slug: Updated local.");

		/**** Deleted data. ****/
		Curl::initMock();
		Curl::mockResult(array());
		Curl::mockResult(array());

		$job=new MockJob();
		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->pull();

		$messages=$job->getMessages();
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

		$job=new MockJob();
		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->push();

		wp_trash_post($postId);
		Curl::initMock();
		Curl::mockResult(array(
			array("slug"=>'the-slug','revision'=>"hello")
		));
		Curl::mockResult(array());
		Curl::mockResult(array());

		$job=new MockJob();
		$op=new RemoteSyncOperations();
		$op->job=$job;
		$op->push();
	}
}
