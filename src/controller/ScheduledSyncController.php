<?php

require_once __DIR__."/../utils/Singleton.php";
require_once __DIR__."/../log/FileLogger.php";
require_once __DIR__."/SyncJob.php";

/**
 * Handle scheduled syncs.
 */
class ScheduledSyncController extends Singleton {

	/**
	 * Handle exception while syncing.
	 */
	function handleExceptionInSync($exception) {
		$logger=RemoteSyncPlugin::instance()->getLogger();
		$logger->log($exception->getMessage());
		$logger->log($exception->getTraceAsString());
		$logger->done();
	}

	/**
	 * Run.
	 */
	public function run() {
		$upload_dir=wp_upload_dir();
		$upload_base_dir=$upload_dir["basedir"];
		$log_file=$upload_base_dir."/wp-remote-sync.log";

		if (file_exists($log_file))
			unlink($log_file);

		RemoteSyncPlugin::instance()->setLogger(new FileLogger($log_file));
		set_exception_handler(array($this,"handleExceptionInSync"));

		$logger=RemoteSyncPlugin::instance()->getLogger();
		$logger->log("Scheduled sync started...");

		$job=new SyncJob();
		$job->setResolutionStrategy(get_option("rs_resulotion_strategy"));
		$job->run();

		$logger=RemoteSyncPlugin::instance()->getLogger();
		$logger->log("");
		$logger->log("Done!");
		$logger->done();
	}
}