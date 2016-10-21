<?php

require_once __DIR__."/../utils/Singleton.php";
require_once __DIR__."/../log/WpCliLogger.php";
require_once __DIR__."/SyncJob.php";

/**
 * Remote?
 */
class WpCliController extends Singleton {

	private static $stateLabels=array(
		SyncResource::NEW_LOCAL=>"Locally created",
		SyncResource::NEW_REMOTE=>"Remotely created",
		SyncResource::DELETED_LOCAL=>"Locally deleted",
		SyncResource::DELETED_REMOTE=>"Remotely deleted",
		SyncResource::UPDATED_LOCAL=>"Locally updated",
		SyncResource::UPDATED_REMOTE=>"Remotely updated",
		SyncResource::CONFLICT=>"Updated on both servers",
	);

	/**
	 * Handle exception while syncing.
	 */
	function handleExceptionInSync($exception) {
		WP_CLI::error($exception->getMessage());
	}

	/**
	 * Sync resources on this WordPress installation with the remote.
	 * The remote needs to be set up in the settings page.
	 *
	 * ## OPTIONS
	 * [--resolve=<strategy>]
	 * : Strategy to use for resources that have been updated on both servers.
	 * Available options:
	 * none       - Leave resource for manual resolution.
	 * useLocal   - Upload local resource to remote.
	 * useRemote  - Download remote resource to local.
	 */
	function sync($args, $params) {
		RemoteSyncPlugin::instance()->setLogger(new WpCliLogger());
		set_exception_handler(array($this,"handleExceptionInSync"));

		$job=new SyncJob();

		foreach ($params as $param=>$value) {
			switch ($param) {
				case "resolve":
					$job->setResolutionStrategy($value);
					break;

				default:
					throw new Exception("Unknown param: ".$param);
					break;
			}
		}

		$job->run();

		WP_CLI::success("Up to date!");
	}

	/**
	 * Check status.
	 * Will compare resources on this server and the remote server
	 * and show a list of their statuses. The remote needs to be set up
	 * on the settings page.
	 */
	function status() {
		if (!trim(get_option("rs_remote_site_url"))) {
			WP_CLI::error("No remote set.");
			return;
		}

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

				WP_CLI::warning($syncer->getType().": No remote support.");
			}

			$syncResources=array_merge($syncResources,$resourcesForType);
		}

		$table=array();

		foreach ($syncResources as $syncResource) {
			$state=$syncResource->getState();
			if (self::$stateLabels[$state]) {
				$table[]=array(
					"slug"=>$syncResource->getUniqueSlug(),
					"state"=>self::$stateLabels[$state]
				);
			}
		}

		if ($table)
			WP_CLI\Utils\format_items("table",$table,array("slug","state"));

		else
			WP_CLI::log("Up to date!");
	}
}