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
<script>
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
echo file_get_contents("man-default.fhtml");
echo @file_get_contents("{$targetdir}/default.fhtml");
echo @file_get_contents("{$targetdir}/man-default.fhtml");
system("groff -T html -m man ".escapeshellarg($targetfile)." | sed -e '1,/<body>/d'");
