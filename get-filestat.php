<?php
// Copyright 2026 akamoz.jp
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
require_once __DIR__."/common-util.inc";

header("Content-Type: application/json");
$absname = rtrim("{$rootpath}{$path}", "/");
$ent = getFileStat($absname, getDirname($absname));
if ($ent === false) {
	http_response_code(404);
	echo J([
		"success" => false
	]);
}
$ent["isDir"] = is_dir($absname);
$ent["isFile"] = is_file($absname);
echo J([
	"success" => true,
	"ent" => $ent
]);
