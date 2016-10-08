<?php

require_once __DIR__."/../../src/utils/Template.php";

use remotesync\Template;

$resources=array(
	"Post"=>array(
		array(
			"slug"=>"my-post",
			"stateLabel"=>"Locally created",
			"actionLabel"=>"Upload locate version to remote"
		),

		array(
			"slug"=>"my-other-post",
			"stateLabel"=>"Remotely created",
			"actionLabel"=>"Download remote version to local"
		),
	),

	"Attachment"=>array(
		array(
			"slug"=>"an-image",
			"stateLabel"=>"Locally deleted",
			"actionLabel"=>"Delete remote version too"
		),

		array(
			"slug"=>"another-image",
			"stateLabel"=>"Remotely deleted",
			"actionLabel"=>"Delete local version too"
		),

		array(
			"slug"=>"a-third-image",
			"stateLabel"=>"Locally updated",
			"actionLabel"=>"Upload locate version to remote"
		),

		array(
			"slug"=>"a-forth-image",
			"stateLabel"=>"Remotely updated",
			"actionLabel"=>"Download remote version to local"
		),

		array(
			"slug"=>"something else",
			"stateLabel"=>"Updated on both servers",
			"conflict"=>TRUE
		),
	),
);

$params=array(
	"resources"=>$resources
);

Template::display(__DIR__."/../../tpl/resourcelist.tpl.php",$params);
