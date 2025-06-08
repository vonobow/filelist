<?php
require_once __DIR__."/segmented-upload-util.inc";
session_write_close();

// pos: file position
$pos = $_REQUEST["pos"] ?? exitWithResponse(400, "no file position");
if ($pos < 0)
	exitWithResponse(400, "bad position");
if ($pos >= $max_upload_position)
	exitWithResponse(400, "too large position");

$srcfd = fopen("php://input", "r");
if ($srcfd === false)
	exitWithRespose(500, "failed to get data");

$tmpfd = fopen($tmpname, "r+");
if ($tmpfd === false)
	exitWithResponse(500, "no temporary file?");
if (flock($tmpfd, LOCK_EX) == false)
	exitWithResponse(500, "failed to get lock");
if (fseek($tmpfd, $pos, SEEK_SET) != 0)
	exitWithRespose(500, "failed to setup for writing");
if (stream_copy_to_stream($srcfd, $tmpfd) === false)
	exitWithRespose(500, "failed to write the data");

fclose($tmpfd);
