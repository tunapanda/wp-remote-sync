<?php

require_once __DIR__."/utils.php";

function rsPull() {
	rsJobLog("Pulling remote changes...");

	rsRemoteCall("list");
}