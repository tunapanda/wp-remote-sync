<html>
	<body>
		Testing buffering... There should be 10 lines, and they should appear one by one with 10
		second intervals... If they appear all at once, it means that it is not possible to turn off
		the buffering.<br><br>


<?php

	require_once __DIR__."/../../src/utils/ApacheUtil.php";

	ApacheUtil::disableBuffering();

	for ($i=1; $i<=10; $i++) {
		echo "Line $i\n<br>";
		sleep(1);
	}
