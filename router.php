<?php
require_once __DIR__."/auth-common.php";
if (checkAuthenticated()) {
	if (!isset($_SERVER["PATH_INFO"]))
		return false; // download a file, or .php without a path

	// avoid to invoke router.php twice.
	$scriptfilename = realpath($_SERVER["SCRIPT_FILENAME"]);
	$thisfilename = realpath(__FILE__);
	if ($scriptfilename == false || $thisfilename == false) {
		http_response_code(404);
		exit("file not found");
	}
	if (strtolower($scriptfilename) == strtolower($thisfilename)) {
		http_response_code(400);
		exit("incorrect script");
	}

	// execute the script
	require_once $scriptfilename;
	exit(0);
}
if (isset($_REQUEST["nologin"])) {
	http_response_code(401);
	exit("unauthorized");
}
require_once __DIR__."/login.php";
