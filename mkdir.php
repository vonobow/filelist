<?php
require_once __DIR__."/set-pathinfo.php";
function check_path($path) {
	if (preg_match("%(^|/)\.\.?(/|$)%", $path))
		exit("incorrect path");
}
function exitWithResponse($resp, $text) {
	http_response_code($resp);
	exit($text);
}

if ($_SERVER["REQUEST_METHOD"] != "POST")
	exitWithResponse(405, "method not allowed");
$path = "{$rootpath}{$path}";
check_path($path);
if (@is_dir($path))
	exit("directory already exists. nothing has done.");
if (!@mkdir($path))
	exitWithResponse(400, "failed to create directory");
exit("the directory is successfully created");
