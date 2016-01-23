<?php

require_once __DIR__."/../../src/syncers/H5pSyncer.php";

class H5PSyncerTest extends WP_UnitTestCase {

	function test_getResourceAttachments() {
		RemoteSyncPlugin::instance()->syncers=NULL;
		RemoteSyncPlugin::instance()->install();

		$uploadBasedir=wp_upload_dir()["basedir"];
		if (!file_exists($uploadBasedir."/h5p/content/555/images/"))
			mkdir($uploadBasedir."/h5p/content/555/images/",0777,TRUE);

		file_put_contents($uploadBasedir."/h5p/content/555/images/test.txt","hello");

		$h5pSyncer=new H5pSyncer();
		$attachments=$h5pSyncer->getResourceAttachments(555);

		$this->assertEquals(1,sizeof($attachments));
		$this->assertEquals($attachments[0],"h5p/content/{id}/images/test.txt");
	}
}

