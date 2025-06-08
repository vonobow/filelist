<?php
function prependPathPrefix($path) {
	$prefix = getenv("PATH_PREFIX");
	if (!is_string($prefix))
		return $path;
	return "/".trim($prefix, "/")."/".ltrim($path, "/");
}
