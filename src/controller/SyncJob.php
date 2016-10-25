<?php

/**
 * A non interactive sync job.
 */
class SyncJob {

	private static $stateLabels=array(
		SyncResource::NEW_LOCAL=>"Locally created.",
		SyncResource::NEW_REMOTE=>"Remotely created.",
		SyncResource::DELETED_LOCAL=>"Locally deleted.",
		SyncResource::DELETED_REMOTE=>"Remotely deleted.",
		SyncResource::UPDATED_LOCAL=>"Locally updated.",
		SyncResource::UPDATED_REMOTE=>"Remotely updated.",
		SyncResource::CONFLICT=>"Updated on both servers.",
		SyncResource::GARBAGE=>"Garbage.",
		SyncResource::UP_TO_DATE=>"Up to date.",
	);

	private $resolutionStrategy;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setResolutionStrategy(NULL);
	}

	/**
	 * Set resolution strategy.
	 */
	public function setResolutionStrategy($resolutionStrategy) {
		if ($resolutionStrategy==NULL)
			$resolutionStrategy="none";

		$this->resolutionStrategy=$resolutionStrategy;

		$strategies=array("none","useRemote","useLocal");
		if (!in_array($this->resolutionStrategy,$strategies))
			throw new Exception(
				"Unknown resolution strategy: ".
				$this->resolutionStrategy
			);
	}

	/**
	 * Get sync resources.
	 */
	public function getSyncResources() {
		$logger=RemoteSyncPlugin::instance()->getLogger();

		$syncResources=array();
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();
		foreach ($syncers as $syncer) {
			$resourcesForType=array();
			try {
				$resourcesForType=SyncResource::findAllForType(
					$syncer->getType(),
					SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
				);
			}

			catch (Exception $e) {
				if ($e->getMessage()!="Resource type not enabled")
					throw $e;

				$logger->log($syncer->getType().": No remote support.");
			}

			$syncResources=array_merge($syncResources,$resourcesForType);
		}

		return $syncResources;
	}

	/**
	 * Run sync.
	 */
	public function run() {
		$logger=RemoteSyncPlugin::instance()->getLogger();
		if (!$logger)
			throw new Exception("SyncJob needs a logger.");

		$logger->log("Checking resources on remote site...");
		$syncResources=$this->getSyncResources();

		$upToDate=0;
		foreach ($syncResources as $syncResource) {
			$state=$syncResource->getState();
			if ($state==SyncResource::UP_TO_DATE)
				$upToDate++;
		}

		$logger->log("Total number of resources: ".sizeof($syncResources));
		$logger->log("Up to date: ".$upToDate);
		$logger->log("");

		foreach ($syncResources as $syncResource) {
			$state=$syncResource->getState();
			$stateLabel=self::$stateLabels[$state];

			if ($state!=SyncResource::UP_TO_DATE)
				$logger->log($syncResource->getUniqueSlug().": ".$stateLabel);

			switch ($syncResource->getState()) {
				case SyncResource::NEW_LOCAL:
					$syncResource->createRemoteResource();
					$logger->log($syncResource->getUniqueSlug().": Created remote resource.");
					break;

				case SyncResource::NEW_REMOTE:
					$syncResource->createLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Created local resource.");
					break;

				case SyncResource::DELETED_LOCAL:
					$syncResource->deleteRemoteResource();
					$logger->log($syncResource->getUniqueSlug().": Deleted remote resource.");
					break;

				case SyncResource::DELETED_REMOTE:
					$syncResource->deleteLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Deleted local resource.");
					break;

				case SyncResource::UPDATED_LOCAL:
					$syncResource->updateRemoteResource();
					$logger->log($syncResource->getUniqueSlug().": Uploaded to remote.");
					break;

				case SyncResource::UPDATED_REMOTE:
					$syncResource->updateLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Downloaded to local.");
					break;

				case SyncResource::CONFLICT:
					switch ($this->resolutionStrategy) {
						case "none":
							$logger->log($syncResource->getUniqueSlug().": Leaving for manual resolution.");
							break;

						case "useRemote":
							$syncResource->updateLocalResource();
							$logger->log(
								$syncResource->getUniqueSlug().
								": Resolved by downloading remote version."
							);
							break;

						case "useLocal":
							$syncResource->updateRemoteResource();
							$logger->log(
								$syncResource->getUniqueSlug().
								": Resolved by uploading local version to remote."
							);
							break;

						default:
							throw new Exception("Unknown resolution strategy.");
							break;
					}
					break;

				case SyncResource::GARBAGE:
					$syncResource->delete();
					break;

				case SyncResource::UP_TO_DATE:
					break;

				default:
					throw new Exception("Can't handle this state: ".$syncResource->getState());
					break;
			}
		}
	}

	/**
	 * Revert.
	 */
	public function revert() {
		$logger=RemoteSyncPlugin::instance()->getLogger();
		if (!$logger)
			throw new Exception("SyncJob needs a logger.");

		$logger->log("Checking resources on remote site...");
		$syncResources=$this->getSyncResources();

		foreach ($syncResources as $syncResource) {
			$state=$syncResource->getState();
			$stateLabel=self::$stateLabels[$state];

			if ($state!=SyncResource::UP_TO_DATE)
				$logger->log($syncResource->getUniqueSlug().": ".$stateLabel);

			switch ($syncResource->getState()) {
				case SyncResource::NEW_LOCAL:
					$syncResource->deleteLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Deleted local resource.");
					break;

				case SyncResource::NEW_REMOTE:
					$syncResource->createLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Created local resource.");
					break;

				case SyncResource::DELETED_LOCAL:
					$syncResource->createLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Restored local resource.");
					break;

				case SyncResource::DELETED_REMOTE:
					$syncResource->deleteLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Deleted local resource.");
					break;

				case SyncResource::UPDATED_LOCAL:
					$syncResource->updateLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Downloaded to local.");
					break;

				case SyncResource::UPDATED_REMOTE:
					$syncResource->updateLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Downloaded to local.");
					break;

				case SyncResource::CONFLICT:
					$syncResource->updateLocalResource();
					$logger->log($syncResource->getUniqueSlug().": Downloaded to local.");
					break;

				case SyncResource::GARBAGE:
					$syncResource->delete();
					break;

				case SyncResource::UP_TO_DATE:
					break;

				default:
					throw new Exception("Can't handle this state: ".$syncResource->getState());
					break;
			}
		}
	}
}