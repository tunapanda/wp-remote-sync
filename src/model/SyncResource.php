<?php

require_once __DIR__."/../../ext/smartrecord/SmartRecord.php";
require_once __DIR__."/RemoteResource.php";
require_once __DIR__."/../plugin/ResourceUpdateInfo.php";

/**
 * Manage one synced resouce.
 */
class SyncResource extends SmartRecord {

	const POPULATE_LOCAL=1;
	const POPULATE_REMOTE=2;
	const ONLY_LOCAL_EXISTING=4;

	const NEW_LOCAL=1;
	const NEW_REMOTE=2;
	const DELETED_LOCAL=3;
	const DELETED_REMOTE=4;
	const UPDATED_LOCAL=5;
	const UPDATED_REMOTE=6;
	const CONFLICT=7;
	const GARBAGE=8;
	const UP_TO_DATE=9;

	public $id;
	public $type;
	public $slug;
	public $baseRevision;
	private $localDataFetched;
	private $remoteResourceSet;
	private $localResourceData;
	private $remoteResource;

	/**
	 * Construct.
	 */
	public function __construct($type=NULL, $slug=NULL) {
		$this->type=$type;
		$this->slug=$slug;

		$this->job=NULL;
		$this->Curl="Curl";
	}

	/**
	 * Initialize.
	 */
	public static function initialize() {
		self::field("id","integer not null auto_increment");
		self::field("type","varchar(255) not null");
		self::field("slug","varchar(255) not null");
		self::field("baseRevision","varchar(255) not null");
	}

	/**
	 * Get type.
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get base revision.
	 */
	public function getBaseRevision() {
		return $this->baseRevision;
	}

	/**
	 * Get revision
	 */
	public function getLocalRevision() {
		$data=$this->getData();

		if (!$data)
			return NULL;

		return md5(json_encode($data));
	}

	/**
	 * Ensure we have local data, if available.
	 */
	private function ensureLocalDataFetched() {
		if ($this->localDataFetched)
			return;

		$this->localDataFetched=TRUE;
		$this->localResourceData=$this->getSyncer()->getResource($this->slug);
	}

	/**
	 * Get data.
	 */
	public function getData() {
		$this->ensureLocalDataFetched();

		return $this->localResourceData;
	}

	/**
	 * Get attachments.
	 */
	public function getAttachments() {
		$cands=$this->getSyncer()->getResourceAttachments($this->slug);
		$used=array();
		$attachments=array();

		foreach ($cands as $cand) {
			$filename=$this->getAttachmentDirectory()."/".$cand;
			if (file_exists($filename) && !in_array($cand,$used)) {
				$used[]=$cand;
				$attachment=new Attachment($cand,filesize($filename));
				$attachments[]=$attachment;
			}
		}

		return $attachments;
	}

	/**
	 * Get binary data file name.
	 */
	public function getResourceBinaryData() {
		return $this->getSyncer()->getResourceBinaryData($this->slug);
	}

	/**
	 * Get syncer.
	 */
	public function getSyncer() {
		return RemoteSyncPlugin::instance()->getSyncerByType($this->type);
	}

	/**
	 * Get slug.
	 */
	public function getSlug() {
		return $this->slug;
	}

	/**
	 * Get attachment dir.
	 */
	public function getAttachmentDirectory() {
		return $this->getSyncer()->getAttachmentDirectory($this->slug);
	}

	/**
	 * Download one attachment.
	 */
	private function downloadAttachment($attachment) {
		$attachmentFileName=$attachment->getFileName();
		$targetFileName=$this->getAttachmentDirectory()."/".$attachmentFileName;

		$logger=RemoteSyncPlugin::instance()->getLogger();
		$logger->log("Downloading to: ".$targetFileName);

		$dir=dirname($targetFileName);
		if (!is_dir($dir)) {
			if (!mkdir($dir,0777,TRUE))
				throw new Exception("Unable to create directory: ".$dir);
		}

		//file_put_contents($targetFileName,"hello world");

		$res=RemoteSyncPlugin::instance()->remoteCall("getAttachment")
			->addPostField("attachment",$attachmentFileName)
			->addPostField("slug",$this->slug)
			->addPostField("type",$this->type)
			->setDownloadFileName($targetFileName)
			->exec();
	}

