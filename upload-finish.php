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

$hashes = file_get_contents("php://input");
if ($hashes === false)
	exitWithResponse(400, "cannot get body");
$hashes = json_decode($hashes);
if ($hashes === null)
	exitWithResponse(400, "incorrect body format");
if (!is_array($hashes))
	exitWithResponse(400, "incorrect body format");
foreach ($hashes as &$hash) {
	if (!is_string($hash))
		exitWithResponse("incorrect hash format");
	$hash = str_replace(["-", "_"], ["+", "/"], $hash);
	$hash = base64_decode($hash) ?: exitWithResponse(400, "incorrect hash");
}
unset($hash);

deleteUploadSession($id);
session_write_close();

error_log("calculating hash");
const HASHSTEP = 256 * 1024 ** 2;
const HASHLEN = HASHSTEP + 1024 ** 2;
$fd = fopen($tmpname, "rb") ?: exitWithResponse(500, "failed to open uploaded file");
$i = 0;
foreach ($hashes as $hash) {
	$h = hash_init("sha256");
	if (fseek($fd, $i) < 0)
		exitWithResponse(500, "upload uncompleted?");
	hash_update_stream($h, $fd, HASHLEN);
	if (hash_final($h) != bin2hex($hash))
		exitWithResponse(500, "hashes are not matched");
	$i += HASHSTEP;
}
fclose($fd);
error_log("done");

if (@rename($tmpname, $dstname) == false)
	exitWithResponse(500, "failed to create target file");

exitWithResponse(200, "done");
