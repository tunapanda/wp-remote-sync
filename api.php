<?php

require_once __DIR__."/src/utils/WpUtil.php";
require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";

use remotesync\WpUtil;

require_once WpUtil::getWpLoadPath();

require_once ABSPATH.'wp-admin/includes/plugin.php';

header('Content-Type: application/json');
if (!$_REQUEST) {
	http_response_code(500);
	echo json_encode(array(
		"error"=>TRUE,
		"message"=>
			"Got no request data, you might need to increase post_max_size and/or upload_max_filesize. ".
			"post_max_size=".ini_get("post_max_size")." upload_max_filesize=".ini_get("upload_max_filesize")

	));
	exit();
}

$plugin=new RemoteSyncPlugin();
$plugin->getApi()->handleApiCall($_REQUEST["action"],$_REQUEST);
