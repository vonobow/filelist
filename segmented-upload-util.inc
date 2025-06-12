<?php
// Copyright 2025 akamoz.jp
//
// This file is part of tiny-filelist.
//
// Tiny-filelist is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// Tiny-filelist program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// Affero General Public License for more details.
//
// You should have received a copy of the Affero GNU General Public License
// along with this program. If not, see <https://www.gnu.org/licenses/>.

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
