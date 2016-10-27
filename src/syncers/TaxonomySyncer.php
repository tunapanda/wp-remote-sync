<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Sync WordPress taxonomies.
 */
class TaxonomySyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("taxonomy");
	}

	/**
	 * Get weight.
	 */
	public function getResourceWeight($uniqueSlug) {
		$parts=explode(":",$uniqueSlug);
		$taxonomy=$parts[0];
		$slug=$parts[1];
		$term=get_term_by("slug",$slug,$taxonomy);

		$termIds=get_ancestors($term->term_id,$taxonomy,"taxonomy");
		$termIds=array_reverse($termIds);

		$s="";
		foreach ($termIds as $termId) {
			$term=get_term($termId,$taxonomy);
			$s.=$term->slug."/";
		}

		return $s.$slug;
	}

	/**
	 * List current local resources.
	 * We will silently skip posts without a slug (they are probably drafts (?)).
	 */
	public function listResourceSlugs() {
		global $wpdb;

		$rows=$wpdb->get_results(
			"SELECT *   ".
			"FROM      {$wpdb->prefix}term_taxonomy AS x ".
			"LEFT JOIN {$wpdb->prefix}terms as t ON x.term_id=t.term_id" 
		);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$slugs=array();
		foreach ($rows as $row) {
			$slugs[]=$row->taxonomy.":".$row->slug;
		}

		return $slugs;
	}

	/**
	 * Get post by slug.
	 */
	public function getResource($uniqueSlug) {
		global $wpdb;

		$parts=explode(":",$uniqueSlug);
		$taxonomy=$parts[0];
		$slug=$parts[1];

		$row=$wpdb->get_row($wpdb->prepare(
			"SELECT *   ".
			"FROM      {$wpdb->prefix}term_taxonomy AS x ".
			"LEFT JOIN {$wpdb->prefix}terms as t ON x.term_id=t.term_id ".
			"WHERE     taxonomy=%s ".
			"AND       slug=%s",
			$taxonomy,
			$slug
		),ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		if (!$row)
			return NULL;

		$parent=NULL;
		if ($row["parent"]) {
			$parentTerm=get_term($row["parent"],$taxonomy);
			$parent=$parentTerm->slug;
		}

		$data=array(
			"taxonomy"=>$row["taxonomy"],
			"description"=>$row["description"],
			"name"=>$row["name"],
			"slug"=>$slug,
			"parent"=>$parent
		);

		return $data;
	}

	/**
	 * Update resource.
	 */
	function updateResource($uniqueSlug, $updateInfo) {
		global $wpdb;

		$parts=explode(":",$uniqueSlug);
		$taxonomy=$parts[0];
		$slug=$parts[1];
		$data=$updateInfo->getData();

		if ($updateInfo->isCreate()) {
			$res=wp_insert_term($data["name"],$data["taxonomy"],array(
				"description"=>$data["description"],
				"slug"=>$data["slug"]
			));
			if (!$res)
				throw new Exception("Unable to insert term");

			$term=get_term($tes["term_id"]);
		}

		else {
			$term=get_term_by("slug",$slug,$taxonomy);
			if (!$term)
				throw new Exception("term not found for update");

			wp_update_term($term->term_id,$taxonomy,array(
				"name"=>$data["name"],
				"description"=>$data["description"]
			));
		}

		$parentId=NULL;
		if ($data["parent"]) {
			$parentTerm=get_term_by("slug",$data["parent"],$taxonomy);
			$parentId=$parentTerm->term_id;
		}

		wp_update_term($term->term_id,$taxonomy,array(
			"parent"=>$parentId
		));
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($uniqueSlug) {
		$parts=explode(":",$uniqueSlug);
		$taxonomy=$parts[0];
		$slug=$parts[1];
		$term=get_term_by("slug",$slug,$taxonomy);
		wp_delete_term($term->term_id,$taxonomy);
	}
}