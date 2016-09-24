<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Pluggable syncer.
 */
class PluggableSyncer extends AResourceSyncer {

	/**
	 * Consruct.
	 */
	public function __construct($impl) {
		$this->impl=$impl;
	}

	/**
	 * Get type.
	 */
	public function getType() {
		$name=get_class($this->impl);
		$name=str_replace("\\","-",$name);

		return $name;
	}

	/**
	 * Get resource slugs.
	 */
	public function listResourceSlugs() {
		return $this->impl->listResourceSlugs();
	}

	/**
	 * Get resource.
	 */
	public function getResource($slug) {
		return $this->impl->getResource($slug);
	}

	/**
	 * Update resource.
	 */
	public function updateResource($slug, $updateInfo) {
		return $this->impl->updateResource($slug,$updateInfo);
	}

	/**
	 * Update resource.
	 */
	public function deleteResource($slug) {
		return $this->impl->deleteResource($slug);
	}

	/**
	 * Attachments,
	 */
	function getResourceAttachments($slug) {
		if (!method_exists($this->impl,"getResourceAttachments"))
			return array();

		return $this->impl->getResourceAttachments($slug);
	}

	/**
	 * Directory.
	 */
	function getAttachmentDirectory($slug) {
		if (!method_exists($this->impl,"getAttachmentDirectory")) {
			$upload_dir_info=wp_upload_dir();
			return $upload_dir_info["basedir"];
		}

		return $this->impl->getAttachmentDirectory($slug);
	}

	/**
	 * Weight
	 */
	function getResourceWeight($slug) {
		if (!method_exists($this->impl,"getResourceWeight"))
			return "";

		return $this->impl->getResourceWeight($slug);
	}
}