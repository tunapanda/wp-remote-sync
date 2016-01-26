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
		$this->setopt(CURLOPT_POSTFIELDS,array());
	}

	static function reset() {
		MockCurl::$callCount=0;
		MockCurl::$execResults=array();
		MockCurl::$instances=array();
		MockCurl::$execResults=array();
	}

	static function mockResult($data) {
		MockCurl::$execResults[]=$data;
	}

	static function mockResultJson($data) {
		MockCurl::mockResult(json_encode($data));
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
		$res=NULL;

		if (isset(MockCurl::$execResults[MockCurl::$callCount]))
			$res=MockCurl::$execResults[MockCurl::$callCount];

		else
			throw new Exception(
				"MockCurl: out of results, callcount=".MockCurl::$callCount.
				", url=".$this->url.
				" fields=".print_r($this->opt[CURLOPT_POSTFIELDS]));

		MockCurl::$callCount++;

		if (isset($this->opt[CURLOPT_FILE]))
			fwrite($this->opt[CURLOPT_FILE],$res);

		return $res;
	}

	function error() {
		return "";
	}

	function close() {
	}
}
