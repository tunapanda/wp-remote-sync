<?php

require_once __DIR__."/../../ext/smartrecord/SmartRecord.php";

/**
 * Manage one synced resouce.
 */
class SyncResource extends SmartRecord {

	/**
	 * Construct.
	 */
	public function __construct($type=NULL) {
		$this->type=$type;
		$this->dataFetched=FALSE;
	}

	/**
	 * Initialize.
	 */
	public static function initialize() {
		self::field("id","integer not null auto_increment");
		self::field("type","varchar(255) not null");
		self::field("localId","integer not null");
		self::field("globalId","varchar(255) not null");
		self::field("baseData","text");
	}

	/**
	 * Get base revision.
	 */
	public function getBaseRevision() {
		if (!$this->baseData)
			return NULL;

		return $this->getSyncer()->getResourceRevision($this->getBaseData());
	}

	/**
	 * Get revision
	 */
	public function getRevision() {
		return $this->getSyncer()->getResourceRevision($this->getData());
	}

	/**
	 * Is this locally modified?
	 */
	public function isLocallyModified() {
		return $this->getRevision()!=$this->getBaseRevision();
	}

	/**
	 * Is this a new resource?
	 */
	public function isNew() {
		if ($this->getBaseRevision())
			return FALSE;

		return TRUE;
	}

	/**
	 * Deleted?
	 */
	public function isDeleted() {
		if ($this->getData())
			return FALSE;

		return TRUE;
	}

	/**
	 * Get base data.
	 */
	public function getBaseData() {
		return json_decode($this->baseData,TRUE);
	}

	/**
	 * Set base data.
	 */
	public function setBaseData($data) {
		$this->baseData=json_encode($data);
	}

	/**
	 * Get data.
	 */
	public function getData() {
		if (!$this->dataFetched)
			$this->data=$this->getSyncer()->getResource($this->localId);

		return $this->data;
	}

	/**
	 * Get resource attachments.
	 */
	public function getResourceAttachments() {
		return $this->getSyncer()->getResourceAttachments($this->localId);
	}

	/**
	 * Get syncer.
	 */
	public function getSyncer() {
		return RemoteSyncPlugin::instance()->getSyncerByType($this->type);
	}

	/**
	 * Get label
	 */
	public function getLabel() {
		$data=$this->getData();
		return $this->getSyncer()->getResourceLabel($data);
	}
}