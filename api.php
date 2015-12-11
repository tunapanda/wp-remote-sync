<?php

require_once __DIR__."/src/utils/WpUtil.php";
require_once __DIR__."/src/plugin/RemoteSyncPlugin.php";

use remotesync\WpUtil;

require_once WpUtil::getWpLoadPath();

$plugin=new RemoteSyncPlugin();
$plugin->getApi()->handleApiCall($_REQUEST["action"],$_REQUEST);
