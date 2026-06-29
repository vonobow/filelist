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

require_once __DIR__."/common-util.inc";
require_once __DIR__."/segmented-upload-util.inc";
session_write_close();

$hash = $_REQUEST["h"] ?? exitWithResponse(400, "no hash value");
$hash = str_replace(["-", "_"], ["+", "/"], $hash);
$hash = base64_decode($hash, strict: true) ?: exitWithResponse(400, "incorrect hash");
$from = validate_int_param("p", "position");
$len = validate_int_param("s", "size");

$fd = fopen($tmpname, "rb") ?: exitWithResponse(500, "failed to open uploaded file");
$h = hash_init("sha256");
if (fseek($fd, $from) < 0)
	exitWithResponse(500, "upload uncompleted?");
hash_update_stream($h, $fd, $len);
if (hash_final($h) != bin2hex($hash))
	exitWithResponse(500, "hashes are not matched");
fclose($fd);
