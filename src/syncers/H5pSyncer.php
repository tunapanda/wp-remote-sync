<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";
require_once __DIR__."/../utils/H5pUtil.php";

/**
 * Sync wordpress posts.
 */
class H5pSyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("h5p");
	}

	/**
	 * Is the underlying resource available?
	 */
	public function isAvailable() {
		return is_plugin_active("h5p/h5p.php");
	}

	/**
	 * List current local resources.
	 */
	public function listResourceSlugs() {
		global $wpdb;

		$slugs=$wpdb->get_col("SELECT slug FROM {$wpdb->prefix}h5p_contents");

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$res=array();
		foreach ($slugs as $slug)
			if ($slug)
				$res[]=$slug;

		return $res;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($slug) {
		if (!H5pUtil::h5pExists($slug))
			return NULL;

		$localId=H5pUtil::getIdBySlug($slug);
		global $wpdb;

		$q=$wpdb->prepare("SELECT * FROM {$wpdb->prefix}h5p_contents WHERE id=%s",$localId);
		$h5p=$wpdb->get_row($q,ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		if (!$h5p)
			return NULL;

		return array(
			"title"=>$h5p["title"],
			"parameters"=>$h5p["parameters"],
			"filtered"=>$h5p["filtered"],
			"slug"=>$h5p["slug"],
			"embed_type"=>$h5p["embed_type"],
			"disable"=>strval($h5p["disable"]),
			"content_type"=>$h5p["content_type"]?$h5p["content_type"]:"",
			"keywords"=>$h5p["keywords"]?$h5p["keywords"]:"",
			"description"=>$h5p["description"]?$h5p["description"]:"",
			"license"=>$h5p["license"]?$h5p["license"]:"",
			"library"=>H5pUtil::getLibraryNameById($h5p["library_id"]),
		);
	}

	/**
	 * Get resource data.
	 */
	public function getResourceBinaryData($slug) {
		$localId=H5pUtil::getIdBySlug($slug);
		$upload_dir_info=wp_upload_dir();
		return $upload_dir_info["basedir"]."/h5p/exports/".$slug."-".$localId.".h5p";
	}

	/**
	 * Update a local resource with data.
	 */
	public function updateResource($slug, $updateInfo) {
		$data=$updateInfo->getData();
		$binaryDataFileName=$updateInfo->getBinaryDataFileName();

		//error_log("update with binary in h5p: ".$binaryDataFileName);
		if ($updateInfo->isCreate())
			H5pUtil::insertH5p($slug,$binaryDataFileName,$data["title"]);

		else
			H5pUtil::updateH5p($slug,$binaryDataFileName,$data["title"]);
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($slug) {
		H5pUtil::deleteH5p($slug);
	}
}