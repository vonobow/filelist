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
