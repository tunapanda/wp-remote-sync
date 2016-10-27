<?php

require_once __DIR__."/../utils/Singleton.php";
require_once __DIR__."/../syncers/PostSyncer.php";
require_once __DIR__."/../syncers/AttachmentSyncer.php";
require_once __DIR__."/../syncers/H5pSyncer.php";
require_once __DIR__."/../syncers/PluggableSyncer.php";
require_once __DIR__."/../syncers/TaxonomySyncer.php";
require_once __DIR__."/../controller/RemoteSyncApi.php";
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
		$this->logger=NULL;

		$this->callMessage="Syncing...";
		$this->lastPrintedMessage="";

		$this->protocolVersion=4;
	}

	/**
	 * Get enabled syncer.
	 */
	public function getEnabledSyncers() {
		if (!$this->syncers) {
			$this->syncers=array(); 

			$syncerClasses=array(
				"TaxonomySyncer",
				"PostSyncer",
				"AttachmentSyncer",
				"H5pSyncer"
			);

			foreach ($syncerClasses as $syncerClass) {
				$syncer=new $syncerClass();
				if ($syncer->isAvailable())
					$this->syncers[]=$syncer;
			}

			$pluggableSyncers=apply_filters("remote-syncers",array());
			foreach ($pluggableSyncers as $pluggableSyncer)
				$this->syncers[]=new PluggableSyncer($pluggableSyncer);
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

		throw new Exception("Resource type not enabled");
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

		$this->logger->taskProgress($percent);
	}

	/**
	 * Get protocol version.
	 */
	public function getProtocolVersion() {
		return $this->protocolVersion;
	}

	/**
	 * Create a remote call.
	 */
	public function remoteCall($action, $message="Please wait") {
		if ($this->logger)
			$this->logger->task($message);

		$url=trim(get_option("rs_remote_site_url"));

		if (!$url)
			throw new Exception("No remote set.");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";
		$curl=new Curl($url);

		$curl->setResultDecoding(Curl::JSON);
		$curl->addPostField("action",$action);
		$curl->addPostField("key",trim(get_option("rs_access_key")));
		$curl->addPostField("version",$this->protocolVersion);
		$curl->setPercentFunc(array($this,"onCurlPercent"));

		return $curl;
	}
}