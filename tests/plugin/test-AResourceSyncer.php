<?php

require_once __DIR__."/../../src/plugin/AResourceSyncer.php";

class TestSyncer extends AResourceSyncer {

	public function __construct() {
		parent::__construct("test");
	}

	function listResourceIds() {}
	function getResource($localId) {}
	function updateResource($localId, $data) {}
	function createResource($data) {}
	function deleteResource($localId) {}
	function mergeResourceData($base, $local, $remote) {}
	function getResourceLabel($data) {}
	function getResourceRevision($localId) {}
}

class AResourceSyncerTest extends WP_UnitTestCase {

	function test_merge() {
		$testSyncer=new TestSyncer();

		$base="hello\nworld";
		$local="hello\nchange";
		$remote="hello\nanother";

		$merged=$testSyncer->merge($base,$local,$remote);
		$this->assertEquals($merged,"hello\nanother");

		update_option("rs_merge_strategy","prioritize_local");

		$merged=$testSyncer->merge($base,$local,$remote);
		$this->assertEquals($merged,"hello\nchange");
	}

	function test_pickMerge() {
		$testSyncer=new TestSyncer();

		$base=array("hello"=>array(1,2,3));
		$local=array("hello"=>array(1,2,5));
		$remote=array("hello"=>array(1,2,3));
	}
}

