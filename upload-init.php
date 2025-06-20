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
require_once __DIR__."/common-util.inc";
require_once __DIR__."/upload-util.inc";

$upload_session_id_bytes = 12;

if (!isAuthenticated())
	startNewSession();

$tempdir = getenv(ENV_TEMPDIR);
if ($tempdir === false) {
	exitWithResponse(500,
		"need ".ENV_TEMPDIR." environment variable for multi-segmented upload"
	);
}
$tempdir = rtrim($tempdir, "/");
if (strlen($tempdir) < 1)
	exitWithResponse(500, ENV_TEMPDIR." is empty or lone slash?");

// name: target filename
$name = $_REQUEST["name"] ?? exitWithResponse(400, "no name");
$dstname = checkTargetFile($name);
$unlinkOnError[] = $dstname;

$tmpname = tempnam($tempdir, "filelist-");
if ($tmpname === false)
	exitWithResponse(500, "failed to create a temporary file");
$unlinkOnError[] = $tmpname;
$temppath = realpath($tempdir);
if ($temppath === false)
	exitWithResponse(500, "temporary directory doesn't exist?");
if ($temppath != preg_replace('%/[^/]*$%', "", $tmpname))
	exitWithResponse(500, "failed to create a file in the temporary directory");

for (;;) {
	$usid = str_replace([ "+", "/" ], [ "-", "_" ],
		base64_encode(random_bytes($upload_session_id_bytes))
	);
	if (!isset($_SESSION[K_UPLOAD_SESSION][$usid]))
		break;
}
error_log("usid={$usid}");

$_SESSION[K_UPLOAD_SESSION][$usid] = [
	"dstname" => $dstname,
	"tmpname" => $tmpname
];
header("Content-Type: application/json");
exitWithResponse(200, J([
	"uploadSessionId" => $usid
]));
