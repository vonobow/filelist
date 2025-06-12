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
