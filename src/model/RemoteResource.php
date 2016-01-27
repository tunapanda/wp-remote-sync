<?php

require_once __DIR__."/Attachment.php";

/**
 * Represents one remote resource.
 */
class RemoteResource {

	/**
	 * Consruct
	 */
	public function __construct($type, $slug, $revision) {
		if (!$slug)
			throw new Exception("no slug in RemoteResource constr...");

		if (!$revision)
			throw new Exception("no revision in RemoteResource constr...");

		$this->type=$type;
		$this->slug=$slug;
		$this->revision=$revision;
		$this->data=NULL;
		$this->fetched=FALSE;
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

		$remoteResources=[];

		foreach ($infos as $info) {
			//print_r($info);
			$remoteResource=new RemoteResource($type,$info["slug"],$info["revision"]);
			$remoteResources[]=$remoteResource;
		}

		return $remoteResources;
	}
}