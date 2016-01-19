<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Sync wordpress attachments.
 */
class AttachmentSyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("attachment");
	}

	/**
	 * List current local resources.
	 */
	public function listResourceIds() {
		$ids=array();

		$q=new WP_Query(array(
			"post_type"=>"attachment",
			"post_status"=>"any",
			"posts_per_page"=>-1
		));
		$posts=$q->get_posts();

		foreach ($posts as $post) {
			$ids[]=$post->ID;
		}

		return $ids;
	}

	/**
	 * Get resource attachment.
	 */
	public function getResourceAttachments($localId) {
		$res=array();

		$res[]=get_post_meta($localId,"_wp_attached_file",TRUE);
		$meta=get_post_meta($localId,"_wp_attachment_metadata",TRUE);

		if (isset($meta["file"]))
			$res[]=$meta["file"];

		if (isset($meta["file"]) && isset($meta["file"]) && $meta["sizes"]){
			foreach ($meta["sizes"] as $type=>$size)
				$res[]=dirname($meta["file"])."/".$size["file"];
		}
		
		return $res;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($localId) {
		$post=get_post($localId);

		if (!$post)
			return NULL;

		if ($post->post_status=="trash")
			return NULL;

		return array(
			"guid"=>$post->guid,
			"post_title"=>$post->post_title,
			"post_mime_type"=>$post->post_mime_type,
			"_wp_attached_file"=>get_post_meta($localId,"_wp_attached_file",TRUE),
			"_wp_attachment_metadata"=>get_post_meta($localId,"_wp_attachment_metadata",TRUE)
		);
	}

	/**
	 * Update a local resource with data.
	 */
	function updateResource($localId, $data) {
		$post=get_post($localId);

		$post->guid=$data["guid"];
		$post->post_title=$data["post_title"];
		$post->post_mime_type=$data["post_mime_type"];
		wp_update_post($post);

		update_post_meta($localId,"_wp_attached_file",$data["_wp_attached_file"]);
		update_post_meta($localId,"_wp_attachment_metadata",$data["_wp_attachment_metadata"]);
	}

	/**
	 * Create a local resource.
	 */
	function createResource($data) {
		$id=wp_insert_post(array(
			"guid"=>$data["guid"],
			"post_title"=>$data["post_title"],
			"post_mime_type"=>$data["post_mime_type"],
			"post_type"=>"attachment"
		));

		update_post_meta($id,"_wp_attached_file",$data["_wp_attached_file"]);
		update_post_meta($id,"_wp_attachment_metadata",$data["_wp_attachment_metadata"]);

		return $id;
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($localId) {
		wp_delete_post($localId);
	}

	/**
	 * Merge resource data.
	 */
	function mergeResourceData($base, $local, $remote) {
		return array(
			"post_guid"=>$this->pickKeyValues("post_guid",$base,$local,$remote),
			"post_title"=>$this->pickKeyValues("post_title",$base,$local,$remote),
			"post_mime_type"=>$this->pickKeyValues("post_mime_type",$base,$local,$remote),
			"_wp_attached_file"=>$this->pickKeyValue("_wp_attached_file",$base,$local,$remote),
			"_wp_attachment_metadata"=>$this->pickKeyValue("_wp_attachment_metadata",$base,$local,$remote)
		);
	}

	/**
	 * Get sync label from data.
	 */
	function getResourceLabel($data) {
		return $data["post_title"];
	}
}