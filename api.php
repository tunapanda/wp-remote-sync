<?php

require_once __DIR__."/src/WpUtil.php";

use remotesync\WpUtil;

require_once WpUtil::getWpLoadPath();

function handleException($exception) {
	$res=array(
		"error"=>TRUE,
		"message"=>$exception->getMessage()
	);

	http_response_code(500);
	echo json_encode($res);
}

set_exception_handler("handleException");

switch ($_REQUEST["action"]) {
	case "list":
		$pages=get_pages();
		$res=array();

		foreach ($pages as $page) {
			$res[]=array(
				"id"=>get_post_meta($page->ID,"_rs_id",TRUE),
				"rev"=>get_post_meta($page->ID,"_rs_rev",TRUE)
			);
		}

		echo json_encode($res);
		break;

	case "getpost":
		$posts=get_posts(array(
			"meta_key"=>"_rs_id",
			"meta_value"=>$_REQUEST["id"],
			"post_type"=>"page"
		));
		if (sizeof($posts)!=1)
			throw new Exception("Expected 1 post");

		$post=$posts[0];

		$res=array(
			"title"=>$post->post_title,
			"content"=>$post->post_content,
			"_rs_id"=>get_post_meta($post->ID,"_rs_id",TRUE),
			"_rs_rev"=>get_post_meta($post->ID,"_rs_rev",TRUE)
		);
		echo json_encode($res);
		break;

	case "putpost":
		$posts=get_posts(array(
			"meta_key"=>"_rs_id",
			"meta_value"=>$_REQUEST["_rs_id"],
			"post_type"=>"page"
		));

		if (sizeof($posts)!=1)
			throw new Exception("Expected 1 post");

		if (!$_REQUEST["_rs_rev"])
			throw new Exception("Expected revision");

		if (!$_REQUEST["_rs_baserev"])
			throw new Exception("Expected base revision");

		$post=$posts[0];
		$currentRev=get_post_meta($post->ID,"_rs_rev",TRUE);
		if ($currentRev!=$_REQUEST["_rs_baserev"])
			throw new Exception("Not up to date, merge first, my rev=".$currentRev);

		echo "implement me!";
		break;

	default:
		echo "unknown api action";
		return;
}

