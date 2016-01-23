<?php

/**
 * Represents one remote resource.
 */
class RemoteResource {

	/**
	 * Consruct
	 */
	public function __construct($type, $globalId, $revision) {
		$this->type=$type;
		$this->globalId=$globalId;
		$this->revision=$revision;
		$this->data=NULL;
		$this->fetched=FALSE;

		if (!$globalId)
			throw new Exception("no globalId in RemoteResource constr...");
	}

	/**
	 * Get global id.
	 */
	public function getGlobalId() {
		return $this->globalId;
	}

	/**
	 * Fetch from remote.
	 */
	private function fetch() {
		$this->fetched=TRUE;

		$remoteData=RemoteSyncPlugin::instance()->remoteCall("get",array(
			"type"=>$this->type,
			"globalId"=>$this->globalId
		));

		if (!$remoteData)
			throw new Exception("Unable to fetch remote data.");

		$this->data=$remoteData["data"];
		$this->attachments=$remoteData["attachments"];
	}

	/**
	 * Get data.
	 */
	public function getData() {
		if (!$this->fetched)
			$this->fetch();

		return $this->data;
	}

	/**
	 * Get attachments.
	 */
	public function getAttachments() {
		if (!$this->fetched)
			$this->fetch();

		return $this->attachments;
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

	/**
	 * Get revision
	 */
	public function getRevision() {
		return $this->revision;
	}
}