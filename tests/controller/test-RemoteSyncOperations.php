<?php

require_once __DIR__."/../../src/controller/RemoteSyncOperations.php";
require_once __DIR__."/../../src/utils/MockCurl.php";

class MockJob {
	function log($message) {}
}

class RemoteSyncOperationsTest extends WP_UnitTestCase {

	function test_ls() {
		RemoteSyncPlugin::instance()->install();
		RemoteSyncPlugin::instance()->Curl="MockCurl";

		MockCurl::$execResults[]='[{"globalId":123,"revision":5}]';
//		MockCurl::$execResults[]="{{{";
		MockCurl::$execResults[]="[]";

		update_option("rs_remote_site_url","http://example.com/");

		$op=new RemoteSyncOperations();
		$op->job=new MockJob();
		$op->status();
	}
}

