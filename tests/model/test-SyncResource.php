<?php

require_once __DIR__."/../../src/model/SyncResource.php";
require_once __DIR__."/../../src/syncers/H5pSyncer.php";
require_once __DIR__."/../../src/utils/MockCurl.php";

class SyncResourceTest extends WP_UnitTestCase {

	function test_getAttachmentEntries() {
		RemoteSyncPlugin::instance()->install();
		RemoteSyncPlugin::instance()->syncers=array(
			new H5pSyncer()
		);

		$uploadBasedir=wp_upload_dir()["basedir"];
		if (!file_exists($uploadBasedir."/h5p/content/999/images/"))
			mkdir($uploadBasedir."/h5p/content/999/images/",0777,TRUE);

		file_put_contents($uploadBasedir."/h5p/content/999/images/test.txt","hello");

		$syncResource=new SyncResource("h5p");
		$syncResource->localId=999;
		$entries=$syncResource->getAttachmentEntries();

		$this->assertEquals($entries,array(
			"h5p%2Fcontent%2F%7Bid%7D%2Fimages%2Ftest.txt"=>
				$uploadBasedir."/h5p/content/999/images/test.txt"
		));
	}

	function test_downloadAttachments() {
		if (file_exists(wp_upload_dir()["basedir"]."/hello777world"))
			@unlink(wp_upload_dir()["basedir"]."/hello777world");

		update_option("rs_remote_site_url","hellosite");

		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		$remoteResource=new RemoteResource("attachment","global=123","123");
		$remoteResource->fetched=TRUE;
		$remoteResource->attachments=array(
			"hello{id}world"
		);

		MockCurl::reset();
		MockCurl::$execResults[]="hello remote file";

		$syncResource=new SyncResource("attachment");
		$syncResource->Curl="MockCurl";
		$syncResource->localId=777;
		$syncResource->downloadAttachments($remoteResource);

		$this->assertEquals(sizeof(MockCurl::$instances),1);
		$mockCurl=MockCurl::$instances[0];

		$this->assertEquals(
			$mockCurl->opt[CURLOPT_URL],
			"hellosite/wp-content/plugins/wp-remote-sync/api.php?action=getAttachment&key=&filename=hello%7Bid%7Dworld&globalId=global%3D123"
		);

		$this->assertTrue(file_exists(wp_upload_dir()["basedir"]."/hello777world"));
	}

	function test_postedAttachments() {
		$syncResource=new SyncResource("attachment");

		$upload_base_dir=wp_upload_dir()["basedir"];
		if (file_exists($upload_base_dir."/some/dir/123/here/it/is.txt"))
			unlink($upload_base_dir."/some/dir/123/here/it/is.txt");

		$_FILES=array(
			"__attachment0"=>array(
				"name"=>"some.random.name",
				"test"=>"text/plain",
				"tmp_name"=>__DIR__."/data/emulateupload.txt",
				"name"=>urlencode("some/dir/{id}/here/it/is.txt"),
				"error"=>0
			)
		);

		$syncResource->localId=123;
		$syncResource->processPostedAttachments();

		$upload_base_dir=wp_upload_dir()["basedir"];
		$f=file_get_contents($upload_base_dir."/some/dir/123/here/it/is.txt");
		$this->assertEquals($f,"hello world");
	}
}
