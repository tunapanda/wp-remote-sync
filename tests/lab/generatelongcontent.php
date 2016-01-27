<?php

	header("Content-length: 100000");

	if (!headers_sent())
		ini_set('zlib.output_compression', false);

	while (@ob_end_flush());
	     
	// Implicitly flush the buffer(s)
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);

	if (!headers_sent()) {
		header("Content-type: text/plain");
		header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
	}
	ob_flush();
	flush();

	for ($i=0; $i<100; $i++) {
		$s="";

		for ($j=0; $j<1000; $j++)
			$s.="X";

		echo $s;
		ob_flush();
		flush();
		usleep(100000);
	}