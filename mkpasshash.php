<?php
require_once __DIR__."/set-pathinfo.php";

function CHECK($b, $msg) {
	if (!$b) {
		error_log($msg);
		exit(1);
	}
}

if (php_sapi_name() != "cli") {
	error_log("mkpasshash.php is invoked from non-CLI environment, exited.");
	echo "this scritp must be invoked from CLI.";
	exit(1);
}

$tty = @fopen("/dev/tty", "rt");
CHECK($tty !== false, "no tty?");
CHECK(!file_exists(PASSHASHFILE), PASSHASHFILE." exists, remove it first.");

echo "password: ";
register_shutdown_function(function () { system("stty echo"); });
system("stty -echo");
$pw1 = rtrim(fgets($tty), "\r\n");
echo PHP_EOL, "retype password: ";
$pw2 = rtrim(fgets($tty), "\r\n");
system("stty echo");
echo PHP_EOL;
CHECK($pw1 == $pw2, "passwords are not matched.");

umask(0077);
$fd = @fopen(PASSHASHFILE, "xt"); // x for avoid race condition
CHECK($fd !== false, PASSHASHFILE." exists, remove it first.");
CHECK(fwrite($fd, password_hash($pw1, PASSWORD_DEFAULT)), "failed to write password hash.");
CHECK(fclose($fd), "failed to close password hash file");
error_log("password hash is successfully written.");
exit(0);
