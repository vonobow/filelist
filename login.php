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

$msg = null;
$pw = null;
$passhash = null;

function flush_secrets() {
	foreach ([ "pw", "passhash" ] as $v) {
		$GLOBALS[$v] = str_repeat(" ", 100);
		unset($GLOBALS[$v]);
	}
}

$fd = @fopen(PASSHASHFILE, "r");
if ($fd === false) {
require __DIR__."/html-header.inc";
?>
<title>filelist authentication</title>
<p>no password hash file, use mkpasshash.php from CLI to create it.
<?php
	exit(1);
}
else {
	$passhash = trim(stream_get_contents($fd));
	fclose($fd);
}
do {
	$pw = $_REQUEST["pw"] ?? null;
	if ($pw === null)
		break;
	if (!password_verify($pw, $passhash)) {
		$msg = "password is not matched";
		break;
	}
	flush_secrets();
	exitWithNewSession();
} while (false);
flush_secrets();
require_once __DIR__."/html-header.inc";
?>
<title>filelist authentication</title>
<form method="post">
<label>password
<input type="password" name="pw" autofocus></label>
</form>
<?php if ($msg !== null) echo "<p>", htmlspecialchars($msg), PHP_EOL; ?>