	/**
	 * Is this attachment up to date?
	 */
	private function isLocalAttachmentCurrent($attachment) {
		$targetFileName=$this->getAttachmentDirectory()."/".$attachment->getFileName();

		if (file_exists($targetFileName) && 
				filesize($targetFileName)==$attachment->getFileSize())
			return TRUE;

		return FALSE;
	}

	/**
	 * Download attachments from remote.
	 */
	private function downloadAttachments() {
		if (!$this->getRemoteResource())
			throw new Exception("Can't download attachments, doesn't exist remote: ".$this->slug);

		$logger=RemoteSyncPlugin::instance()->getLogger();
		$attachments=$this->getRemoteResource()->getAttachments();

		foreach ($attachments as $attachment) {
			if (!$this->isLocalAttachmentCurrent($attachment)) {
				$logger->log("Downloading: ".$attachment->getFileName());
				$this->downloadAttachment($attachment);
			}

			else {
				$logger->log("Skipping: ".$attachment->getFileName());
			}
		}
	}

	/**
	 * Process posted attachments.
	 */
	public final function processPostedAttachments() {
		if (!isset($this->slug))
			throw new Exception("Can't process attachments, no slug");

		$upload_dir_info=wp_upload_dir();
		$upload_base_dir=$upload_dir_info["basedir"];

		if (sizeof($_FILES)==ini_get("max_file_uploads"))
			throw new Exception("Too many attached files, max_file_uploads=".ini_get("max_file_uploads"));

		//error_log("files: ".print_r($_FILES,TRUE));

		foreach ($_FILES as $field=>$uploadedFile) {
			if ($field!="@") {
				if ($uploadedFile["error"])
					throw new Exception("Unable to process uploaded file: ".$uploadedFile["error"]);

				//$fileName=urldecode($uploadedFile["name"]);
				$fileName=urldecode($field);
				$targetFileName=$this->getAttachmentDirectory()."/".$fileName;

				$dir=dirname($targetFileName);

				if (!file_exists($dir)) {
					if (!mkdir($dir,0777,TRUE))
						throw new Exception("Unable to create directory: ".$dir);
				}

				$res=copy($uploadedFile["tmp_name"],$targetFileName);
				if (!$res)
					throw new Exception("Unable to copy uploaded file");
			}
		}
	}

	/**
	 * Get remote resource.
	 */
	public function getRemoteResource() {
		if (!$this->remoteResourceSet)
			throw new Exception("Remote resource not fetched");

		return $this->remoteResource;
	}

	/**
	 * Set remote resource.
	 */
	private function setRemoteResource($remoteResource) {
		$this->remoteResourceSet=TRUE;
		$this->remoteResource=$remoteResource;
	}

	/**
	 * Find one for type.
	 */
	public static function findOneForType($type, $slug/*, $findFlags=0*/) {
		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($type);

		$syncResource=SyncResource::findOneByQuery(
			"SELECT * FROM %t WHERE type=%s AND slug=%s",
			$type,$slug
		);

		if ($syncResource)
			return $syncResource;

		if ($syncer->getResource($slug))
			return new SyncResource($type,$slug);

		return NULL;
	}

	/**
	 * Compare resource weight.
	 */
	private static function cmpResourceWeight($a, $b) {
		if (!isset($a->__weight)) {
			if ($a->getData())
				$a->__weight=$a->getSyncer()->getResourceWeight($a->getSlug());

			else
				$a->__weight="";
		}

		if (!isset($b->__weight)) {
			if ($b->getData())
				$b->__weight=$b->getSyncer()->getResourceWeight($b->getSlug());

			else
				$b->__weight="";
		}

		return strcmp($a->__weight,$b->__weight);
	}

	/**
	 * Get unique slug across resource types.
	 */
	public function getUniqueSlug() {
		return $this->type.":".$this->slug;
	}

	/**
	 * Find all resources for all enabled syncers.
	 */
	public static function findAllEnabled($findFlags) {
		$resources=array();

		foreach (RemoteSyncPlugin::instance()->getEnabledSyncers() as $syncer) {
			$syncerResources=SyncResource::findAllForType(
				$syncer->getType(),
				$findFlags
			);

			$resources=array_merge($resources,$syncerResources);
		}

		return $resources;
	}

