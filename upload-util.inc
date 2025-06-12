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
