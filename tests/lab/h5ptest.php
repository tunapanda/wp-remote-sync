<?php

	require_once __DIR__."/../../src/utils/WpUtil.php";
	require_once remotesync\WpUtil::getWpLoadPath();
	require_once __DIR__."/../../src/utils/H5pUtil.php";

	// This is not a proper unit test, because it relies on the H5P plugin
	// to be installed on the system where it runs.
	// The intended way to run this test, is to cd into the wp-remote-sync
	// directory, when this directory is installed into the plugin plugin
	// directory of a WordPress instance. This WordPress instance needs to
	// have H5P installed as well. Then run:
	//
	//   php test/lab/h5ptest.php
	//
	// From the plugin directory.

	H5pUtil::saveH5p("my-h5p",__DIR__."/testing-with-image.h5p","This is my H5P");
	//H5pUtil::insertH5p("my-h5p",__DIR__."/testing-with-image.h5p","This is my H5P");
	//H5pUtil::deleteH5p("my-h5p");

	//echo H5pUtil::getLibraryNameById(10)."\n";