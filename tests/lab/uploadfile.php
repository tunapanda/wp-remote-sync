<?php

require_once __DIR__."/../../src/utils/Curl.php";

$curl=new Curl("http://localhost/repo/wp-remote-sync/tests/lab/recvfile.php");
$curl->setopt(CURLOPT_RETURNTRANSFER,TRUE);
$curl->setopt(CURLOPT_POST,1);
$postfields=array();

$postfields["hello/world.txt"]=new CurlFile(
	__DIR__."/file.txt",
	"text/plain",
	"/some/file.hello.worl.txt"
);

$curl->setopt(CURLOPT_POSTFIELDS,$postfields);

$res=$curl->exec();
echo $res;