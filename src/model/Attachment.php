<?php

/**
 * Represents a resource attachment.
 */
class Attachment {

	private $fileName;
	private $fileSize;

	/**
	 * Constructor.
	 */
	public function __construct($fileName, $fileSize) {
		$this->fileName=$fileName;
		$this->fileSize=$fileSize;
	}

	/**
	 * Get file name.
	 */
	public function getFileName() {
		return $this->fileName;
	}

	/**
	 * Get file size.
	 */
	public function getFileSize() {
		return $this->fileSize;
	}
}