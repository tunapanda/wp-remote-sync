<?php

/**
 * Utilities for working with H5P content.
 */
class H5pUtil {

	/**
	 * Check if a H5P content exists on the system.
	 */
	function h5pExists($slug) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT id ".
			"FROM   {$wpdb->prefix}h5p_contents ".
			"WHERE  slug=%s",
			$slug);

		$res=$wpdb->get_results($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return sizeof($res)>=1;
	}

	/**
	 * Get id by slug.
	 */
	function getIdBySlug($slug) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT id ".
			"FROM   {$wpdb->prefix}h5p_contents ".
			"WHERE  slug=%s",
			$slug);

		$id=$wpdb->get_var($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		if (!$id)
			throw new Exception("H5P not found: ".$slug);

		return $id;
	}

	/**
	 * Delete a piece of H5P content.
	 */
	function deleteH5p($slug) {
		$content=array(
			"id"=>H5pUtil::getIdBySlug($slug),
			"slug"=>$slug,
		);

	    $plugin = H5P_Plugin::get_instance();
		$storage = $plugin->get_h5p_instance('storage');
		$storage->deletePackage($content);
	}

	/**
	 * Insert a H5P content to the system. If a H5P content with
	 * the same slug already exists, an error will be generated.
	 */
	function insertH5p($slug, $h5pFileName, $title) {
		global $wpdb;

		if (H5pUtil::h5pExists($slug))
			throw new Exception("H5P already exists: ".$slug);

	    $plugin=H5P_Plugin::get_instance();
	    $validator=$plugin->get_h5p_instance('validator');
	    $interface=$plugin->get_h5p_instance('interface');

		copy($h5pFileName,$interface->getUploadedH5pPath());
		$valid=$validator->isValidPackage();

		if (!$valid)
			throw new Exception("H5P content package not valid.");

		$storage = $plugin->get_h5p_instance('storage');

		$content=array(
			"title"=>$title,
			"disable"=>FALSE
		);

		$storage->savePackage($content);
		$contentId=$storage->contentId;

		if (!$contentId)
			throw new Exception("Unable to save H5P package.");

		$q=$wpdb->prepare(
			"UPDATE {$wpdb->prefix}h5p_contents ".
			"SET    slug=%s ".
			"WHERE  id=%d",
			$slug,$contentId);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		// This is done in order to create the export file.
	    $core=$plugin->get_h5p_instance('core');
	    $content=$core->loadContent($contentId);
	    $core->filterParameters($content);
	}

	/**
	 * Update a H5P content on the system. It is assumed that 
	 * a H5P content with the same slug already exists on the
	 * system to be replaced. If there is no existing content
	 * with the given slug, an error will be generated.
	 */
	function updateH5p($slug, $h5pFileName, $title) {
		if (!H5pUtil::h5pExists($slug))
			throw new Exception("H5P not found: ".$slug);

		H5pUtil::deleteH5p($slug);
		H5pUtil::insertH5p($slug,$h5pFileName,$title);
	}

	/**
	 * Save a H5P content onto the system. If a content with
	 * the same slug already exists, it will be replaced.
	 */
	function saveH5p($slug, $h5pFileName, $title) {
		if (H5pUtil::h5pExists($slug))
			H5pUtil::updateH5p($slug,$h5pFileName,$title);

		else
			H5pUtil::insertH5p($slug,$h5pFileName,$title);
	}
}
