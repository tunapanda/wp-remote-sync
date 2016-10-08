<?php

require_once __DIR__."/../../src/utils/Template.php";

use remotesync\Template;

$resources=array(
	"Post"=>array(
		array(
			"slug"=>"my-post",
			"stateLabel"=>"Locally created",
			"actions"=>array("action1"=>"Action 1")
		),

		array(
			"slug"=>"my-other-post",
			"stateLabel"=>"Remotely created",
			"actions"=>array("action1"=>"Action 1")
		),
	),

	"Attachment"=>array(
		array(
			"slug"=>"an-image",
			"stateLabel"=>"Locally deleted",
			"actions"=>array("action1"=>"Action 1")
		),

		array(
			"slug"=>"another-image",
			"stateLabel"=>"Remotely deleted",
			"actions"=>array("action1"=>"Action 1")
		),

		array(
			"slug"=>"a-third-image",
			"stateLabel"=>"Locally updated",
			"actions"=>array("action1"=>"Action 1")
		),

		array(
			"slug"=>"a-forth-image",
			"stateLabel"=>"Remotely updated",
			"actions"=>array("action1"=>"Action 1")
		),

		array(
			"slug"=>"something else",
			"stateLabel"=>"Updated on both servers",
			"actions"=>array("action1"=>"Action 1","action2"=>"Action 2")
		),
	),
);

$params=array(
	"resources"=>$resources
);

Template::display(__DIR__."/../../tpl/resourcelist.tpl.php",$params);
