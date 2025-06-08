<?php
require_once __DIR__."/auth-common.php";
require_once __DIR__."/upload-util.inc";

$uploadSessionHoldSec ??= 300;
const K_DELETED_AT = "deletedAt";

function deleteUploadSession($id) {
	global $uploadSessionHoldSec;
	$now = time();
	$_SESSION[K_UPLOAD_SESSION] ??= [];
	$uploadSessions = &$_SESSION[K_UPLOAD_SESSION];
	foreach ($uploadSessions as $k => $v) {
		$t = $v[K_DELETED_AT] ?? null;
		if ($t == null)
			continue;
		if ($now - $t >= $uploadSessionHoldSec)
			unset($uploadSessions[$k]);
	}
	$uploadSessions[$id] = [
		K_DELETED_AT => $now
	];
}

if (!isAuthenticated())
	exitWithResponse(400, "no session?");

// id: upload session id
$id = $_REQUEST["id"] ?? exitWithResponse(400, "no upload session id");
$uploadSession = $_SESSION[K_UPLOAD_SESSION][$id] ?? exitWithResponse(
	400, "no upload session"
);

$tmpname = $uploadSession["tmpname"] ?? null;
$dstname = $uploadSession["dstname"] ?? null;

if ($checkUploadSession ?? true) {
	if ($tmpname == null)
		exitWithResponse(500, "no dstname in session");
	if ($dstname == null)
		exitWithResponse(500, "no tmpname in session");
}
