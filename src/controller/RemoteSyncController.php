<?php

require_once __DIR__."/../../src/utils/Template.php";
require_once __DIR__."/../../src/model/SyncResource.php";

use remotesync\Template;

/**
 * Display variaous pages.
 */
class RemoteSyncController {

	/**
	 * Show the list of syncable resources.
	 */
	function showResourceList() {
		$stateLabels=array(
			SyncResource::NEW_LOCAL=>"Locally created",
			SyncResource::NEW_REMOTE=>"Remotely created",
			SyncResource::DELETED_LOCAL=>"Locally deleted",
			SyncResource::DELETED_REMOTE=>"Remotely deleted",
			SyncResource::UPDATED_LOCAL=>"Locally updated",
			SyncResource::UPDATED_REMOTE=>"Remotely updated",
			SyncResource::CONFLICT=>"Updated on both servers",
		);

		$actionLabels=array(
			SyncResource::NEW_LOCAL=>"Upload locate version to remote",
			SyncResource::NEW_REMOTE=>"Download remote version to local",
			SyncResource::DELETED_LOCAL=>"Delete remote version too",
			SyncResource::DELETED_REMOTE=>"Delete local version too",
			SyncResource::UPDATED_LOCAL=>"Upload locate version to remote",
			SyncResource::UPDATED_REMOTE=>"Download remote version to local",
		);

		$syncers=RemoteSyncPlugin::instance()->getEnabledSyncers();
		$resourceViewCategoryDatas=array();

		foreach ($syncers as $syncer) {
			$syncResources=SyncResource::findAllForType(
				$syncer->getType(),
				SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
			);

			$resourceViewDatas=array();

			foreach ($syncResources as $syncResource) {
				$state=$syncResource->getState();
				if ($stateLabels[$state]) {
					$resourceViewData=array(
						"slug"=>$syncResource->getSlug(),
						"stateLabel"=>$stateLabels[$state],
						"conflict"=>FALSE
					);

					if ($state==SyncResource::CONFLICT)
						$resourceViewData["conflict"]=TRUE;

					else
						$resourceViewData["actionLabel"]=$actionLabels[$state];

					$resourceViewDatas[]=$resourceViewData;
				}
			}

			if ($resourceViewDatas) {
				$typeLabel=ucfirst($syncer->getType());
				$resourceViewCategoryDatas[$typeLabel]=$resourceViewDatas;
			}
		}

		$params=array(
			"resources"=>$resourceViewCategoryDatas
		);

		Template::print(__DIR__."/../../tpl/resourcelist.tpl.php",$params);
	}
}