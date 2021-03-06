<?php

require_once __DIR__."/../plugin/ResourceUpdateInfo.php";

/**
 * Handle api calls.
 */
class RemoteSyncApi {

	private $plugin;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->calls=array(
			"ls","get","put","add","del",
			"getAttachment","getBinary"
		);
	}

	/**
	 * Check for required access level.
	 */
	public function requireAccessLevel($args, $requiredLevel) {
		$level=$this->getAccessLevel($args);

		switch ($requiredLevel) {
			case "upload":
				if ($level=="upload")
					return;
				break;

			case "download":
				if (in_array($level,array("download","upload")))
					return;
		}

		throw new Exception("Not allowed to perform this action.");
	}

	/**
	 * Get access level.
	 */
	public function getAccessLevel($args) {
		$downloadKey=get_option("rs_download_access_key");
		$uploadKey=get_option("rs_upload_access_key");

		$key=NULL;
		if (isset($args["key"]))
			$key=$args["key"];

		if ($uploadKey && $key==$uploadKey)
			return "upload";

		if (!$downloadKey || $key==$downloadKey)
			return "download";

		return NULL;
	}

	/**
	 * Get binary data for a resource.
	 */
	public function getBinary($args) {
		$this->requireAccessLevel($args,"download");

		$syncResource=SyncResource::findOneForType($args["type"],$args["slug"]);
		if (!$syncResource)
			throw new Exception("resource not found, slug=".$args["slug"]);

		$filename=$syncResource->getResourceBinaryData();

		if (!file_exists($filename))
			throw new Exception("file doesn't exist: ".$filename);

		if (!is_file($filename))
			throw new Exception("that's not a file: ".$filename);

		$type=mime_content_type($filename);
		header("Content-Type: $type");
		header("Content-Disposition: attachment; filename=".basename($filename));

		$filesize=filesize($filename);
		header("Content-Length: ".$filesize);
    	header("Content-Range: 0-".($filesize-1)."/".$filesize);

		readfile($filename);
		exit();
	}

	/**
	 * Get attachment file.
	 */
	public function getAttachment($args) {
		$this->requireAccessLevel($args,"download");

		if (!$args["attachment"])
			throw new Exception("expected arg: attachment");

		$syncResource=SyncResource::findOneForType($args["type"],$args["slug"]);
		if (!$syncResource)
			throw new Exception("resource not found, slug=".$args["slug"]);

		$filename=$syncResource->getAttachmentDirectory()."/".$args["attachment"];

		if (!file_exists($filename))
			throw new Exception("file doesn't exist: ".$filename);

		if (!is_file($filename))
			throw new Exception("that's not a file: ".$filename);

		$type=mime_content_type($filename);
		header("Content-Type: $type");

		$filesize=filesize($filename);
		header("Content-Length: ".$filesize);
    	header("Content-Range: 0-".($filesize-1)."/".$filesize);

		readfile($filename);
		exit();
	}

	/**
	 * List.
	 */
	public function ls($args) {
		$this->requireAccessLevel($args,"download");

		if (!isset($args["type"]))
			throw new Exception("Expected resource type for ls");

		$syncResources=
			SyncResource::findAllForType(
				$args["type"],
				SyncResource::POPULATE_LOCAL|SyncResource::ONLY_LOCAL_EXISTING
			);

		$res=array();

		foreach ($syncResources as $syncResource)
			$res[]=array(
				"slug"=>$syncResource->getSlug(),
				"revision"=>$syncResource->getLocalRevision(),
				"weight"=>$syncResource->getWeight()
			);

		//sleep(1);

		return $res;
	}

	/**
	 * Get resource
	 */
	public function get($args) {
		$this->requireAccessLevel($args,"download");

		if (!isset($args["type"]) || !$args["type"])
			throw new Exception("Expected parameter type");

		if (!isset($args["slug"]) || !$args["slug"])
			throw new Exception("Expected parameter slug");

		$resource=SyncResource::findOneForType($args["type"],$args["slug"]);

		if (!$resource)
			throw new Exception("The resource doesn't exist locally");

		$attachmentData=array();
		foreach ($resource->getAttachments() as $attachment)
			$attachmentData[]=array(
				"fileName"=>$attachment->getFileName(),
				"fileSize"=>$attachment->getFileSize()
			);

		$hasBinaryData=$resource->getResourceBinaryData()!=NULL;

		return array(
			"slug"=>$resource->getSlug(),
			"revision"=>$resource->getLocalRevision(),
			"type"=>$resource->getType(),
			"data"=>$resource->getData(),
			"attachments"=>$attachmentData,
			"binary"=>$hasBinaryData
		);
	}

	/**
	 * Get posted binary data. Used by add and put.
	 */
	public function getPostedBinaryData() {
		//error_log(print_r($_FILES,TRUE));

		if (!isset($_FILES["@"]))
			return NULL;

		if ($_FILES["@"]["error"])
			throw new Exception("Unable to process binary data: ".$_FILES["@"]["error"]);

		return $_FILES["@"]["tmp_name"];
	}

	/**
	 * Add a resource.
	 */
	public function add($args) {
		$this->requireAccessLevel($args,"upload");

		if (!$args["slug"] ||
			!$args["data"] || !$args["type"])
			throw new Exception("Expected slug, type and data.");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($args["type"]);

		$resource=SyncResource::findOneForType($args["type"],$args["slug"]);
		if ($resource)
			throw new Exception("Already exists!");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$updateInfo=new ResourceUpdateInfo(TRUE,
			$data,$this->getPostedBinaryData());

		$syncer->updateResource($args["slug"],$updateInfo);
		$syncResource=SyncResource::findOneForType($args["type"],$args["slug"]);

		try {
			$syncResource->processPostedAttachments();
		}

		catch (Exception $e) {
			$syncer->deleteResource($args["slug"]);
			throw $e;
		}

		return array(
			"ok"=>1
		);
	}

	/**
	 * Put.
	 */
	public function put($args) {
		$this->requireAccessLevel($args,"upload");

		if (!$args["slug"] ||
			!$args["baseRevision"] || !$args["data"] || !$args["type"])
			throw new Exception("Expected slug, baseRevision, type and data.");

		$resource=SyncResource::findOneForType($args["type"],$args["slug"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		if ($args["baseRevision"]!=$resource->getLocalRevision())
			throw new Exception("Wrong base revision, please pull.");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$syncer=$resource->getSyncer();
		$oldData=$syncer->getResource($resource->getSlug());
		$oldBinaryData=$syncer->getResourceBinaryData($resource->getSlug());

		$updateInfo=new ResourceUpdateInfo(FALSE,
			$data,$this->getPostedBinaryData());

		$syncer->updateResource($resource->getSlug(),$updateInfo);

		try {
			$resource->processPostedAttachments();
		}

		catch (Exception $e) {
			// we should "reset" the resource here, but this is difficult...
			throw $e;
		}

		return array(
			"ok"=>1
		);
	}

	/**
	 * Delete.
	 */
	public function del($args) {
		$this->requireAccessLevel($args,"upload");

		if (!isset($args["type"]) || !$args["type"])
			throw new Exception("Expected parameter type");

		if (!isset($args["slug"]) || !$args["slug"])
			throw new Exception("Expected parameter slug");

		$resource=SyncResource::findOneForType($args["type"],$args["slug"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		$syncer=$resource->getSyncer();
		$syncer->deleteResource($resource->getSlug());

		if (!$resource->getBaseRevision() && $resource->id)
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
			"message"=>$exception->getMessage(),
			"trace"=>$exception->getTraceAsString()
		);

		http_response_code(500);
		echo json_encode($res);
		exit();
	}

	/**
	 * Handle api call.
	 * This is not testable, so do as much logic as posible 
	 * in doApiCall.
	 */
	public function handleApiCall($call, $params) {
		set_time_limit(0);
		set_exception_handler(array($this,"handleException"));
		$res = $this->doApiCall($call, $params);
		echo json_encode($res);
		exit();
	}

	/**
	 * Handle the Api Response.
	 * Do logic here rather than in handleApiCall.
	 */
	public function doApiCall($call, $params){
		if (!array_key_exists("version",$params) || 
				$params["version"]!=RemoteSyncPlugin::instance()->getProtocolVersion())
			throw new Exception(
				"Your local version of wp-remote-sync is not compatible ".
				"with the version installed on this remote server. ".
				"Server version: ".RemoteSyncPlugin::instance()->getProtocolVersion()
			);

		if (!in_array($call,$this->calls))
			throw new Exception("Unknown api call: $call");

		$res=call_user_func(array($this,$call),$params);
		return $res;
	}
}
