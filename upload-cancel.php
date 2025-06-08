<?php
$checkUploadSession = false;
require_once __DIR__."/segmented-upload-util.inc";

if ($tmpname != null)
	@unlink($tmpname);
if ($dstname != null)
	@unlink($dstname);
deleteUploadSession($id);
session_write_close();
exit("upload canceled");
