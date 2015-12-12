<?php

require_once __DIR__."/../utils/LongRunJob.php";
require_once __DIR__."/../model/RemoteResource.php";

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
		$link=get_site_url()."/wp-admin/options-general.php?page=rs_settings";
		$afterText="<hr/><a href='$link' class='button'>Back</a>";

		$this->job=new LongRunJob();
		$this->job->setAfterText($afterText);
		$this->job->start();

		set_exception_handler(array($this,"handleException"));

		$operation=strtolower($operation);
		if (!in_array($operation,$this->operations))
			throw new Exception("Unknown operation: ".$operation);

		call_user_func(array($this,$operation));

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

			$syncResources=$syncer->getSyncResourcesByGlobalId();
			$remoteResources=$syncer->getRemoteResourcesByGlobalId();

			$newLocal=0;
			$newRemote=0;
			$deletedLocal=0;
			$deletedRemote=0;
			$updatedLocal=0;
			$updatedRemote=0;
			$needsMerge=0;

			foreach ($syncResources as $syncResource) {
				if (!$syncResource->baseRevision)
					$newLocal++;

				else if ($syncResource->isDeleted())
					$deletedLocal++;

				else if (!$remoteResources[$syncResource->globalId])
					$deletedRemote++;

				else if ($syncResource->revision!=$syncResource->baseRevision)
					$updatedLocal++;
			}

			foreach ($remoteResources as $remoteResource) {
				$syncResource=$syncResources[$remoteResource->globalId];

				if (!$syncResource)
					$newRemote++;

				else if ($remoteResource->revision!=$syncResource->baseRevision) {
					$updatedRemote++;

					if ($syncResource->revision!=$syncResource->baseRevision)
						$needsMerge++;
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
				$this->job->log("  Needs merge:            ".$needsMerge);
		}
	}

	/**
	 * Pull.
	 */
	public function pull() {
		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();

		foreach ($syncers as $syncer) {
			$this->job->log("Pull: ".$syncer->getType());
			$syncer->setActOnLocalChange(FALSE);

			$remoteResources=$syncer->getRemoteResourcesByGlobalId();
			foreach ($remoteResources as $remoteResource) {
				$localResource=SyncResource::findOneBy("globalId",$remoteResource->globalId);

				// Skip if it is locally deleted.
				if ($localResource && $localResource->isDeleted()) {
					//echo "it is locally deleted!";
				}

				// Exists locally
				else if ($localResource) {
					$localId=$localResource->localId;

					// Remotely changed
					if ($localResource->baseRevision!=$remoteResource->revision) {
						$label=$remoteResource->getLabel();

						// Merge?
						if ($localResource->isLocallyModified()) {
							$merged=$syncer->mergeResourceData(
								$localResource->getBaseData(),
								$localResource->getData(),
								$remoteResource->getData()
							);

							$syncer->updateResource($localId,$merged);
							$syncer->processAttachments($localId);
							$localResource->baseRevision=$remoteResource->revision;
							$localResource->save();
							$this->job->log("* M {$remoteResource->globalId} $localId $label");
						}

						else {
							$syncer->updateResource($localId,$remoteResource->getData());
							$syncer->processAttachments($localId);
							$localResource->baseRevision=$remoteResource->revision;
							$localResource->revision=$remoteResource->revision;
							$localResource->save();
							$this->job->log("* U {$remoteResource->globalId} $localId $label");
						}
					}
				}

				// Doesn't exist locally.
				else {
					$localId=$syncer->createResource($remoteResource->getData());
					$syncer->processAttachments($localId);
					$localResource=new SyncResource($syncer->getType());
					$localResource->localId=$localId;
					$localResource->globalId=$remoteResource->globalId;
					$localResource->revision=$remoteResource->revision;
					$localResource->baseRevision=$remoteResource->revision;
					$localResource->baseData=json_encode($remoteResource->getData());
					$localResource->save();

					$label=$remoteResource->getLabel();

					$this->job->log("* A {$remoteResource->globalId} $localId $label");
				}
			}

			$localResources=$syncer->getSyncResources();
			foreach ($localResources as $localResource) {
				$globalId=$localResource->globalId;
				if ($localResource->baseRevision && !$remoteResources[$globalId]) {
					$label=$localResource->getLabel();

					$syncer->deleteResource($localResource->localId);
					$localResource->delete();

					$this->job->log("* D {$localResource->globalId} {$localResource->localId} $label");
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
			$syncer->setActOnLocalChange(FALSE);

			$syncResources=$syncer->getSyncResources();
			foreach ($syncResources as $syncResource) {
				if ($syncResource->isDeleted()) {
					if ($syncResource->baseRevision) {
						RemoteSyncPlugin::instance()->remoteCall("del",array(
							"globalId"=>$syncResource->globalId
						));
					}

					$syncResource->delete();
					$this->job->log("* D {$syncResource->globalId} {$syncResource->localId}");
				}

				else if ($syncResource->isNew()) {
					$data=$syncResource->getData();
					$label=$syncer->getResourceLabel($data);

					if (!$syncResource->revision)
						throw new Exception("Local data doesn't have a revision!");

					RemoteSyncPlugin::instance()->remoteCall("add",array(
						"globalId"=>$syncResource->globalId,
						"revision"=>$syncResource->revision,
						"data"=>json_encode($data),
						"type"=>$syncResource->type
					),$syncResource->getResourceAttachments());

					$syncResource->baseRevision=$syncResource->revision;
					$syncResource->setBaseData($syncResource->getData());
					$syncResource->save();

					$this->job->log("* A {$syncResource->globalId} {$syncResource->localId} $label");
				}

				else if ($syncResource->isLocallyModified()) {
					$data=$syncResource->getData();
					$label=$syncer->getResourceLabel($data);

					RemoteSyncPlugin::instance()->remoteCall("put",array(
						"globalId"=>$syncResource->globalId,
						"revision"=>$syncResource->revision,
						"baseRevision"=>$syncResource->baseRevision,
						"data"=>json_encode($data)
					),$syncResource->getResourceAttachments());

					$syncResource->baseRevision=$syncResource->revision;
					$syncResource->setBaseData($syncResource->getData());
					$syncResource->save();

					$this->job->log("* U {$syncResource->globalId} {$syncResource->localId} $label");
				}
			}
		}
	}
}
