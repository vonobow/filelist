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

$prettyJson ??= false;
function J($v, $jsonFlags = JSON_UNESCAPED_SLASHES) {
	global $prettyJson;
	if ($prettyJson)
		$jsonFlags |= JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
	return json_encode($v, $jsonFlags);
}

// JSON for JavaScript, must not include </script>
function JJ($v) {
	return J($v, JSON_HEX_TAG);
}

function isAbsolute($s) {
	return str_starts_with($s, "/");
}

function hasDirname($s) {
	return str_contains($s, "/");
}

// pathinfo("/a/b/") => { dirname: "/a", basename: "b" }
// realpath("/") => "/"
// realpath("/etc") => "/etc"
// realpath("/etc/") => "/etc"

function splitTargetPath($target) {
	if ($target == false)
		return [ "dir" => "", "name" => "" ];
	$path = explode("/", $target);
	if (count($path) < 2)
		return [ "dir" => "", "name" => $target ];
	$name = array_pop($path);
	return [ "dir" => implode("/", $path), "name" => "/{$name}" ];
}

// with trailing slash
function getDirname($s) {
	$pathinfo = pathinfo($s);
	return rtrim($pathinfo["dirname"], "/")."/"; // for lone slash
}

function getParentURL($abspath) {
	global $rootpath;
	if (!is_string($abspath))
		return $abspath;
	$dir = getDirname($abspath);	// with trailing slash
	$realpath = realpath($dir);		// without trailing slash
	if (!is_string($realpath))
		return $realpath;
	$realroot = realpath($rootpath);	// without trailing slash
	if (!str_starts_with($realpath, $realroot))
		return null;	// out of tree
	return substr($dir, strlen($realroot));	// with trailing slash
}

// $name must be absolute path in the filesystem
function getFileStat($name, $base = null) {
	global $rootpath, $path;
	$stat = @lstat($name);
	if ($stat === false)
		return false;
	$ent = [
		"isLink" => false,
		"mtime" => $stat["mtime"],
		"size" => $stat["size"]
	];
	if (is_link($name)) {
		$ent["isLink"] = true;
		$target = readlink($name);
		$ent["next"] = splitTargetPath($target);
		$abspath = $target;
		$base ??= "{$rootpath}{$path}";
		if (!isAbsolute($abspath))
			$abspath = "{$base}{$target}";
		$ent["next"]["url"] = getParentURL($abspath);
		$finaltarget = realpath($name);
		$ent["final"] = splitTargetPath($finaltarget);
		$ent["final"]["url"] = getParentURL($finaltarget);
	}
	return $ent;
}
