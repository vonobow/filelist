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
require_once __DIR__."/common-util.inc";

// if force is not specified and not a markdown file,
// redirect to the url which returns that file as it is.
function validateBoolean($b) {
	if (is_string($b)) {
		switch (strtolower($b)) {
		case "false": case "no": case "0": case "":
			return false;
		case "true": case "yes": case "1":
			return true;
		default:
			throw new Exception("incorrect boolean value");
		}
	}
	return boolval($b);
}
function boolParam($p, $v = false) {
	return validateBoolean($_REQUEST[$p] ?? $v);
}
for (;;) {
	if (boolParam("force"))
		break;
	if (preg_replace("/.*\\./", "", $targetfile) == "md")
		break;
	header("Location: " . rtrim("{$path}", "/"));
	exit(0);
}
?>
<!doctype html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($targetfile) ?></title>
<script type="module">
import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid/+esm'
addEventListener("DOMContentLoaded", () => {
	for (let e of document.querySelectorAll(".language-mermaid")) {
		e.parentElement.className = "mermaid";
		e.parentElement.textContent = e.textContent;
	}
});
addEventListener("keydown", ev => {
	switch (ev.key) {
	case "ArrowUp":
		if (ev.isComposing)
			return;
		if (!ev.altKey)
			return;
		location.href = <?=J("{$scriptpath}index.php{$parentpath}")?>;
		break;
	}
});
</script>
<?php
chdir(__DIR__);
echo file_get_contents("default.fhtml");
echo file_get_contents("md-default.fhtml");
echo @file_get_contents("{$targetdir}/default.fhtml");
echo @file_get_contents("{$targetdir}/md-default.fhtml");
?>
</head>
<body class="md-content"><?php
if (boolParam("usephp")) {
	$procphp = proc_open(
		[ "php", $targetfile, ...($_REQUEST["p"] ?? []) ],
		[ [ "file", "/dev/null", "r" ], [ "pipe", "w" ] ], $phppipes);
	$procnode = proc_open(
		[ "node", "md2html-with-deflist.mjs" ],
		[ $phppipes[1], [ "pipe", "w" ] ], $nodepipes);
	fpassthru($nodepipes[1]);
	proc_close($procphp);
	proc_close($procnode);
}
else
	system("node md2html-with-deflist.mjs < {$targetfile}");
?></body>
