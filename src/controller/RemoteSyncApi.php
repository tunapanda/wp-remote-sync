<?php

/**
 * Handle api calls.
 */
class RemoteSyncApi {

	private $plugin;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->calls=array("ls","get","put","add","del","getAttachment");
	}

	/**
	 * Get attachment file.
	 */
	public function getAttachment($args) {
		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if (!$resource)
			throw new Exception("resource not found, id=".$args["globalId"]);

		$filename=str_replace("{id}",$resource->localId,$args["filename"]);
		$filename=wp_upload_dir()["basedir"]."/".$filename;

		if (!file_exists($filename))
			throw new Exception("file doesn't exist: ".$filename);

		$type=mime_content_type($filename);
		header("Content-Type: $type");

		readfile($filename);
		exit();
	}

	/**
	 * Compare resource weight.
	 */
	private function cmpResourceWeight($a, $b) {
		return strcmp($a->weight,$b->weight);
	}

	/**
	 * List.
	 */
	public function ls($args) {
		if (!isset($args["type"]))
			throw new Exception("Expected resource type fopen(filename, mode)r ls");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($args["type"]);
		$syncer->updateSyncResources();
		$resources=$syncer->getSyncResources();
		$res=array();

		for ($i=0; $i<sizeof($resources); $i++)
			$resources[$i]->weight=$syncer->getResourceWeight($resources[$i]->localId);

		usort($resources,array($this,"cmpResourceWeight"));

		foreach ($resources as $resource) {
			if (!$resource->isDeleted()) {
				$res[]=array(
					"globalId"=>$resource->globalId,
					"revision"=>$resource->getRevision()
				);
			}
		}

		return $res;
	}

	/**
	 * Get resource
	 */
	public function get($args) {
		if (!$args["globalId"])
			throw new Exception("Expected parameter globalId");

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($resource->type);
		$syncer->updateSyncResources();

		return array(
			"globalId"=>$resource->globalId,
			"revision"=>$resource->getRevision(),
			"type"=>$resource->type,
			"data"=>$resource->getData()
		);
	}

	/**
	 * Add a resource.
	 */
	public function add($args) {
		if (!$args["globalId"] ||
			!$args["data"] || !$args["type"])
			throw new Exception("Expected globalId, type and data.");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($args["type"]);
		$syncer->updateSyncResources();

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if ($resource)
			throw new Exception("Already exists!");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$localId=$syncer->createResource($data);
		$syncer->processAttachments($localId,$args["globalId"]);

		$localResource=new SyncResource($syncer->getType());
		$localResource->localId=$localId;
		$localResource->globalId=$args["globalId"];
		$localResource->save();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Put.
	 */
	public function put($args) {
		if (!$args["globalId"] ||
			!$args["baseRevision"] || !$args["data"])
			throw new Exception("Expected globalId, baseRevision and data.");

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		if ($args["baseRevision"]!=$resource->getRevision())
			throw new Exception("Wrong base revision, please pull.");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$syncer=$resource->getSyncer();
		//$syncer->updateSyncResources();
		$syncer->updateResource($resource->localId,$data);
		$syncer->processAttachments($resource->localId,$args["globalId"]);

		$resource->save();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Delete.
	 */
	public function del($args) {
		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		$syncer=$resource->getSyncer();
		$syncer->deleteResource($resource->localId);

		if (!$resource->getBaseRevision())
			$resource->delete();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Handle exception in api call.
	 */
	public function handleException($exception) {
		$res=array(
			"error"=>TRUE,
			"message"=>$exception->getMessage()
		);

		http_response_code(500);
		echo json_encode($res);
		exit();
	}

	/**
	 * Handle api call.
	 */
	public function handleApiCall($call, $params) {
		set_exception_handler(array($this,"handleException"));
		if (!in_array($call,$this->calls))
			throw new Exception("Unknown api call: $call");
		$res = $this->doApiCall($call, $params);
		echo json_encode($res);
		exit();
	}

	/*
		Handle the Api Response
	*/
	public function doApiCall($call, $params){
		$res = array();
		if (!(array_key_exists("key", $params) && $params["key"] === get_option("rs_access_key",""))){
			$res += array("Error" => "Operation NOT permitted!!\nEither you have not set the access key or the access key does not match the remote access key."); 
			return $res; 
		}
		else {
			$res=call_user_func(array($this,$call),$params);
			return $res;
		}
	}
}