	/**
	 * Find all for type.
	 * Optionally populate with local and remote resource data.
	 */
	public static function findAllForType($type, $findFlags=0) {
		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($type);

		$syncResources=SyncResource::findAllBy("type",$type);
		$syncResourcesBySlug=array();

		foreach ($syncResources as $syncResource)
			$syncResourcesBySlug[$syncResource->getSlug()]=$syncResource;

		if ($findFlags&SyncResource::POPULATE_LOCAL) {
			$slugs=$syncer->listResourceSlugs();

			foreach ($slugs as $slug) {
				if (!isset($syncResourcesBySlug[$slug])) {
					$syncResource=new SyncResource($type,$slug);
					$syncResources[]=$syncResource;
					$syncResourcesBySlug[$slug]=$syncResource;
				}
			}
		}

		usort($syncResources,"SyncResource::cmpResourceWeight");

		if ($findFlags&SyncResource::POPULATE_REMOTE) {
			$remoteResources=RemoteResource::fetchAllForType($type);

			foreach ($remoteResources as $remoteResource) {
				$slug=$remoteResource->getSlug();

				if (!isset($syncResourcesBySlug[$slug])) {
					$syncResource=new SyncResource($type,$slug);
					$syncResources[]=$syncResource;
					$syncResourcesBySlug[$slug]=$syncResource;
				}

				$syncResource=$syncResourcesBySlug[$slug];
				$syncResource->setRemoteResource($remoteResource);
			}

			foreach ($syncResources as $syncResource)
				$syncResource->remoteResourceSet=TRUE;
		}

		if ($findFlags&SyncResource::ONLY_LOCAL_EXISTING) {
			if ($findFlags&SyncResource::POPULATE_REMOTE)
				throw new Exception("Can't use POPULATE_REMOTE and ONLY_LOCAL_EXISTING at the same time");

			$tmp=$syncResources;
			$syncResources=array();

			foreach ($tmp as $syncResource) {
				if ($syncResource->getLocalRevision())
					$syncResources[]=$syncResource;
			}
		}

		return $syncResources;
	}

	/**
	 * Get remote revision.
	 */
	function getRemoteRevision() {
		return $this->getRemoteResource()->getRevision();
	}

	/**
	 * Create local resource with remote data.
	 * Download attachments related to the resource.
	 * Saves the resource to the database.
	 */
	function createLocalResource() {
		$slug=$this->getRemoteResource()->getSlug();
		$binaryDataFileName=$this->getRemoteResource()->downloadBinaryData();

		$resourceInfo=new ResourceUpdateInfo(TRUE,
			$this->getRemoteResource()->getData(),
			$binaryDataFileName
		);

		$localId=$this->getSyncer()->updateResource($slug,$resourceInfo);

		if ($binaryDataFileName)
			@unlink($binaryDataFileName);

		$this->localDataFetched=FALSE;
		$this->baseRevision=$this->getLocalRevision();

		if ($this->getRemoteRevision()!=$this->getLocalRevision()) {
			$logger=RemoteSyncPlugin::instance()->getLogger();
			if ($logger) {
				$logger->log("**** Tried to save, but that changed the revision, slug=".$slug);
				$logger->log("**** Remote:");
				$logger->log(json_encode($this->getRemoteResource()->getData(),JSON_PRETTY_PRINT));
				$logger->log("**** Local:");
				$logger->log(json_encode($this->getData(),JSON_PRETTY_PRINT));
			}

			$this->getSyncer()->deleteResource($slug);

			throw new Exception($slug.": Local revision differ from remote after create.");
		}

		try {
			$this->downloadAttachments();
		}

		catch (Exception $e) {
			$this->deleteLocalResource();
			throw $e;
		}

		$this->save();
	}

	/**
	 * Update local resource with remote data.
	 * Download any attachments and stor the resource in the database.
	 * TODO: restore local data on failure.
	 */
	function updateLocalResource() {
		$binaryDataFileName=$this->getRemoteResource()->downloadBinaryData();
		$resourceInfo=new ResourceUpdateInfo(FALSE,
			$this->getRemoteResource()->getData(),
			$binaryDataFileName
		);

		$this->getSyncer()->updateResource(
			$this->getRemoteResource()->getSlug(),
			$resourceInfo
		);

		if ($binaryDataFileName)
			@unlink($binaryDataFileName);

		$this->localDataFetched=FALSE;
		$this->baseRevision=$this->getLocalRevision();

		if ($this->getRemoteRevision()!=$this->getLocalRevision()) {
			throw new Exception(
				"Local revision differ from remote after update\n".
				"local: ".json_encode($this->getData())."\n".
				"remote: ".json_encode($this->getRemoteResource()->getData())
			);
		}

		$this->downloadAttachments();
		$this->save();
	}

