<?php
require_once __DIR__."/set-pathinfo.php";

const K_UPLOAD_SESSION = "uploadSession";

$max_upload_position ??= 1024 * 1024 * 1024;

$unlinkOnError = [];
function exitWithResponse($resp, $text) {
	global $unlinkOnError;
	if ($resp >= 400) {
		foreach ($unlinkOnError as $v)
			unlink($v);
	}
	http_response_code($resp);
	exit($text);
}

function check_path($path) {
	if (strlen($path) < 1)
		exitWithResponse(400, "incorrect path");
	if (preg_match('%(^|/)\.\.?(/|$)%', $path))
		exitWithResponse(400, "incorrect path");
}

function checkTargetFile($name) {
	global $rootpath, $path;

	$fn = preg_replace("%^.*/%", "", $name);
	check_path($fn);
	check_path($path);

	// check file existence and prevent further creation
	$dstname = "{$rootpath}{$path}{$fn}";
	$dst = @fopen($dstname, "x");
	if ($dst === false)
		exitWithResponse(400, "failed to create file (file already exists?)");
	fclose($dst);

	return $dstname;
}
