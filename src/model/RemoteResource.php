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
	}

	/**
	 * Get data.
	 */
	public function getData() {
		if (!$this->fetched) {
			$this->fetched=true;

			$getData=RemoteSyncPlugin::instance()->remoteCall("get",array(
				"type"=>$this->type,
				"globalId"=>$this->globalId
			));

			if (!$getData)
				throw new Exception("Unable to fetch remote data.");

			$this->data=$getData["data"];
		}

		return $this->data;
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