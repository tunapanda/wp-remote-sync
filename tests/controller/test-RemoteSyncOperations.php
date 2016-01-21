<?php

require_once __DIR__."/../../src/controller/RemoteSyncOperations.php";

class MockJob {
	function log($message) {}
}

class MockCurl {

	static $callCount=0;
	static $execResults=array();
	static $execUrls=array();

	function __construct($url) {
		$this->url=$url;
		$this->opt=array();
		$this->info=array();

		$this->info[CURLINFO_HTTP_CODE]=200;
	}

	function setopt($option, $value) {
		$this->opt[$option]=$value;
	}

	function getinfo($option) {
		if (isset($this->info[$option]))
			return $this->info[$option];

		return NULL;
	}

	function exec() {
		MockCurl::$execUrls[]=$this->url;
		$res=MockCurl::$execResults[MockCurl::$callCount];

		MockCurl::$callCount++;

		return $res;
	}

	function error() {
		return "";
	}

	function close() {
	}
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

