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

function E($htmlmsg) {
require __DIR__."/html-header.inc";
?>
<title>error - filelist</title>
<?=$htmlmsg?>
<?php
	exit(0);
}

$filepath = rtrim("{$rootpath}{$path}", "/");
if (preg_match("%(^|/)\\.\\.(/|$)%", $filepath))
	E("incorect path");
$filename = preg_replace("|.*/|", "", $path);
if (strlen($path) < 1)
	E("incorrect filename");
$fd = fopen($filepath, "rb");
if ($fd === false)
	E("no file");
$fstat = fstat($fd);
if ($fstat === false)
	E("failed to get file type");
if (($fstat["mode"] & (0770000)) !=0100000)
	E("not a file");
$outfd = fopen("php://output", "wb");
if ($outfd === false)
	E("failed to output");
$safename = "";
for ($i = 0; $i < strlen($filename); $i++) {
	$c = $filename[$i];
	if ($c < 0x20 || $c >= 0x7f || $c == '"' | $c == '%' )
		$safename .= sprintf("%%%02x", $c);
	else
		$safename .= $c;
}
switch ($_REQUEST["content-type"] ?? null) {
case "text":
	header("Content-Type: text/plain");
	break;
default:
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"{$safename}\"");
}
if (stream_copy_to_stream($fd, $outfd) === false)
	error_log("failed to output");
fclose($fd);
fclose($outfd);
