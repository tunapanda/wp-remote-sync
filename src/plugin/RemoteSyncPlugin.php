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
		$this->logger=NULL;

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
	public function getLogger() {
		if (!$this->logger)
			throw new Exception("No logger installed");

		return $this->logger;
	}

	/**
	 * Get logger.
	 */
	public function setLogger($logger) {
		$this->logger=$logger;
	}

	/**
	 * Set the message to show for next remote calls.
	 */
	public function setCallMessage($message) {
		$this->callMessage=$message;
	}

	/**
	 * Create a remote call.
	 */
	public function remoteCall($action) {
		$url=trim(get_option("rs_remote_site_url"));

		if (!$url)
			throw new Exception("No remote set.");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";
		$curl=new Curl($url);

		$curl->setResultDecoding(Curl::JSON);
		$curl->addPostField("action",$action);
		$curl->addPostField("key",trim(get_option("rs_access_key")));

		return $curl;
	}
}