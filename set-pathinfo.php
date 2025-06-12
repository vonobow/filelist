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

const VERSION = "0.1";
const PASSHASHFILE = ".tiny-filelist-passhash";
const ENV_TEMPDIR = "FILELIST_TEMPDIR";

// strip last component from specified path.
//	returned string has no trailling slash.
//	consecutive slashes are also removed.
//	if $path has no slash, return as it is.
//	if $path has only slash(es) at the end, just remove it.
function stripLastComponent($path) {
	return preg_replace("|/+[^/]*/*\$|", "\$1", $path);
}

// $path: path specified in URL, with preceeding slash
// and trailing slash
$path = $_SERVER['PATH_INFO'] ?? "";
$path = "/".trim($path, "/");
if (substr($path, -1) != "/") // don't add "/" if $path is lone "/"
	$path .= "/";

// $parentpath: the parent directory, with preceeding and
// trailing slash. if $path == "/", $parentpath is "/".
$parentpath = stripLastComponent($path)."/";

// $scriptpath: the path where the script exists, in URL,
// with preceeding slash and trailing slash
$scriptpath = stripLastComponent($_SERVER['SCRIPT_NAME'] ?? "")."/";
require_once __DIR__."/pathprefix.inc";
$scriptpath = prependPathPrefix($scriptpath);

// $rootpath: path in the filesystem, without trailing slash
// empty string indicates the top of the hierarchy.
$rootpath = $_SERVER['DOCUMENT_ROOT'];
if (strlen($rootpath) < 1)
	$rootpath = __DIR__;
$rootpath = rtrim($rootpath, "/");

// $targetfile: full path to the target file
$targetfile = rtrim($rootpath.$path, "/");

// $targetdir: the directory where the target is in,
//	without trailing slash.
$targetdir = stripLastComponent($targetfile);

// for URL: {$scriptpath}index.php{$path}
// for filesystem: {$rootpath}{$path}
// for reading target file: {$targetfile}
// directory where target is in: {$targetdir}
