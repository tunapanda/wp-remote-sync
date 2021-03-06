<?php

require_once __DIR__."/Attachment.php";

/**
 * Represents one remote resource.
 */
class RemoteResource {

	/**
	 * Consruct.
	 * It is private, use fetchAllForType.
	 */
	private function __construct($type, $slug, $revision, $weight) {
		if (!$slug)
			throw new Exception("no slug in RemoteResource constr...");

		if (!$revision)
			throw new Exception("no revision in RemoteResource constr...");

		$this->type=$type;
		$this->slug=$slug;
		$this->revision=$revision;
		$this->data=NULL;
		$this->fetched=FALSE;
		$this->weight=$weight;
	}

	/**
	 * Get weight.
	 */
	public function getWeight() {
		return $this->weight;
	}

	/**
	 * Get slug.
	 */
	public function getSlug() {
		return $this->slug;
	}

	/**
	 * Fetch from remote.
	 */
	private function fetch() {
		if ($this->fetched)
			return;

		$this->fetched=TRUE;

		$remoteData=RemoteSyncPlugin::instance()->remoteCall("get")
			->addPostField("type",$this->type)
			->addPostField("slug",$this->slug)
			->exec();

		if (!$remoteData)
			throw new Exception("Unable to fetch remote data.");

		if ($this->revision!=md5(json_encode($remoteData["data"])))
			throw new Exception("Remote data is not the right revision");

		$this->data=$remoteData["data"];

		$this->attachments=array();
		foreach ($remoteData["attachments"] as $attachmentData) {
			$attachment=new Attachment(
				$attachmentData["fileName"],
				$attachmentData["fileSize"]
			);

			$this->attachments[]=$attachment;
		}

		$this->hasBinaryData=$remoteData["binary"];
	}

	/**
	 * Download associated binary data and store as a temporary file.
	 */
	public function downloadBinaryData() {
		$this->fetch();

		if (!$this->hasBinaryData)
			return NULL;

		$targetFileName=tempnam(sys_get_temp_dir(),"wp-remote-sync-");

		$res=RemoteSyncPlugin::instance()->remoteCall("getBinary")
			->addPostField("slug",$this->slug)
			->addPostField("type",$this->type)
			->setDownloadFileName($targetFileName)
			->exec();

		return $targetFileName;
	}

	/**
	 * Get data.
	 */
	public function getData() {
		$this->fetch();

		return $this->data;
	}

	/**
	 * Get attachments.
	 */
	public function getAttachments() {
		$this->fetch();

		return $this->attachments;
	}

	/**
	 * Get attachment by filename.
	 */
	public function getAttachmentByFileName($fileName) {
		$this->fetch();

		foreach ($this->attachments as $attachment)
			if ($attachment->getFileName()==$fileName)
				return $attachment;

		return NULL;
	}

	/**
	 * Get syncer.
	 */
	public function getSyncer() {
		return RemoteSyncPlugin::instance()->getSyncerByType($this->type);
	}

	/**
	 * Get revision
	 */
	public function getRevision() {
		return $this->revision;
	}

	/**
	 * Fetch all remote resources for type.
	 */
	public static function fetchAllForType($type) {
		$infos=RemoteSyncPlugin::instance()->remoteCall("ls")
			->addPostField("type",$type)
			->exec();

		$remoteResources=array();

		foreach ($infos as $info) {
			/*$logger=RemoteSyncPlugin::instance()->getLogger();
			if ($logger)
				$logger->log(print_r($info,TRUE));*/

			$remoteResource=new RemoteResource($type,$info["slug"],$info["revision"],$info["weight"]);
			$remoteResources[]=$remoteResource;
		}

		return $remoteResources;
	}
}