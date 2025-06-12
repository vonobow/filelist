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

$prettyJson ??= false;
function J($v) {
	global $prettyJson;
	$jsonFlags = JSON_UNESCAPED_SLASHES;
	if ($prettyJson)
		$jsonFlags |= JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
	return json_encode($v, $jsonFlags);
}
