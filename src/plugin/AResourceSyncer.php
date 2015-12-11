<?php

require_once __DIR__."/../model/SyncResource.php";
require_once __DIR__."/../../ext/merge3/DiffModule.php";

/**
 * Abstract class for handling remotely syncable resources.
 */
abstract class AResourceSyncer {

	static $actOnLocalChange=TRUE;

	/**
	 * Construct.
	 */
	public function __construct($type) {
		$this->type=$type;
	}

	/**
	 * Get type.
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Act on local change?
	 */
	public function setActOnLocalChange($value) {
		AResourceSyncer::$actOnLocalChange=$value;
	}

	/**
	 * Notify the system that a resource has been locally changed.
	 */
	public final function notifyLocalChange($localId) {
		if (!AResourceSyncer::$actOnLocalChange)
			return;

		if (!$localId)
			throw new Exception("localId is null!");

		$syncResource=SyncResource::findOneBy("localId",$localId);

		if (!$syncResource && !$this->getResource($localId))
			return;

		if ($syncResource && !$this->getResource($localId)) {
			$syncResource->delete();
			return;
		}

		if (!$syncResource) {
			$syncResource=new SyncResource($this->type);
			$syncResource->localId=$localId;
			$syncResource->globalId=uniqid();
		}

		$syncResource->revision=uniqid();
		$syncResource->save();
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
	 * Convert local id to global id.
	 */
	public function localToGlobal($localId) {
		$r=SyndResource::findOneBy(array(
			"type"=>$this->type,
			"localId"=>$localId
		));

		return $r->globalId;
	}

	/**
	 * Convert global id to local id.
	 */
	public function globalToLocal($globalId) {
		$r=SyndResource::findOneBy("globalId",$globalId);
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
		$currentIds=$this->listResourceIds();

		foreach ($currentIds as $id) {
			$this->notifyLocalChange($id);
		}
	}

	/**
	 * Merge according to priority settings.
	 */
	public final function merge3($base, $local, $remote) {
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

		return $res;
	}
}