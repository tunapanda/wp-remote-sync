<?php

require_once __DIR__."/src/WpUtil.php";

use remotesync\WpUtil;

require_once WpUtil::getWpLoadPath();

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

	default:
		echo "unknown api action";
		return;
}

