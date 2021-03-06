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
	 * Is this resource syncable by the local system?
	 */
	public function isResourceSyncable($uniqueSlug) {
		$parts=explode(":",$uniqueSlug);
		$taxonomy=$parts[0];
		$taxonomies=array_keys(get_taxonomies());

		if ($taxonomy=="nav_menu")
			return FALSE;

		if (in_array($taxonomy,$taxonomies))
			return TRUE;

		return FALSE;
	}

	/**
	 * Set meta from a structured array.
	 */
	public static function setTermMeta($termId, $newMeta) {
		$oldMeta=TaxonomySyncer::getStructuredTermMeta($termId);

		foreach ($oldMeta as $old) {
			$keep=FALSE;

			foreach ($newMeta as $new)
				if ($old["key"]==$new["key"] && $old["value"]==$new["value"])
					$keep=TRUE;

			if (!$keep)
				delete_term_meta($termId,$old["key"],$old["value"]);
		}

		foreach ($newMeta as $new) {
			$already=FALSE;

			foreach ($oldMeta as $old)
				if ($old["key"]==$new["key"] && $old["value"]==$new["value"])
					$already=TRUE;

			if (!$already)
				add_term_meta($termId,$new["key"],$new["value"]);
		}
	}

	/**
	 * Get term meta.
	 */
	public static function getStructuredTermMeta($termId) {
		$meta=get_term_meta($termId);
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
			"parent"=>$parent,
			"meta"=>TaxonomySyncer::getStructuredTermMeta($row["term_id"]),
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

		$term=get_term_by("slug",$slug,$taxonomy);
		if (!$term) {
			$res=wp_insert_term($data["name"],$data["taxonomy"],array(
				"slug"=>$data["slug"]
			));
			if (!$res || $res instanceof \WP_Error)
				throw new Exception("Unable to insert term");

			$term=get_term($res["term_id"]);
		}

		wp_update_term($term->term_id,$taxonomy,array(
			"name"=>$data["name"],
			"description"=>$data["description"]
		));

		$parentId=NULL;
		if ($data["parent"]) {
			$parentTerm=get_term_by("slug",$data["parent"],$taxonomy);
			if ($parentTerm)
				$parentId=$parentTerm->term_id;
		}

		wp_update_term($term->term_id,$taxonomy,array(
			"parent"=>$parentId
		));

		TaxonomySyncer::setTermMeta($term->term_id,$data["meta"]);
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