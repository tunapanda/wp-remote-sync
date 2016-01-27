<?php

if (isset($_REQUEST["json"])) {
	echo json_encode(array(
		"_REQUEST"=>$_REQUEST,
		"_FILES"=>$_FILES
	));
	exit();
}

print_r($_REQUEST);
print_r($_FILES);