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
	 * Update a local resource with data.
	 */
	abstract function updateResource($slug, $data);

	/**
	 * Overrid this is create needs to be different
	 * from update.
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
		return wp_upload_dir()["basedir"];
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