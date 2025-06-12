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
function check_path($path) {
	if (preg_match("%(^|/)\.\.?(/|$)%", $path))
		exit("incorrect path");
}
function exitWithResponse($resp, $text) {
	http_response_code($resp);
	exit($text);
}

if ($_SERVER["REQUEST_METHOD"] != "POST")
	exitWithResponse(405, "method not allowed");
$path = "{$rootpath}{$path}";
check_path($path);
if (@is_dir($path))
	exit("directory already exists. nothing has done.");
if (!@mkdir($path))
	exitWithResponse(400, "failed to create directory");
exit("the directory is successfully created");
