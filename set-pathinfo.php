<?php
// need PHP7.0 or later (for "??")

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
$rootpath = $_SERVER['DOCUMENT_ROOT'];
if (strlen($rootpath) < 1)
	$rootpath = __DIR__;

// $targetfile: full path to the target file
$targetfile = rtrim($rootpath.$path, "/");

// $targetdir: the directory where the target is in,
//	without trailing slash.
$targetdir = stripLastComponent($targetfile);

// for URL: {$scriptpath}index.php{$path}
// for filesystem: {$rootpath}{$path}
// for reading target file: {$targetfile}
// directory where target is in: {$targetdir}
