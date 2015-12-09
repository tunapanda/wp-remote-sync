<?php

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
	 * Notify the system that a resource has been locally changed.
	 */
	public final function notifyLocalChange($localId) {

	}

	/**
	 * List current local resources of this resource type.
	 */
	abstract function listResourceIds();

	/**
	 * Fetch a local resource.
	 * Should return an array with data.
	 */
	abstract function getResource($localId);

	/**
	 * Update a local resource with data.
	 */
	abstract function updateResource($localId, $data);

	/**
	 * Create a local resource.
	 */
	abstract function createResource($data);

	/**
	 * Delete a local resource.
	 */
	abstract function deleteResource($localId);

	/**
	 * Merge resource data.
	 */
	abstract function mergeResourceData($base, $local, $remote);
}