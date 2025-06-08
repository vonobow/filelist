<?php
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
