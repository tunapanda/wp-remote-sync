<?php

/**
 * Flush to browser and make as much effort as possible to turn off
 * output buffering. Initiates a long running operation.
 */
function rsJobStart() {
	// Turn off output buffering
	ini_set('output_buffering', 'off');
	// Turn off PHP output compression
	if (!headers_sent())
		ini_set('zlib.output_compression', false);

	//Flush (send) the output buffer and turn off output buffering
	//ob_end_flush();
	while (@ob_end_flush());
         
	// Implicitly flush the buffer(s)
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);

	//prevent apache from buffering it for deflate/gzip
	if (!headers_sent()) {
		header("Content-type: text/plain");
		header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
	}

	for($i = 0; $i < 1000; $i++) {
	    echo ' ';
	}

	ob_flush();
	flush();
}

/**
 * Log a line for a job.
 */
function rsJobLog($message) {
	echo "<pre>";
	echo $message;
	echo "</pre>";
	ob_flush();
	flush();
}

/**
 * Job done.
 */
function rsJobDone() {
	$link=get_site_url()."/wp-admin/options-general.php?page=rs_settings";

	echo "<hr/>";
	echo "<a href='$link' class='button'>Back</a>";
}

/**
 * Make a call to the api on the remote site.
 */
function rsRemoteCall($method, $args=array()) {
	//rsJobLog("Calling remote: ".$method);

	$args["action"]=$method;

	$url=get_option("rs_remote_site_url");
	if (!trim($url))
		throw new Exception("Remote site url not set");

	$url.="/wp-content/plugins/wp-remote-sync/api.php";
	$url.="?".http_build_query($args);

	//rsJobLog($url);

	$curl=curl_init($url);
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	$res=curl_exec($curl);
	$returnCode=curl_getinfo($curl,CURLINFO_HTTP_CODE);

	if ($returnCode!=200)
		throw new Exception("Unexpected return code: ".$returnCode."\n".$res);

	$res=json_decode($res,TRUE);

	if (!$res)
		throw new Exception("Unable to parse json... ".$res);

	return $res;
}