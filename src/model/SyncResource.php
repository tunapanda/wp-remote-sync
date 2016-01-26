<?php

require_once __DIR__."/../../ext/smartrecord/SmartRecord.php";
require_once __DIR__."/RemoteResource.php";
/**
 * Manage one synced resouce.
 */
class SyncResource extends SmartRecord {

	const POPULATE_LOCAL=1;
	const POPULATE_REMOTE=2;

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
		return $this->getSyncer()->getResourceAttachments($this->slug);
	}

	/**
	 * Get attachment entries, an array on the form:
	 *   <formfield> => <file>
	 */
	/*public function getAttachmentEntries() {
		$uploadBasedir=wp_upload_dir()["basedir"];

		$attachments=$this->getAttachments();
		$res=array();

		foreach ($attachments as $attachment)
			$res[urlencode($attachment)]=
				$uploadBasedir."/".str_replace("{id}",$this->localId,$attachment);

		return $res;
	}*/

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
	 * Progress when downloading.
	 */
	public function downloadCurlProgress($id, $downTotal, $down, $upTotal, $up) {
		if (!$this->job)
			return;

		$percent=0;

		if ($upTotal && $up<$upTotal)
			$percent=round(100*$up/$upTotal);

		else if ($downTotal && $down<$downTotal)
			$percent=round(100*$down/$downTotal);

		$this->job->progressStatus($this->downloadMessage,$percent);
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
		$targetFileName=$this->getAttachmentDirectory()."/".$attachment;
		if (file_exists($targetFileName)) {
			if ($this->log)
				$this->job->log("Attachment up to date: ".$attachment);
			return;
		}

		if ($this->job)
			$this->job->status("Downloading: '".$attachment."'");

		$url=get_option("rs_remote_site_url");
		if (!$url)
			throw new Exception("Remote site url not set for fetching attachment.");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";

		$params=array(
			"action"=>"getAttachment",
			"key"=>get_option("rs_access_key",""),
			"filename"=>$attachment,
			"slug"=>$this->slug
		);

		$url.="?".http_build_query($params);

		$dir=dirname($targetFileName);
		if (!is_dir($dir)) {
			if (!mkdir($dir,0777,TRUE))
				throw new Exception("Unable to create directory: ".$dir);
		}

		$outf=fopen($targetFileName,"wb");
		if (!$outf)
			throw new Exception("Unable to write attachment file: ".$localFilename);

		$curl=new $this->Curl($url);
		$curl->setopt(CURLOPT_FILE,$outf);
		$curl->setopt(CURLOPT_HEADER,0);
		$curl->setopt(CURLOPT_NOPROGRESS,FALSE);
		$curl->setopt(CURLOPT_PROGRESSFUNCTION,array($this,"downloadCurlProgress"));
		$curl->exec();
		fclose($outf);

		if ($curl->error()) {
			unlink($targetFileName);
			throw new Exception($curl->error());
		}

		if ($curl->getinfo(CURLINFO_HTTP_CODE)!=200) {
			unlink($targetFileName);
			throw new Exception($url.": HTTP Error: ".$curl->getinfo(CURLINFO_HTTP_CODE));
		}
	}

	/**
	 * Download attachments from remote.
	 */
	public function downloadAttachments() {
		if (!$this->getRemoteResource())
			throw new Exception("Can't download attachments, doesn't exist remote: ".$this->slug);

		$attachments=$this->getRemoteResource()->getAttachments();

		foreach ($attachments as $attachment)
			$this->downloadAttachment($attachment);
	}

	/**
	 * Process posted attachments.
	 */
	public final function processPostedAttachments() {
		if (!isset($this->slug))
			throw new Exception("Can't process attachments, no slug");

		$upload_base_dir=wp_upload_dir()["basedir"];

		foreach ($_FILES as $uploadedFile) {
			if ($uploadedFile["error"])
				throw new Exception("Unable to process uploaded file: ".$uploadedFile["error"]);

			$fileName=urldecode($uploadedFile["name"]);
			$fileName=str_replace("{id}",$this->localId,$fileName);
			$targetFileName=$upload_base_dir."/".$fileName;
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
		if (!isset($a->__weight))
			$a->__weight=$a->getSyncer()->getResourceWeight($a->getSlug());

		if (!isset($b->__weight))
			$b->__weight=$b->getSyncer()->getResourceWeight($b->getSlug());

		return strcmp($a->__weight,$b->__weight);
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
	 */
	function createLocalResource() {
		$this->getSyncer()->createResource(
			$this->getRemoteResource()->getSlug(),
			$this->getRemoteResource()->getData()
		);

		$this->localDataFetched=FALSE;
		$this->baseRevision=$this->getLocalRevision();

		if ($this->getRemoteRevision()!=$this->getLocalRevision())
			throw new Exception("Local revision differ from remote after create");
	}

	/**
	 * Update local resource with remote data.
	 */
	function updateLocalResource() {
		$this->getSyncer()->updateResource(
			$this->getRemoteResource()->getSlug(),
			$this->getRemoteResource()->getData()
		);

		$this->localDataFetched=FALSE;
		$this->baseRevision=$this->getLocalRevision();

		if ($this->getRemoteRevision()!=$this->getLocalRevision())
			throw new Exception("Local revision differ from remote after update");
	}

	/**
	 * Delete the local resource.
	 */
	function deleteLocalResource() {
		$this->getSyncer()->deleteResource($this->slug);
	}

	/**
	 * Create remote resource based on local data.
	 */
	function createRemoteResource() {
		RemoteSyncPlugin::instance()->remoteCall("add",array(
			"type"=>$this->type,
			"slug"=>$this->slug,
			"data"=>json_encode($this->getData())
		));

		$this->baseRevision=md5(json_encode($this->getData()));
	}

	/**
	 * Delete remote resource.
	 */
	function deleteRemoteResource() {
		RemoteSyncPlugin::instance()->remoteCall("del",array(
			"type"=>$this->type,
			"slug"=>$this->slug
		));
	}

	/**
	 * Update remote resource.
	 */
	function updateRemoteResource() {
		RemoteSyncPlugin::instance()->remoteCall("put",array(
			"type"=>$this->type,
			"slug"=>$this->slug,
			"data"=>json_encode($this->getData()),
			"baseRevision"=>$this->baseRevision
		));

		$this->baseRevision=md5(json_encode($this->getData()));
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