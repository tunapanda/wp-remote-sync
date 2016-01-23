<?php

class MockCurl {

	static $callCount=0;
	static $execResults=array();
	static $execUrls=array();
	static $instances=array();

	function __construct($url) {
		$this->url=$url;
		$this->opt=array();
		$this->info=array();
		$this->info[CURLINFO_HTTP_CODE]=200;

		MockCurl::$instances[]=$this;
		$this->setopt(CURLOPT_URL,$url);
	}

	static function reset() {
		MockCurl::$callCount=0;
		MockCurl::$execResults=array();
		MockCurl::$instances=array();
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
