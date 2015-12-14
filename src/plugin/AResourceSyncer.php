<?php

require_once __DIR__."/../model/SyncResource.php";
require_once __DIR__."/../../ext/merge3/DiffModule.php";
require_once __DIR__."/../../ext/spyc/Spyc.php";
/**
 * Abstract class for handling remotely syncable resources.
 */
abstract class AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct($type) {
		$this->type=$type;
	}

	/**
	 * Is the underlying resource available?
	 */
	public function isAvailable() {
		return TRUE;
	}

	/**
	 * Get type.
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Update local revisions.
	 */
	public final function updateSyncResources() {
		$localIds=$this->listResourceIds();
		$syncResources=$this->getSyncResourcesByLocalId();

		foreach ($localIds as $localId) {
			if (!isset($syncResources[$localId])) {
				$syncResource=new SyncResource($this->type);
				$syncResource->globalId=uniqid();
				$syncResource->localId=$localId;
				$syncResource->save();
			}
		}

		foreach ($syncResources as $syncResource) {
			if (!$syncResource->getRevision() &&
					!$syncResource->getBaseRevision())
				$syncResource->delete();
		}
	}

	/**
	 * List current local resources of this resource type.
	 */
	abstract function listResourceIds();

	/**
	 * Fetch a local resource.
	 * Should return an array with data.
	 */
	abstract function getResource($localId);

	/**
	 * Update a local resource with data.
	 */
	abstract function updateResource($localId, $data);

	/**
	 * Create a local resource.
	 */
	abstract function createResource($data);

	/**
	 * Delete a local resource.
	 */
	abstract function deleteResource($localId);

	/**
	 * Merge resource data.
	 */
	abstract function mergeResourceData($base, $local, $remote);

	/**
	 * Get label to show when syncing.
	 */
	abstract function getResourceLabel($data);

	/**
	 * Get local resource revision.
	 */
	function getResourceRevision($data) {
		if (!$data)
			return NULL;

		return md5(json_encode($data));
	}

	/**
	 * Override this to support attached files.
	 */
	function getResourceAttachments($localId) {
		return array();
	}

	/**
	 * Use this attachment and make sure it exists in the upload folder. 
	 * It can come from a post file attachment, if we are the remote in the 
	 * middle of a push operation. It can be fetched from the remote, 
	 * if we are in a pull operation.
	 */
	private function processAttachment($filename) {
		$upload_base_dir=wp_upload_dir()["basedir"];
		$targetfilename="$upload_base_dir/$filename";
		$keyfilename=str_replace(".","_",$filename);

		if (file_exists($targetfilename))
			return;

		if ($_FILES[$keyfilename]) {
			move_uploaded_file($_FILES[$keyfilename]["tmp_name"],$targetfilename);
			return;
		}

		$url=get_option("rs_remote_site_url");
		if (!trim($url))
			throw new Exception("Remote site url not set for fetching attachment.");

		$outf=fopen($targetfilename,"wb");
		if (!$outf)
			throw new Exception("Unable to write attachment file: ".$targetfilename);

		$url.="/wp-content/uploads/$filename";

		$curl=curl_init();
		curl_setopt($curl,CURLOPT_FILE,$outf);
		curl_setopt($curl,CURLOPT_HEADER,0);
		curl_setopt($curl,CURLOPT_URL,$url);
		curl_exec($curl);
		fclose($outf);

		if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!=200) {
			@unlink($targetfilename);
			throw new Exception($url.": HTTP Error: ".curl_getinfo($curl,CURLINFO_HTTP_CODE));
		}

		if (curl_error($curl)) {
			@unlink($targetfilename);
			throw new Exception(curl_error($curl));
		}
	}

	/**
	 * Process attachments. If they don't exist locally they can either
	 * have been sent as attachments to the current request. If not, try
	 * to download them from the remote.
	 */
	public final function processAttachments($localId) {
		$attachments=$this->getResourceAttachments($localId);

		foreach ($attachments as $attachment)
			$this->processAttachment($attachment);
	}

	/**
	 * Convert local id to global id.
	 */
	public function localToGlobal($localId) {
		$r=SyncResource::findOneBy(array(
			"type"=>$this->type,
			"localId"=>$localId
		));

		if (!$r)
			return NULL;

		return $r->globalId;
	}

	/**
	 * Convert global id to local id.
	 */
	public function globalToLocal($globalId) {
		$r=SyncResource::findOneBy("globalId",$globalId);
		if (!$r)
			return NULL;

		if ($r->type!=$this->type)
			throw new Exception("Wrong type");

		return $r->localId;
	}

	/**
	 * Get sync resource by local id.
	 */
	public function getSyncResourceByLocalId($localId) {
		return SyncResource::findOneBy(array(
			"type"=>$this->type,
			"localId"=>$localId
		));
	}

	/**
	 * Get related sync resources.
	 */
	public function getSyncResources() {
		return SyncResource::findAllBy("type",$this->type);
	}

	/**
	 * Get related sync resources, keyed on globalId.
	 */
	public function getSyncResourcesByGlobalId() {
		$syncResources=$this->getSyncResources();
		$res=[];

		foreach ($syncResources as $syncResource)
			$res[$syncResource->globalId]=$syncResource;

		return $res;
	}

	/**
	 * Get related sync resources, keyed on localId.
	 */
	public function getSyncResourcesByLocalId() {
		$syncResources=$this->getSyncResources();
		$res=[];

		foreach ($syncResources as $syncResource)
			$res[$syncResource->localId]=$syncResource;

		return $res;
	}

	/**
	 * Get remote resources.
	 */
	public function getRemoteResources() {
		return RemoteSyncPlugin::instance()->getRemoteResources($this->type);
	}

	/**
	 * Get remote resources, keyed on globalId.
	 */
	public function getRemoteResourcesByGlobalId() {
		$remoteResources=$this->getRemoteResources();
		$res=[];

		foreach ($remoteResources as $remoteResource)
			$res[$remoteResource->globalId]=$remoteResource;

		return $res;
	}

	/**
	 * Install.
	 */
	public function install() {
		$this->updateSyncResources();
	}

	/**
	 * Merge key values from objects.
	 */
	public final function mergeKeyValue($key, $base, $local, $remote) {
		return $this->merge($base[$key],$local[$key],$remote[$key]);
	}

	/**
	 * Pick a "merged" value depending on settings.
	 */
	public function pickKeyValue($key, $base, $local, $remote) {
		return $this->pick($base[$key],$local[$key],$remote[$key]);
	}

	/**
	 * Pick a value depending on changes and configuration.
	 */
	public static function pick($base, $local, $remote) {
		if (get_option("rs_merge_strategy")=="prioritize_local" 
				&& $local!=$base)
			return $local;

		if ($remote!=$base)
			return $remote;

		if ($local!=$base)
			return $local;

		return $base;
	}

	/**
	 * Merge text according to priority settings.
	 */
	public final function merge($base, $local, $remote) {
		$base=explode("\n",$base);
		$local=explode("\n",$local);
		$remote=explode("\n",$remote);

		$diff=new DiffModule();
		$mergeRecords=$diff->merge3($base,$local,$remote);
		$res="";

		foreach ($mergeRecords as $mergeRecord) {
			switch ($mergeRecord[0]) {
				case 'orig':
				case 'addright':
				case 'addleft':
				case 'addboth':
					$res.=$mergeRecord[1]."\n";
					break;

				case "delleft":
				case "delright":
				case "delboth":
					break;

				case "conflict":
					if (get_option("rs_merge_strategy")=="prioritize_local")
						$res.=$mergeRecord[1]."\n";

					else
						$res.=$mergeRecord[2]."\n";
					break;

				default:
					print_r($mergeRecords);
					throw new Exception("Strange merge conflict: ".$mergeRecord[0]);
					break;
			}
		}

		$res=substr($res,0,strlen($res)-1);

		return $res;
	}

	/**
	 * Merge objects.
	 * COnvert them to yaml, then merge text and parse.
	 */
	public final function mergeObjects($base, $local, $remote) {
		$baseYaml=spyc_dump($base);
		$localYaml=spyc_dump($base);
		$remoteYaml=spyc_dump($base);

		$mergedYaml=$this->merge($baseYaml,$localYaml,$remoteYaml);

		return spyc_load($mergedYaml);
	}
}