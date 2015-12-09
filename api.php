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
		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"posts_per_page"=>-1
		));
		$pages=$q->get_posts();
		$res=array();

		foreach ($pages as $page) {
			$info=array(
				"_rs_id"=>get_post_meta($page->ID,"_rs_id",TRUE),
				"_rs_rev"=>get_post_meta($page->ID,"_rs_rev",TRUE)
			);

			if (!$info["_rs_id"] || !$info["_rs_rev"])
				throw new Exception("Something is wrong, id or rev missing from post.");

			$res[]=$info;
		}

		echo json_encode($res);
		break;

	case "getpost":
		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"meta_key"=>"_rs_id",
			"meta_value"=>$_REQUEST["_rs_id"],
		));

		$posts=$q->get_posts();
		if (sizeof($posts)!=1)
			throw new Exception("Expected 1 post");

		$post=$posts[0];

		$res=array(
			"post_title"=>$post->post_title,
			"post_content"=>$post->post_content,
			"post_type"=>$post->post_type,
			"_rs_id"=>get_post_meta($post->ID,"_rs_id",TRUE),
			"_rs_rev"=>get_post_meta($post->ID,"_rs_rev",TRUE)
		);
		echo json_encode($res);
		break;

	case "addpost":
		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"meta_key"=>"_rs_id",
			"meta_value"=>$_REQUEST["_rs_id"],
		));

		$posts=$q->get_posts();
		if (sizeof($posts))
			throw new Exception("Post already exists");

		if (!$_REQUEST["_rs_id"])
			throw new Exception("Expected id");

		if (!$_REQUEST["_rs_rev"])
			throw new Exception("Expected revision");

		$id=wp_insert_post(array(
			"post_title"=>stripslashes($_REQUEST["post_title"]),
			"post_content"=>stripslashes($_REQUEST["post_content"]),
			"post_type"=>"page"
		));

		if (!$id)
			throw new Exception("Unable to create post");

		update_post_meta($id,"_rs_id",$_REQUEST["_rs_id"]);
		update_post_meta($id,"_rs_rev",$_REQUEST["_rs_rev"]);
		echo json_encode(array(
			"ok"=>1
		));
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

		if (!$_REQUEST["_rs_base_rev"])
			throw new Exception("Expected base revision");

		$post=$posts[0];
		$currentRev=get_post_meta($post->ID,"_rs_rev",TRUE);
		if ($currentRev!=$_REQUEST["_rs_base_rev"])
			throw new Exception("Not up to date, merge first, my rev=".$currentRev);

		$post->post_content=stripslashes($_REQUEST["post_content"]);
		wp_update_post($post);
		update_post_meta($post->ID,"_rs_rev",$_REQUEST["_rs_rev"]);
		echo json_encode(array(
			"ok"=>1
		));
		break;

	default:
		echo "unknown api action";
		return;
}
