<?php
namespace rs;

require_once __DIR__."/../utils/Singleton.php";

use \Singleton;
use \WP_CLI;
use \RemoteSyncPlugin;
use \Exception;
use \SyncResource;

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
	 * Sync.
	 */
	function sync() {
		WP_CLI::error("Not yet implemented.");
	}

	/**
	 * Check status.
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