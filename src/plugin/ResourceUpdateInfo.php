<?php

/**
 * Contains info about an updated resource. Instances of this class
 * is passed to implementers of AResourceSyncer.
 */
class ResourceUpdateInfo {

	private $create;
	private $data;
	private $binaryData;

	/**
	 * Constructor.
	 */
	public function __construct($create, $data, $binaryDataFileName) {
		$this->create=$create;
		$this->data=$data;
		$this->binaryDataFileName=$binaryDataFileName;
	}

	/**
	 * Is the resource newly created?
	 */
	public function isCreate() {
		return $this->create;
	}

	/**
	 * Get new data for the resource.
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Get filename for binary data.
	 */
	public function getBinaryDataFileName() {
		return $this->binaryDataFileName;
	}
}
