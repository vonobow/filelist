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

require_once __DIR__."/auth-common.php";
if (checkAuthenticated()) {
	if (!isset($_SERVER["PATH_INFO"]))
		return false; // download a file, or .php without a path

	// avoid to invoke router.php twice.
	$scriptfilename = realpath($_SERVER["SCRIPT_FILENAME"]);
	$thisfilename = realpath(__FILE__);
	if ($scriptfilename == false || $thisfilename == false) {
		http_response_code(404);
		exit("file not found");
	}
	if (strtolower($scriptfilename) == strtolower($thisfilename)) {
		http_response_code(400);
		exit("incorrect script");
	}

	// execute the script
	require_once $scriptfilename;
	exit(0);
}
if (isset($_REQUEST["nologin"])) {
	http_response_code(401);
	exit("unauthorized");
}
require_once __DIR__."/login.php";
