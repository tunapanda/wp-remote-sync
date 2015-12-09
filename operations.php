<?php

require_once __DIR__."/utils.php";
require_once __DIR__."/ext/merge3/DiffModule.php";

function rsMerge($base, $local, $remote) {
	$base=explode("\n",$base);
	$local=explode("\n",$local);
	$remote=explode("\n",$remote);

	$diff=new DiffModule();
	$mergeRecords=$diff->merge3($base,$local,$remote);
	$res="";

	foreach ($mergeRecords as $mergeRecord) {
		switch ($mergeRecord[0]) {
			case 'orig':
			case 'addright':
			case 'addleft':
				$res.=$mergeRecord[1]."\n";
				break;

			case "delleft":
			case "delright":
			case "delboth":
				break;

			case "conflict":
				if (get_option("rs_merge_strategy")=="prioritize_local")
					$res.=$mergeRecord[1]."\n";

				else
					$res.=$mergeRecord[2]."\n";
				break;

			default:
				print_r($mergeRecords);
				throw new Exception("Merge conflict: ".$mergeRecord[0]);
				break;
		}
	}

	return $res;
}

function rsPush() {
	rsJobLog("Pushing local changes...");

	$q=new WP_Query(array(
		"post_type"=>"any",
		"post_status"=>"any"
	));

	$posts=$q->get_posts();

	foreach ($posts as $post) {
		$rsId=get_post_meta($post->ID,"_rs_id",TRUE);
		$rsRev=get_post_meta($post->ID,"_rs_rev",TRUE);
		$rsBaseRev=get_post_meta($post->ID,"_rs_base_rev",TRUE);

		if (!$rsId)
			throw new Exception("Post doesn't have an id: ".$post->post_title);

		// New
		if (!$rsBaseRev) {
			rsJobLog("* A ".$rsId." ".$post->ID." ".$post->post_title);
		}

		else if ($rsRev!=$rsBaseRev) {
			rsRemoteCall("putpost",array(
				"_rs_id"=>$rsId,
				"_rs_rev"=>$rsRev,
				"_rs_base_rev"=>$rsBaseRev,
				"post_content"=>$post->post_content
			));

			update_post_meta($post->ID,"_rs_base_rev",$rsRev);
			update_post_meta($post->ID,"_rs_base_post_content",$post->post_content);

			rsJobLog("* U ".$rsId." ".$post->ID." ".$post->post_title);
		}
	}
}

function rsPull() {
	rsJobLog("Pulling remote changes...");

	$remoteInfos=rsRemoteCall("list");

	rsJobLog("The remote site has ".sizeof($remoteInfos)." post(s).");

	foreach ($remoteInfos as $remoteInfo) {
		if (!$remoteInfo["_rs_id"])
			throw new Exception("The remote content doesn't have an id.");

		$q=new WP_Query(array(
			"meta_key"=>"_rs_id",
			"meta_value"=>$remoteInfo["_rs_id"],
			"post_type"=>"any",
			"post_status"=>"any"
		));

		// Exists locally.
		if ($q->have_posts()) {
			$posts=$q->get_posts();
			if (sizeof($posts)!=1)
				throw new Exception("Expected 1 post.");

			$post=$posts[0];
			$localRev=get_post_meta($post->ID,"_rs_rev",TRUE);
			$localBaseRev=get_post_meta($post->ID,"_rs_base_rev",TRUE);

			// Not remotely changed.
			if ($remoteInfo["_rs_rev"]==$localBaseRev) {
				rsJobLog("* - ".$remoteInfo["_rs_id"]." ".$post->ID." ".$post->post_title);
			}

			// Remotely changed.
			else {
				$remotePost=rsRemoteCall("getpost",array(
					"_rs_id"=>$remoteInfo["_rs_id"]
				));

				// No local changes
				if ($localRev==$localBaseRev) {
					$post->post_content=$remotePost["post_content"];
					wp_update_post($post);
					rsJobLog("* U ".$remoteInfo["_rs_id"]." ".$post->ID." ".$post->post_title);

					update_post_meta($post->ID,"_rs_rev",$remotePost["_rs_rev"]);
					update_post_meta($post->ID,"_rs_base_rev",$remotePost["_rs_rev"]);
					update_post_meta($post->ID,"_rs_base_post_content",$remotePost["post_content"]);
				}

				// Merge
				else {
					$base=get_post_meta($post->ID,"_rs_base_post_content",TRUE);
					$merged=rsMerge($base,$post->post_content,$remotePost["post_content"]);
					$post->post_content=$merged;
					wp_update_post($post);

					update_post_meta($post->ID,"_rs_rev",uniqid());
					update_post_meta($post->ID,"_rs_base_rev",$remotePost["_rs_rev"]);
					update_post_meta($post->ID,"_rs_base_post_content",$remotePost["post_content"]);

					rsJobLog("* M ".$remoteInfo["_rs_id"]." ".$post->ID." ".$post->post_title);
				}
			}
		}

		// Doesn't exists locally.
		else {
			$remotePost=rsRemoteCall("getpost",array(
				"_rs_id"=>$remoteInfo["_rs_id"]
			));

			if (!$remotePost)
				throw new Exception("Unable to fetch remote content");

			$id=wp_insert_post(array(
				"post_title"=>$remotePost["post_title"],
				"post_content"=>$remotePost["post_content"],
				"post_type"=>$remotePost["post_type"]
			),TRUE);

			if (is_wp_error($id))
				throw new Exception("Unable to create local content: ".$err->get_error_message());

			update_post_meta($id,"_rs_id",$remotePost["_rs_id"]);
			update_post_meta($id,"_rs_rev",$remotePost["_rs_rev"]);
			update_post_meta($id,"_rs_base_rev",$remotePost["_rs_rev"]);
			update_post_meta($id,"_rs_base_post_content",$remotePost["post_content"]);

			rsJobLog("* A ".$remotePost["_rs_id"]." ".$id." ".$remotePost["post_title"]);
		}
	}
}