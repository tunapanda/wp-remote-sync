<?php

require_once __DIR__."/src/utils/WpUtil.php";
require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";

use remotesync\WpUtil;

require_once WpUtil::getWpLoadPath();
require_once ABSPATH.'wp-admin/includes/plugin.php';

$plugin=new RemoteSyncPlugin();
$plugin->getOperations()->handleOperation($_REQUEST["action"]);
