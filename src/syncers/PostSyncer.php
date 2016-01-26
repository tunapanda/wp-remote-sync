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
	 * Get local id by slug.
	 */
	private function getIdBySlug($slug) {
		global $wpdb;

		$q=$wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_name=%s",$slug);
		$id=$wpdb->get_var($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		/*if (!$id)
			throw new Exception("no post for: ".$slug);*/

		return $id;
	}

	/**
	 * Get local id by slug.
	 */
	private function getSlugById($postId) {
		$post=get_post($postId);
		if (!$post)
			return NULL;

		return $post->post_name;
	}

	/**
	 * Get post path.
	 */
	private function getPostPath($postId) {
		if (!$postId)
			throw new Exception("getPostPath: no post id");

		$post=get_post($postId);

		if (!$post)
			throw new Exception("post not found: id=".$postId);

		if (!$post->post_parent)
			return $postId;

		return $this->getPostPath($post->post_parent)."/".$postId;
	}

	/**
	 * Get weight.
	 */
	public function getResourceWeight($slug) {
		$localId=$this->getIdBySlug($slug);
		if (!$localId)
			throw new Exception("can't find resource, slug=".$slug);
		$path=$this->getPostPath($localId);

		//echo "get res weight: $slug -- $path -- $localId\n";

		return $path;
	}

	/**
	 * List current local resources.
	 */
	public function listResourceSlugs() {
		$slugs=array();

		$q=new WP_Query(array(
			"post_type"=>"any",
			"post_status"=>"any",
			"posts_per_page"=>-1
		));
		$posts=$q->get_posts();

		foreach ($posts as $post) {
			if ($post->post_type=="page" || $post->post_type=="post")
				$slugs[]=$post->post_name;
		}

		return $slugs;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($slug) {
		$localId=$this->getIdBySlug($slug);
		$post=get_post($localId);

		if (!$post)
			return NULL;

		if ($post->post_status=="trash")
			return NULL;

		return array(
			"post_name"=>$post->post_name,
			"post_title"=>$post->post_title,
			"post_type"=>$post->post_type,
			"post_content"=>$post->post_content,
			"post_excerpt"=>$post->post_excerpt,
			"post_status"=>$post->post_status,
			"post_parent"=>$this->getSlugById($post->post_parent),
			"menu_order"=>$post->menu_order,
		);
	}

	/**
	 * Update a local resource with data.
	 */
	function updateResource($slug, $data) {
		if ($data["post_name"]!=$slug)
			throw new Exception("Sanity check failed, slug!=name");

		$localId=$this->getIdBySlug($slug);
		$post=get_post($localId);

		$post->post_name=$data["post_name"];
		$post->post_title=$data["post_title"];
		$post->post_type=$data["post_type"];
		$post->post_content=$data["post_content"];
		$post->post_excerpt=$data["post_excerpt"];
		$post->post_status=$data["post_status"];
		$post->post_parent=$this->getIdBySlug($data["post_parent"]);
		$post->menu_order=$data["menu_order"];

		wp_update_post($post);
	}

	/**
	 * Create a local resource.
	 */
	function createResource($slug, $data) {
		if ($data["post_name"]!=$slug)
			throw new Exception("Sanity check failed, slug!=name");

		return wp_insert_post(array(
			"post_name"=>$slug,
			"post_title"=>$data["post_title"],
			"post_type"=>$data["post_type"],
			"post_content"=>$data["post_content"],
			"post_excerpt"=>$data["post_excerpt"],
			"post_status"=>$data["post_status"],
			"post_parent"=>$this->getIdBySlug($data["post_parent"]),
			"menu_order"=>$data["menu_order"]
		));
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($slug) {
		$localId=$this->getIdBySlug($slug);
		wp_trash_post($localId);
	}
}