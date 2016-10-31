<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";
require_once __DIR__."/../utils/WpUtil.php";

use remotesync\WpUtil;

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
	 * Set meta from a structured array.
	 */
	public static function setPostMeta($postId, $newMeta) {
		$oldMeta=PostSyncer::getStructuredPostMeta($postId);

		foreach ($oldMeta as $old) {
			$keep=FALSE;

			foreach ($newMeta as $new)
				if ($old["key"]==$new["key"] && $old["value"]==$new["value"])
					$keep=TRUE;

			if (!$keep)
				delete_post_meta($postId,$old["key"],$old["value"]);
		}

		foreach ($newMeta as $new) {
			$already=FALSE;

			foreach ($oldMeta as $old)
				if ($old["key"]==$new["key"] && $old["value"]==$new["value"])
					$already=TRUE;

			if (!$already)
				add_post_meta($postId,$new["key"],$new["value"]);
		}
	}

	/**
	 * Set post meta.
	 */
	public static function getStructuredPostMeta($postId) {
		$meta=get_post_meta($postId);
		$structuredMeta=array();

		foreach ($meta as $key=>$value) {
			if ($key[0]!="_" && $key) {
				if (is_array($value)) {
					foreach ($value as $singleValue)
						$structuredMeta[]=array(
							"key"=>$key,
							"value"=>$singleValue
						);
				}

				else {
					$structuredMeta[]=array(
						"key"=>$key,
						"value"=>$value
					);
				}
			}
		}

		usort($structuredMeta, function($a,$b) {
			$v=strcmp($a["key"],$b["key"]);
			if ($v)
				return $v;

			return strcmp($a["value"],$b["value"]);
		});

		return $structuredMeta;
	}

	/**
	 * Get local id by slug.
	 */
	private function getIdBySlug($slug) {
		global $wpdb;

		if (!$slug)
			return 0;

		$q=$wpdb->prepare(
			"SELECT ID ".
			"FROM   {$wpdb->prefix}posts ".
			"WHERE  post_name=%s ".
			"AND    post_type IN ('post','page') ".
			"AND    post_status NOT IN ('trash','inherit')",
			$slug);
		$id=$wpdb->get_var($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $id;
	}

	/**
	 * Get local id by slug.
	 */
	private function getSlugById($postId) {
		$post=get_post($postId);
		if (!$post)
			return NULL;

		if ($post->post_type!="page" && $post->post_type!="post")
			throw new Exception("Expected type to be post or page, not: ".$post->post_type);

		if ($post->post_status=="trash" || $post->post_status=="inherit")
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
			return $this->getSlugById($postId);

		return $this->getPostPath($post->post_parent)."/".$this->getSlugById($postId);
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
	 * We will silently skip posts without a slug (they are probably drafts (?)).
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
			if ($post->post_type=="page" || $post->post_type=="post") {
				if ($post->post_name && !in_array($post->post_status,array("trash","inherit"))) {
					//echo $post->post_name." ".$post->post_status."\n";

					$slugs[]=$post->post_name;
				}
			}
		}

		return $slugs;
	}

	/**
	 * Get post by slug.
	 */
	public function getResource($slug) {
		$localId=$this->getIdBySlug($slug);
		$post=get_post($localId);

		if (!$post)
			return NULL;

		if ($post->post_status=="trash" || $post->post_status=="inherit")
			return NULL;

		$parentSlug=$this->getSlugById($post->post_parent);
		if (!$parentSlug)
			$parentSlug="";

		$terms=wp_get_object_terms(
			$localId,
			WpUtil::getTaxonomiesByPostType("post")
		);

		$termSlugs=array();
		foreach ($terms as $term) {
			if (isset($termSlugs[$term->taxonomy]))
				$termSlugs[$term->taxonomy]=array();

			$termSlugs[$term->taxonomy][]=$term->slug;
			sort($termSlugs[$term->taxonomy]);
		}

		ksort($termSlugs);

		$data=array(
			"post_name"=>$post->post_name,
			"post_title"=>$post->post_title,
			"post_type"=>$post->post_type,
			"post_content"=>$post->post_content,
			"post_excerpt"=>$post->post_excerpt,
			"post_status"=>$post->post_status,
			"post_parent"=>$parentSlug,
			"menu_order"=>$post->menu_order,
			"meta"=>PostSyncer::getStructuredPostMeta($localId),
			"terms"=>$termSlugs
		);

		return $data;
	}

	/**
	 * Update resource.
	 */
	function updateResource($slug, $updateInfo) {
		global $wpdb;

		$data=$updateInfo->getData();

		if ($data["post_name"]!=$slug)
			throw new Exception("Sanity check failed, slug!=name");

		if (!$slug)
			throw new Exception("Tried to create or update post with empty slug!");

		if ($updateInfo->isCreate()) {
			// Check if it exists in trash, if so delete permanently,
			// because we need the slug to be free.
			$q=$wpdb->prepare(
				"SELECT * ".
				"FROM   {$wpdb->prefix}posts ".
				"WHERE  post_name=%s ".
				"AND    post_type IN ('post','page')",
				$slug
			);

			$row=$wpdb->get_row($q);
			if ($wpdb->last_error)
				throw new Exception($wpdb->last_error);

			if ($row) {
				if ($row->post_status=="trash" &&
					($row->post_type=="attachment"))
					wp_delete_post($row->ID,TRUE);

				else
					throw new Exception("Slug not free, status=".$row->post_status);
			}

			$localId=wp_insert_post(array(
				"post_name"=>$slug,
				"post_title"=>$data["post_title"],
				"post_type"=>$data["post_type"],
				"post_content"=>$data["post_content"],
				"post_excerpt"=>$data["post_excerpt"],
				"post_status"=>$data["post_status"],
				"post_parent"=>$this->getIdBySlug($data["post_parent"]),
				"menu_order"=>$data["menu_order"]
			));

			PostSyncer::setPostMeta($localId,$data["meta"]);

			$post=get_post($localId);

			if ($post->post_name!=$slug)
				throw new Exception("Slug changed when saving: original: ".$slug);
		}

		else {
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

			PostSyncer::setPostMeta($localId,$data["meta"]);

			wp_update_post($post);
		}

		$taxonomies=WpUtil::getTaxonomiesByPostType("post");
		foreach ($taxonomies as $taxonomy) {
			if (isset($data["terms"][$taxonomy]))
				wp_set_object_terms($localId,$data["terms"][$taxonomy],$taxonomy);

			else
				wp_set_object_terms($localId,array(),$taxonomy);
		}
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($slug) {
		$localId=$this->getIdBySlug($slug);
		wp_delete_post($localId,TRUE);
	}
}