<?php
require_once __DIR__."/upload-util.inc";

function isset_get(&$obj, $key, $msg) {
	if (!isset($obj[$key]))
		exitWithResponse(500, "{$msg}: no {$key}");
	return $obj[$key];
}

$posted = isset_get($_FILES, "postedfile", "incorrect access");
$err = isset_get($posted, "error", "incorrect access");
if ($err != UPLOAD_ERR_OK)
	exitWithResponse(500, "uploading is incomplete");

$dstname = checkTargetFile($posted["name"]);

if (move_uploaded_file($posted["tmp_name"], $dstname) === false) {
	unlink($dst);
	exitWithResponse(500, "failed to upload file");
}

unset($posted);
exit("the file is successfully uploaded");
