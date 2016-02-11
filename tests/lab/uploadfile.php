<?php

require_once __DIR__."/../../src/utils/Curl.php";

$curl=new Curl("http://learning.tunapanda.org/wp-content/plugins/wp-remote-sync/tests/lab/recvfile.php");
$curl->setopt(CURLOPT_RETURNTRANSFER,TRUE);
$curl->setopt(CURLOPT_POST,1);
$curl->addFileUpload("thefile.txt",__DIR__."/file.txt");

$res=$curl->exec();
echo $res;