<?php

require_once __DIR__."/../utils/Singleton.php";
require_once __DIR__."/../syncers/PostSyncer.php";
require_once __DIR__."/../syncers/AttachmentSyncer.php";
require_once __DIR__."/../syncers/H5pSyncer.php";
require_once __DIR__."/../controller/RemoteSyncApi.php";
require_once __DIR__."/../controller/RemoteSyncOperations.php";
require_once __DIR__."/../utils/Curl.php";

/**
 * Remote sync plugin.
 */
class RemoteSyncPlugin extends Singleton {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->syncers=NULL;
		$this->api=NULL;
		$this->operations=NULL;
		$this->Curl="Curl";
		$this->job=NULL;

		$this->callMessage="Syncing...";
		$this->lastPrintedMessage="";
	}

	/**
	 * Get enabled syncer.
	 */
	public function getEnabledSyncers() {
		if (!$this->syncers) {
			$this->syncers=array(); 

			$syncerClasses=array(
				"PostSyncer",
				"AttachmentSyncer",
				"H5pSyncer"
			);

			foreach ($syncerClasses as $syncerClass) {
				$syncer=new $syncerClass();
				if ($syncer->isAvailable())
					$this->syncers[]=$syncer;
			}
		}

		return $this->syncers;
	}

	/**
	 * Get syncer for type.
	 */
	public function getSyncerByType($type) {
		foreach ($this->getEnabledSyncers() as $syncer)
			if ($syncer->getType()==$type)
				return $syncer;

		throw new Exception("Can't sync: ".$type);
	}

	/**
	 * Install.
	 */
	public function install() {
		SyncResource::install();

		$syncers=$this->getEnabledSyncers();

		foreach ($syncers as $syncer)
			$syncer->install();
	}

	/**
	 * Get reference to the api.
	 */
	public function getApi() {
		if (!$this->api)
			$this->api=new RemoteSyncApi();

		return $this->api;
	}

	/**
	 * Get reference to operations object.
	 */
	public function getOperations() {
		if (!$this->operations)
			$this->operations=new RemoteSyncOperations();

		return $this->operations;
	}

	/**
	 * Curl progress.
	 */
	public function remoteCallCurlProgress($id, $downTotal, $down, $upTotal, $up) {
		if (!$this->job)
			return;

		$percent=0;

		if ($upTotal && $up<$upTotal)
			$percent=round(100*$up/$upTotal);

		else if ($downTotal && $down<$downTotal)
			$percent=round(100*$down/$downTotal);

		$this->job->progressStatus($this->callMessage,$percent);
	}

	/**
	 * Set long run job.
	 */
	public function setLongRunJob($job) {
		$this->job=$job;
	}

	/**
	 * Set the message to show for next remote calls.
	 */
	public function setCallMessage($message) {
		$this->callMessage=$message;
	}

	/**
	 * Make a call to the remote.
	 */
	public function remoteCall($method, $args=array(), $attachments=array()) {
		$this->lastPrintedMessage="";

		if ($this->job)
			$this->job->status($this->callMessage);

		$args["action"]=$method;
		$args["key"]=get_option("rs_access_key");
		$url=get_option("rs_remote_site_url");
		if (!trim($url))
			throw new Exception("Remote site url not set");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";

		$curl=new $this->Curl($url);
		$curl->setopt(CURLOPT_RETURNTRANSFER,TRUE);
		$curl->setopt(CURLOPT_POST,1);
		$curl->setopt(CURLOPT_NOPROGRESS,FALSE);
		$curl->setopt(CURLOPT_PROGRESSFUNCTION,array($this,"remoteCallCurlProgress"));
		$postfields=$args;

		foreach ($attachments as $fieldname=>$filename) {
			$postfields[$fieldname]=new CurlFile(
				$filename,
				"text/plain",
				$fieldname
			);
		}

		$curl->setopt(CURLOPT_POSTFIELDS,$postfields);

		$res=$curl->exec();
		if ($curl->error())
			throw new Exception("Curl error: ".$curl->error());

		$returnCode=$curl->getinfo(CURLINFO_HTTP_CODE);
		$curl->close();

		if ($returnCode!=200)
			throw new Exception("Unexpected return code: ".$returnCode."\n".$res);

		//echo "curl res: ".$res;

		$parsedRes=json_decode($res,TRUE);

		if ($parsedRes===NULL)
			throw new Exception("Unable to parse json... ".$res);

		if (array_key_exists("Error", $parsedRes))
			throw new Exception($parsedRes["Error"]);

		if ($this->job)
			$this->job->status("");

		$this->callMessage="Syncing...";

		return $parsedRes;
	}	
}