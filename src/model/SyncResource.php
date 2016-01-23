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
		$this->Curl="Curl";
	}

	/**
	 * Initialize.
	 */
	public static function initialize() {
		self::field("id","integer not null auto_increment");
		self::field("type","varchar(255) not null");
		self::field("localId","integer not null");
		self::field("globalId","varchar(255) not null");
		self::field("baseData","longtext");
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
		$decodedBaseData=json_decode($this->baseData,TRUE);

		if ($this->baseData && $decodedBaseData===NULL)
			throw new Exception("Unable to decode base data");

		return $decodedBaseData;
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
	 * Get attachments.
	 */
	public function getAttachments() {
		return $this->getSyncer()->getResourceAttachments($this->localId);
	}

	/**
	 * Get attachment entries, an array on the form:
	 *   <formfield> => <file>
	 */
	public function getAttachmentEntries() {
		$uploadBasedir=wp_upload_dir()["basedir"];

		$attachments=$this->getAttachments();
		$res=array();

		foreach ($attachments as $attachment)
			$res[urlencode($attachment)]=
				$uploadBasedir."/".str_replace("{id}",$this->localId,$attachment);

		return $res;
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
	 * Download one attachment.
	 */
	private function downloadAttachment($attachment, $remoteResource) {
		$uploadBasedir=wp_upload_dir()["basedir"];
		$localFilename=str_replace("{id}",$this->localId,"$uploadBasedir/$attachment");
		if (file_exists($localFilename))
			return;

		$url=get_option("rs_remote_site_url");
		if (!$url)
			throw new Exception("Remote site url not set for fetching attachment.");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";

		$params=array(
			"action"=>"getAttachment",
			"key"=>get_option("rs_access_key",""),
			"filename"=>$attachment,
			"globalId"=>$remoteResource->globalId
		);

		$url.="?".http_build_query($params);

		$dir=dirname($localFilename);
		if (!is_dir($dir)) {
			if (!mkdir($dir,0777,TRUE))
				throw new Exception("Unable to create directory: ".$dir);
		}

		$outf=fopen($localFilename,"wb");
		if (!$outf)
			throw new Exception("Unable to write attachment file: ".$localFilename);

		$curl=new $this->Curl($url);
		$curl->setopt(CURLOPT_FILE,$outf);
		$curl->setopt(CURLOPT_HEADER,0);
		$curl->exec();
		fclose($outf);

		if ($curl->error()) {
			@unlink($localFilename);
			throw new Exception($curl->error());
		}

		if ($curl->getinfo(CURLINFO_HTTP_CODE)!=200) {
			@unlink($localFilename);
			throw new Exception($url.": HTTP Error: ".$curl->getinfo(CURLINFO_HTTP_CODE));
		}
	}

	/**
	 * Download attachments from remote.
	 */
	public function downloadAttachments($remoteResource) {
		$attachments=$remoteResource->getAttachments();

		foreach ($attachments as $attachment)
			$this->downloadAttachment($attachment,$remoteResource);
	}

	/**
	 * Process posted attachments.
	 */
	public final function processPostedAttachments() {
		if (!isset($this->localId) || !$this->localId)
			throw new Exception("Can't process attachments, no local id yet");

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
}