<?php

require_once __DIR__."/../../src/utils/Template.php";
require_once __DIR__."/../../src/utils/JobOutputLogger.php";
require_once __DIR__."/../../src/utils/ApacheUtil.php";

use remotesync\Template;

echo "<style>";
require __DIR__."/../../wp-remote-sync.css";
echo "</style>";

$params=array();
Template::display(__DIR__."/../../tpl/sync.tpl.php",$params);

ApacheUtil::disableBuffering();

$job=new JobOutputLogger();

for ($i=0; $i<=10; $i++) {
	$job->log("hello: ".$i);
	sleep(1);
}

$job->done();