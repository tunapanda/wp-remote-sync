<?php

require_once __DIR__."/../../src/syncers/H5pSyncer.php";

class H5PSyncerTest extends WP_UnitTestCase {

	function test_extractAttachments() {
		RemoteSyncPlugin::instance()->install();

		$parameters=json_decode(file_get_contents(__DIR__."/data/parameters.json"),TRUE);

		$h5pSyncer=new H5pSyncer();
		$attachments=$h5pSyncer->extractAttachmentsFromParameters($parameters);

		echo "**** attachments ****\n";
		print_r($attachments);
	}
}

