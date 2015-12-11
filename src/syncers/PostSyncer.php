<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Sync wordpress posts.
 */
class PostSyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("post");
	}

	/**
	 * List current local resources.
	 */
	public function listResourceIds() {
		$ids=array();

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"posts_per_page"=>-1
		));
		$posts=$q->get_posts();

		foreach ($posts as $post)
			$ids[]=$post->ID;

		return $ids;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($localId) {
		$post=get_post($localId);

		if ($post->post_status=="trash")
			return NULL;

		return array(
			"post_title"=>$post->post_title,
			"post_content"=>$post->post_content,
			"post_type"=>$post->post_type
		);
	}

	/**
	 * Update a local resource with data.
	 */
	function updateResource($localId, $data) {
		$post=get_post($localId);

		$post->post_title=$data["post_title"];
		$post->post_content=$data["post_content"];
		$post->post_type=$data["post_type"];

		wp_update_post($post);
	}

	/**
	 * Create a local resource.
	 */
	function createResource($data) {
		return wp_insert_post(array(
			"post_content"=>$data["post_content"],
			"post_title"=>$data["post_title"],
			"post_type"=>$data["post_type"]
		));
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($localId) {
		wp_trash_post($localId);
	}

	/**
	 * Merge key values from objects.
	 */
	function mergeKeyValues($key, $base, $local, $remote) {
		return $this->merge3($base[$key],$local[$key],$remote[$key]);
	}

	/**
	 * Merge resource data.
	 */
	function mergeResourceData($base, $local, $remote) {
		/*print_r($base);
		print_r($local);
		print_r($remote);*/

		return array(
			"post_content"=>$this->mergeKeyValues("post_content",$base,$local,$remote),
			"post_title"=>$this->mergeKeyValues("post_title",$base,$local,$remote),
			"post_type"=>$this->mergeKeyValues("post_type",$base,$local,$remote)
		);
	}

	/**
	 * Get sync label from data.
	 */
	function getResourceLabel($data) {
		return $data["post_title"];
	}
}