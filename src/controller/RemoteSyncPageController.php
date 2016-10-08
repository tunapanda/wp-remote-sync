<?php

require_once __DIR__."/../../src/utils/Template.php";
require_once __DIR__."/../../src/model/SyncResource.php";
require_once __DIR__."/../../src/utils/ApacheUtil.php";
require_once __DIR__."/../../src/utils/JobOutputLogger.php";

use remotesync\Template;

/**
 * Display variaous pages.
 */
class RemoteSyncPageController {

	private static $stateLabels=array(
		SyncResource::NEW_LOCAL=>"Locally created",
		SyncResource::NEW_REMOTE=>"Remotely created",
		SyncResource::DELETED_LOCAL=>"Locally deleted",
		SyncResource::DELETED_REMOTE=>"Remotely deleted",
		SyncResource::UPDATED_LOCAL=>"Locally updated",
		SyncResource::UPDATED_REMOTE=>"Remotely updated",
		SyncResource::CONFLICT=>"Updated on both servers",
	);

	private static $applicableActions=array(
		SyncResource::NEW_LOCAL=>array("createOnRemote"),
		SyncResource::NEW_REMOTE=>array("createOnLocal"),
		SyncResource::DELETED_LOCAL=>array("deleteOnRemote"),
		SyncResource::DELETED_REMOTE=>array("deleteOnLocal"),
		SyncResource::UPDATED_LOCAL=>array("upload"),
		SyncResource::UPDATED_REMOTE=>array("download"),
		SyncResource::CONFLICT=>array("download","upload")
	);

	private static $actionLabels=array(
		"createOnRemote"=>"Upload local, create on remote",
		"createOnLocal"=>"Download remote, create on local",
		"deleteOnRemote"=>"Delete remote version too",
		"deleteOnLocal"=>"Delete local version too",
		"upload"=>"Upload local version to remote",
		"download"=>"Download remote version to local",
	);

	/**
	 * Handle exception while listing resources.
	 */
	function handleExceptionInResourceList($exception) {
		$this->errorMessage=$exception->getMessage();
		$this->errorMessage.="<br>".nl2br($exception->getTraceAsString());
		$this->showMain();
	}

	/**
	 * Handle exception while syncing.
	 */
	function handleExceptionInSync($exception) {
		$logger=RemoteSyncPlugin::instance()->getLogger();
		$logger->message($exception->getMessage());
		$logger->message($exception->getTraceAsString());
		$logger->done();
	}

	/**
	 * Show the sync job page.
	 */
	function showSync() {
		$params=array();
		Template::print(__DIR__."/../../tpl/sync.tpl.php",$params);

		ApacheUtil::disableBuffering();
		$logger=new JobOutputLogger();
		RemoteSyncPlugin::instance()->setLogger($logger);
		set_exception_handler(array($this,"handleExceptionInSync"));

		$uniqueSlugs=$_REQUEST["slugs"];
		if (!$uniqueSlugs)
			$uniqueSlugs=array();

		if (!$uniqueSlugs)
			throw new Exception("Nothing to sync!");

		$syncResources=SyncResource::findAllEnabled(
			SyncResource::POPULATE_LOCAL|SyncResource::POPULATE_REMOTE
		);

		foreach ($syncResources as $syncResource) {
			if (in_array($syncResource->getUniqueSlug(),$uniqueSlugs)) {
				$logger->status("Syncing: ".$syncResource->getUniqueSlug());

				$state=$syncResource->getState();
				$action=$_REQUEST["action"][$syncResource->getUniqueSlug()];
				$actionLabel=self::$actionLabels[$action];
				$applicableActions=self::applicableActions[$state];

				if (!in_array($action,$applicableActions))
					throw new Exception(sprintf("%s: Action '%s' not applicable in state '%s', expected: %s",
							$syncResource->getSlug(),
							self::$actionLabels[$action][$action],
							self::$stateLabels[$state],
							join(",",$applicableActions)
						));

				switch ($action) {
					case "createOnLocal":
						$syncResource->createLocalResource();
						break;

					case "createOnRemote":
						$syncResource->createRemoteResource();
						break;

					case "deleteOnLocal":
						$syncResource->deleteLocalResource();
						break;

					case "deleteOnRemote":
						$syncResource->deleteRemoteResource();
						break;

					case "download":
						$syncResource->updateLocalResource();
						break;

					// what to do about the base rev? we can't just upload if there is a conflict...
					case "upload":
						$syncResource->updateRemoteResource();
						break;

					default:
						throw new Exception("Unknown action: ".$action);
						break;
				}

				$logger->message($action.": ".$syncResource->getUniqueSlug());
			}
		}

		$logger->message("");
		$logger->message("Done!");
		$logger->done();
	}

	/**
	 * Show the list of syncable resources.
	 */
	function showSyncPreview() {
		set_exception_handler(array($this,"handleExceptionInResourceList"));

		Template::print(__DIR__."/../../tpl/resourcelist_loading.tpl.php",$params);

		ApacheUtil::disableBuffering();

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

				if ($state==SyncResource::GARBAGE) {
					$syncResource->delete();
				}

				else if ($state!=SyncResource::UP_TO_DATE) {
					$actions=array();
					foreach (self::$applicableActions[$state] as $action) {
						$actions[$action]=self::$actionLabels[$action];
					}

					if (!$actions)
						throw new Exception("No actions to apply.");

					if (self::$stateLabels[$state]) {
						$resourceViewData=array(
							"uniqueSlug"=>$syncResource->getUniqueSlug(),
							"slug"=>$syncResource->getSlug(),
							"stateLabel"=>self::$stateLabels[$state],
							"actions"=>$actions
						);

						$resourceViewDatas[]=$resourceViewData;
					}
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