<?php

require_once __DIR__."/../model/SyncResource.php";
require_once __DIR__."/../../ext/merge3/DiffModule.php";

if (!class_exists("Spyc"))
	require_once __DIR__."/../../ext/spyc/Spyc.php";

/**
 * Abstract class for handling remotely syncable resources.
 */
abstract class AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct($type) {
		$this->type=$type;
	}

	/**
	 * Is the underlying resource available?
	 */
	public function isAvailable() {
		return TRUE;
	}

	/**
	 * Get type.
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * List current local resources of this resource type.
	 */
	abstract function listResourceSlugs();

	/**
	 * Fetch a local resource.
	 * Should return an array with data.
	 */
	abstract function getResource($slug);

	/**
	 * Return binary data associated with a resource.
	 */
	function getResourceBinaryData($slug) {
		return NULL;
	}

	/**
	 * Update a local resource with data.
	 * This is the most basic one.
	 */
	function updateResource($slug, $data) {
		throw new Exception("Abstract");
	}

	/**
	 * Update with binary data.
	 */
	function updateResourceWithBinaryData($slug, $data, $binaryData) {
		$this->updateResource($slug,$data);
	}

	/**
	 * Override this if you want to handle creation with binary data.
	 */
	function createResourceWithBinaryData($slug, $data, $binaryData) {
		if ($binaryData)
			$this->updateResourceWithBinaryData($slug, $data, $binaryData);

		else
			$this->createResource($slug,$data);
	}

	/**
	 * Override this if create needs to be different from update.
	 */
	function createResource($slug, $data) {
		$this->updateResource($slug,$data);
	}

	/**
	 * Delete a local resource.
	 */
	abstract function deleteResource($slug);

	/**
	 * Override this to support attached files.
	 */
	function getResourceAttachments($slug) {
		return array();
	}

	/**
	 * Get local folder where attachments for this resource should be saved.
	 */
	function getAttachmentDirectory($slug) {
		$upload_dir_info=wp_upload_dir();

		return $upload_dir_info["basedir"];
	}

	/**
	 * Return a textual "weight" that decides the
	 * order in which the resources will be listed.
	 * Useful in order to support hierarchial relationships.
	 */
	function getResourceWeight($slug) {
		return "";
	}

	/**
	 * Run install.
	 */
	function install() {
		return;
	}
}