<?php
$prettyJson ??= false;
function J($v) {
	global $prettyJson;
	$jsonFlags = JSON_UNESCAPED_SLASHES;
	if ($prettyJson)
		$jsonFlags |= JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
	return json_encode($v, $jsonFlags);
}
