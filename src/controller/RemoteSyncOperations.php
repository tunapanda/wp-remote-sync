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
		$this->job->log("** Error **");
		$this->job->log($exception->getMessage());
		$this->job->done();
		exit();
	}

	/**
	 * Handle api call.
	 */
	public function handleOperation($operation) {
		$this->job=new EventStream();
		$this->job->start();

		RemoteSyncPlugin::instance()->setLongRunJob($this->job);

		set_exception_handler(array($this,"handleException"));

		$operation=strtolower($operation);
		if (!in_array($operation,$this->operations))
			throw new Exception("Unknown operation: ".$operation);

		call_user_func(array($this,$operation));

		$this->job->log("");
		$this->job->log("Done!");
		$this->job->done();
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
			$this->job->log("Status: ".$syncer->getType());

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
				$this->job->log("  New local items:        ".$newLocal);

			if ($newRemote)
				$this->job->log("  New remote items:       ".$newRemote);

			if ($deletedLocal)
				$this->job->log("  Deleted local items:    ".$deletedLocal);

			if ($deletedRemote)
				$this->job->log("  Deleted remote items:   ".$deletedRemote);

			if ($updatedLocal)
				$this->job->log("  Updated local items:    ".$updatedLocal);

			if ($updatedRemote)
				$this->job->log("  Updated remote items:   ".$updatedRemote);

			if ($needsMerge)
				$this->job->log("  Conflicting:            ".$needsMerge);
		}
	}

	/**
	 * Pull.
	 */
	public function pull() {
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();

		foreach ($syncers as $syncer) {
			$this->job->log("Pull: ".$syncer->getType());

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
						$this->job->log("  ".$syncResource->getSlug().": Created local.");
						break;

					case SyncResource::UPDATED_REMOTE:
						$syncResource->updateLocalResource();
						$syncResource->downloadAttachments();
						$syncResource->save();
						$this->job->log("  ".$syncResource->getSlug().": Updated local.");
						break;

					case SyncResource::DELETED_REMOTE:
						$syncResource->deleteLocalResource();
						$syncResource->delete();
						$this->job->log("  ".$syncResource->getSlug().": Deleted local.");
						break;

					case SyncResource::GARBAGE:
						$syncResource->delete();
						break;

					case SyncResource::CONFLICT:
						switch (get_option("rs_merge_strategy")) {
							case "prioritize_local":
								$syncResource->baseRevision=$syncResource->getRemoteRevision();
								$syncResource->save();
								$this->job->log("  ".$syncResource->getSlug().": Conflict, using local.");
								break;

							case "prioritize_remote":
								$syncResource->updateLocalResource();
								$syncResource->save();
								$this->job->log("  ".$syncResource->getSlug().": Conflict, using remote.");
								break;

							default:
								$this->job->log("  ".$syncResource->getSlug().": Conflict, skipping.");
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
			$this->job->log("Push: ".$syncer->getType());

			$syncResources=SyncResource::findAllForType(
				$syncer->getType(),
				SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
			);

			foreach ($syncResources as $syncResource) {
				switch ($syncResource->getState()) {
					case SyncResource::NEW_REMOTE:
					case SyncResource::UPDATED_REMOTE:
					case SyncResource::DELETED_REMOTE:
						$this->job($syncResource->getSlug.": Remotely changed, please pull.");
						break;

					case SyncResource::NEW_LOCAL:
						$syncResource->createRemoteResource();
						$syncResource->save();
						$this->job->log("  ".$syncResource->getSlug().": Created remote.");
						break;

					case SyncResource::UPDATED_LOCAL:
						$syncResource->updateRemoteResource();
						$syncResource->save();
						$this->job->log("  ".$syncResource->getSlug().": Updated remote.");
						break;

					case SyncResource::DELETED_LOCAL:
						$syncResource->deleteRemoteResource();
						$syncResource->delete();
						$this->job->log("  ".$syncResource->getSlug().": Deleted remote.");
						break;

					case SyncResource::CONFLICT:
						$this->job->log("  ".$syncResource->getSlug().": Conflict, skipping.");
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
