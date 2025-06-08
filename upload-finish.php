<?php
require_once __DIR__."/segmented-upload-util.inc";

$hash = $_REQUEST["hash"] ?? exitWithResponse(400, "no hash");
$hash = str_replace(["-", "_"], ["+", "/"], $hash);
$hash = base64_decode($hash) ?: exitWithResponse(400, "incorrect hash");

deleteUploadSession($id);
session_write_close();

error_log("calculating hash");
$tmphash = hash_file("sha256", $tmpname, true) ?: exitWithResponse(
	500, "failed to calculate hash"
);
error_log("done");
if ($tmphash != $hash)
	exitWithResponse(500, "hashes are not matched");

if (@rename($tmpname, $dstname) == false)
	exitWithResponse(500, "failed to create target file");

exitWithResponse(200, "done");
