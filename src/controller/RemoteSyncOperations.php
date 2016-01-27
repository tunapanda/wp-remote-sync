<?php

require_once __DIR__."/../model/RemoteResource.php";
require_once __DIR__."/../utils/EventStream.php";

/**
 * Handle user operations.
 */
class RemoteSyncOperations {

	private $plugin;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->operations=array("status","pull","push","sync");
	}

	/**
	 * Handle exception in api call.
	 */
	public function handleException($exception) {
		$this->log("** Error **");
		$this->log($exception->getMessage());

		RemoteSyncPlugin::instance()->getLogger()->done();
		exit();
	}

	/**
	 * Handle api call.
	 */
	public function handleOperation($operation) {
		set_exception_handler(array($this,"handleException"));

		$operation=strtolower($operation);
		if (!in_array($operation,$this->operations))
			throw new Exception("Unknown operation: ".$operation);

		call_user_func(array($this,$operation));

		$this->log("");
		$this->log("Done!");
	}

	/**
	 * Log.
	 */
	private function log($message) {
		RemoteSyncPlugin::instance()->getLogger()->log($message);
	}

	/**
	 * Sync.
	 */
	public function sync() {
		$this->pull();
		$this->push();
	}

	/**
	 * Status.
	 */
	public function status() {
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();

		foreach ($syncers as $syncer) {
			$this->log("Status: ".$syncer->getType());

			$syncResources=SyncResource::findAllForType(
				$syncer->getType(),
				SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
			);

			$newLocal=0;
			$newRemote=0;
			$deletedLocal=0;
			$deletedRemote=0;
			$updatedLocal=0;
			$updatedRemote=0;
			$needsMerge=0;

			foreach ($syncResources as $syncResource) {
				switch ($syncResource->getState()) {
					case SyncResource::NEW_LOCAL:
						$newLocal++;
						break;

					case SyncResource::NEW_REMOTE:
						$newRemote++;
						break;

					case SyncResource::DELETED_LOCAL:
						$deletedLocal++;
						break;

					case SyncResource::DELETED_REMOTE:
						$deletedRemote++;
						break;

					case SyncResource::UPDATED_LOCAL:
						$updatedLocal++;
						break;

					case SyncResource::UPDATED_REMOTE:
						$updatedRemote++;
						break;

					case SyncResource::CONFLICT:
						$needsMerge++;
						break;

					case SyncResource::GARBAGE:
					case SyncResource::UP_TO_DATE:
						break;
				}
			}

			if ($newLocal)
				$this->log("  New local items:        ".$newLocal);

			if ($newRemote)
				$this->log("  New remote items:       ".$newRemote);

			if ($deletedLocal)
				$this->log("  Deleted local items:    ".$deletedLocal);

			if ($deletedRemote)
				$this->log("  Deleted remote items:   ".$deletedRemote);

			if ($updatedLocal)
				$this->log("  Updated local items:    ".$updatedLocal);

			if ($updatedRemote)
				$this->log("  Updated remote items:   ".$updatedRemote);

			if ($needsMerge)
				$this->log("  Conflicting:            ".$needsMerge);
		}
	}

	/**
	 * Pull.
	 */
	public function pull() {
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();

		foreach ($syncers as $syncer) {
			$this->log("Pull: ".$syncer->getType());

			$syncResources=SyncResource::findAllForType(
				$syncer->getType(),
				SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
			);

			foreach ($syncResources as $syncResource) {
				switch ($syncResource->getState()) {
					case SyncResource::NEW_REMOTE:
						$syncResource->createLocalResource();

						try {
							$syncResource->downloadAttachments();
						}

						catch (Exception $e) {
							$syncResource->deleteLocalResource();
							throw $e;
						}

						$syncResource->save();
						$this->log("  ".$syncResource->getSlug().": Created local.");
						break;

					case SyncResource::UPDATED_REMOTE:
						$syncResource->updateLocalResource();
						$syncResource->downloadAttachments();
						$syncResource->save();
						$this->log("  ".$syncResource->getSlug().": Updated local.");
						break;

					case SyncResource::DELETED_REMOTE:
						$syncResource->deleteLocalResource();
						$syncResource->delete();
						$this->log("  ".$syncResource->getSlug().": Deleted local.");
						break;

					case SyncResource::GARBAGE:
						$syncResource->delete();
						break;

					case SyncResource::CONFLICT:
						switch (get_option("rs_merge_strategy")) {
							case "prioritize_local":
								$syncResource->baseRevision=$syncResource->getRemoteRevision();
								$syncResource->save();
								$this->log("  ".$syncResource->getSlug().": Conflict, using local.");
								break;

							case "prioritize_remote":
								$syncResource->updateLocalResource();
								$syncResource->save();
								$this->log("  ".$syncResource->getSlug().": Conflict, using remote.");
								break;

							default:
								$this->log("  ".$syncResource->getSlug().": Conflict, skipping.");
								break;
						}
						break;

					case SyncResource::UP_TO_DATE:
						$syncResource->save();
						break;
				}
			}
		}
	}

	/**
	 * Push.
	 */
	public function push() {
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();

		foreach ($syncers as $syncer) {
			$this->log("Push: ".$syncer->getType());

			$syncResources=SyncResource::findAllForType(
				$syncer->getType(),
				SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
			);

			foreach ($syncResources as $syncResource) {
				switch ($syncResource->getState()) {
					case SyncResource::NEW_REMOTE:
					case SyncResource::UPDATED_REMOTE:
					case SyncResource::DELETED_REMOTE:
						$this->log($syncResource->getSlug.": Remotely changed, please pull.");
						break;

					case SyncResource::NEW_LOCAL:
						$syncResource->createRemoteResource();
						$syncResource->save();
						$this->log("  ".$syncResource->getSlug().": Created remote.");
						break;

					case SyncResource::UPDATED_LOCAL:
						$syncResource->updateRemoteResource();
						$syncResource->save();
						$this->log("  ".$syncResource->getSlug().": Updated remote.");
						break;

					case SyncResource::DELETED_LOCAL:
						$syncResource->deleteRemoteResource();
						$syncResource->delete();
						$this->log("  ".$syncResource->getSlug().": Deleted remote.");
						break;

					case SyncResource::CONFLICT:
						$this->log("  ".$syncResource->getSlug().": Conflict, skipping.");
						break;

					case SyncResource::UP_TO_DATE:
						$syncResource->save();
						break;

					case SyncResource::GARBAGE:
						//echo "garbage!!!\n";
						$syncResource->delete();
						break;
				}
			}
		}
	}
}
