<?php

require_once __DIR__."/../../src/utils/Curl.php";

class CurlTest extends WP_UnitTestCase {

	function test_mock() {
		Curl::initMock();
		Curl::mockResult("helloworld");

		$curl=new Curl();
		$res=$curl->exec();

		$this->assertEquals($res,"helloworld");
	}
}

