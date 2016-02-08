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
				"revision"=>$syncResource->getLocalRevision()
			);

		return $res;
	}

	/**
	 * Get resource
	 */
	public function get($args) {
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

		return array(
			"slug"=>$resource->getSlug(),
			"revision"=>$resource->getLocalRevision(),
			"type"=>$resource->getType(),
			"data"=>$resource->getData(),
			"attachments"=>$attachmentData
		);
	}

	/**
	 * Add a resource.
	 */
	public function add($args) {
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

		$syncer->createResource($args["slug"],$data);

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
		$syncer->updateResource($resource->getSlug(),$data);
		$resource->processPostedAttachments();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Delete.
	 */
	public function del($args) {
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

	/**
	 * Handle the Api Response
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
