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
require __DIR__."/html-header.inc";
?>
<title><?= htmlspecialchars($targetfile) ?></title>
<?php
require_once __DIR__."/go-up.pjs";
$text = @file_get_contents($targetfile);
if ($text === false) {
	http_response_code(404);
	exit("file not found.");
}
$json = json_decode("[{$text}]", associative: true, flags: JSON_INVALID_UTF8_SUBSTITUTE);
if ($json == null) {
	http_response_code(422);
	exit("not a json file.");
}
chdir(__DIR__);
echo file_get_contents("default.fhtml");
echo file_get_contents("json-default.fhtml");
echo @file_get_contents("{$targetdir}/default.fhtml");
echo @file_get_contents("{$targetdir}/json-default.fhtml");
echo "<pre><code>";
echo htmlspecialchars(json_encode($json[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "</code></pre>", PHP_EOL;
