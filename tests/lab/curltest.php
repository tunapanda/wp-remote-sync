<?php

	require_once __DIR__."/../../src/utils/Curl.php";

	/*$curl=new Curl();
	$curl
		->setopt(CURLOPT_URL,"http://localhost/repo/wp-remote-sync/tests/lab/recvfile.php?getarg=1&json=1")
		->setopt(CURLOPT_RETURNTRANSFER,TRUE)
		->setResultDecoding(Curl::JSON);
	$res=$curl->exec();

	print_r($res);*/

/*	$curl=new Curl("http://localhost/wp-sync-test-remote/wp-content/plugins/wp-remote-sync/api.php");
	$curl
//		->setopt(CURLOPT_RETURNTRANSFER,TRUE)
		->addPostField("action","getAttachment")
		->addPostField("type","attachment")
		->setResultDecoding(Curl::JSON)
		->addPostField("slug","3d")
		->addPostField("key","")
		->addPostField("attachment","2016/01/3D2.png")
		->setDownloadFileName("hello.png");
	$res=$curl->exec();*/

	function onPercent($percent) {
		echo "percent: ".$percent."\n";
	}

	$curl=new Curl("http://localhost/repo/wp-remote-sync/tests/lab/generatelongcontent.php");
	$curl->setPercentFunc("onPercent");
	$curl->setopt(CURLOPT_RETURNTRANSFER,TRUE);
	$curl->exec();
