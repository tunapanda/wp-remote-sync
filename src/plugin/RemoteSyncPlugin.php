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
	 * Curl progress.
	 */
	public function onCurlPercent($percent) {
		if (!$this->logger)
			return;

		if ($percent)
			$percent.="%";

		else
			$percent="";

		$this->logger->status($this->remoteCallStatus." ".$percent);
	}

	/**
	 * Create a remote call.
	 */
	public function remoteCall($action, $message="Please wait...") {
		$this->remoteCallStatus=$message;

		if ($this->logger)
			$this->logger->status($this->remoteCallStatus);

		$url=trim(get_option("rs_remote_site_url"));

		if (!$url)
			throw new Exception("No remote set.");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";
		$curl=new Curl($url);

		$curl->setResultDecoding(Curl::JSON);
		$curl->addPostField("action",$action);
		$curl->addPostField("key",trim(get_option("rs_access_key")));
		$curl->setPercentFunc(array($this,"onCurlPercent"));

		return $curl;
	}
}