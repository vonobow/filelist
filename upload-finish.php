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
