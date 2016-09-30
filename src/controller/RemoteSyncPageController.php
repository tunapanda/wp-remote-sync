<?php

require_once __DIR__."/../../src/utils/Template.php";
require_once __DIR__."/../../src/model/SyncResource.php";

use remotesync\Template;

/**
 * Display variaous pages.
 */
class RemoteSyncPageController {

	/**
	 * Handle exception while listing resources.
	 */
	function handleExceptionInResourceList($exception) {
		$this->errorMessage=$exception->getMessage();
		// echo $exception->getTraceAsString());
		$this->showMainPage();
	}

	/**
	 * Show the sync job page.
	 */
	function showSync() {
		$params=array();
		Template::print(__DIR__."/../../tpl/sync.tpl.php",$params);
	}

	/**
	 * Show the list of syncable resources.
	 */
	function showSyncPreview() {
		set_exception_handler(array($this,"handleExceptionInResourceList"));

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

	/**
	 * Show the main page.
	 */
	function showMain() {
		$tab=$_REQUEST["tab"];
		if (!$tab)
			$tab="sync";

		$params=array(
			"tab"=>$tab
		);

		$options=array(
			"rs_remote_site_url",
			"rs_access_key",
			"rs_incoming_access_key"
		);

		if ($this->errorMessage)
			$params["error"]=$this->errorMessage;

		foreach ($options as $option) {
			if (isset($_REQUEST[$option])) {
				update_option($option,$_REQUEST[$option]);
				$params["message"]="Settings saved.";
			}
		}

		Template::print(__DIR__."/../../tpl/main.tpl.php",$params);
	}
}