	/**
	 * Delete the local resource.
	 */
	function deleteLocalResource() {
		$this->getSyncer()->deleteResource($this->slug);
		$this->delete();
	}

	/**
	 * Create remote resource based on local data.
	 */
	function createRemoteResource() {
		$call=RemoteSyncPlugin::instance()->remoteCall("add")
			->addPostField("type",$this->type)
			->addPostField("slug",$this->slug)
			->addPostField("data",json_encode($this->getData()));

		$this->addBinaryDataToRemoteCall($call);
		$this->addAttachmentsToRemoteCall($call);
		$call->exec();

		$this->baseRevision=md5(json_encode($this->getData()));
	}

	/**
	 * Delete remote resource.
	 */
	function deleteRemoteResource() {
		RemoteSyncPlugin::instance()->remoteCall("del")
			->addPostField("type",$this->type)
			->addPostField("slug",$this->slug)
			->exec();
	}

	/**
	 * Update remote resource.
	 */
	function updateRemoteResource() {
		$call=RemoteSyncPlugin::instance()->remoteCall("put")
			->addPostField("type",$this->type)
			->addPostField("slug",$this->slug)
			->addPostField("data",json_encode($this->getData()))
			->addPostField("baseRevision",$this->baseRevision);

		$this->addBinaryDataToRemoteCall($call);
		$this->addAttachmentsToRemoteCall($call);
		$call->exec();

		$this->baseRevision=md5(json_encode($this->getData()));
	}

	/**
	 * Is the attachment on the remote current with this one?
	 */
	private function isRemoteAttachmentCurrent($attachment) {
		if (!$this->getRemoteResource())
			return FALSE;

		$remoteResource=$this->getRemoteResource();
		$remoteAttachment=$remoteResource->getAttachmentByFileName($attachment->getFileName());

		if (!$remoteAttachment)
			return FALSE;

		if ($remoteAttachment->getFileSize()!=$attachment->getFileSize())
			return FALSE;

		return TRUE;
	}

	/**
	 * Add local attachments to call for upload.
	 */
	private function addAttachmentsToRemoteCall($call) {
		$logger=RemoteSyncPlugin::instance()->getLogger();

		foreach ($this->getAttachments() as $attachment) {
			if (!$this->isRemoteAttachmentCurrent($attachment)) {
				$logger->log("Uploading: ".$attachment->getFileName());

				$encodedName=urlencode($attachment->getFileName());
				$encodedName=str_replace('.','%2E',$encodedName);
				$encodedName=str_replace('-','%2D',$encodedName);

				$call->addFileUpload(
					$encodedName,
					$this->getAttachmentDirectory()."/".$attachment->getFileName()
				);
			}

			else {
				$logger->log("Skipping: ".$attachment->getFileName());
			}
		}
	}

	/**
	 * Add binary data to remote call.
	 */
	private function addBinaryDataToRemoteCall($call) {
		$binaryDataFileName=$this->getResourceBinaryData();
		if (!$binaryDataFileName)
			return;

		$call->addFileUpload("@",$binaryDataFileName);
	}

	/**
	 * Get state.
	 */
	function getState() {
		if ($this->getRemoteResource()) {
			if (!$this->getRemoteRevision())
				throw new Exception("no remote revision");

			if (!$this->getLocalRevision() &&
					$this->getBaseRevision())
				return SyncResource::DELETED_LOCAL;

			if ($this->getLocalRevision()==
					$this->getRemoteRevision())
				return SyncResource::UP_TO_DATE;

			if (!$this->getLocalRevision())
				return SyncResource::NEW_REMOTE;

			if ($this->getLocalRevision()!=$this->getBaseRevision() &&
					$this->getRemoteRevision()!=$this->getBaseRevision())
				return SyncResource::CONFLICT;

			if ($this->getLocalRevision()!=$this->getBaseRevision())
				return SyncResource::UPDATED_LOCAL;

			if ($this->getRemoteRevision()!=$this->getBaseRevision())
				return SyncResource::UPDATED_REMOTE;

			throw new Exception("unknown state, shouldn't happen");
		}

		if (!$this->getLocalRevision())
			return SyncResource::GARBAGE;

		if ($this->getBaseRevision())
			return SyncResource::DELETED_REMOTE;

		if ($this->getLocalRevision() && !$this->getBaseRevision())
			return SyncResource::NEW_LOCAL;

		throw new Exception("unknown state, shouldn't happen");
	}
}
