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
?>
<script id="diff" type="application/json">
<?=JJ(file_get_contents($targetfile)),PHP_EOL?>
</script>
<script>
addEventListener("DOMContentLoaded", _ => {
	const $D = document;
	const e = $D.querySelector("pre");
	let inHead = false;
	JSON.parse($D.getElementById("diff").innerText).split("\n").forEach(s => {
		const p = $D.createElement("p");
		if (s.startsWith("diff")) {
			p.classList.add("head");
			inHead = true;
		}
		else if (s.startsWith("index"))
			p.classList.add("index");
		else if (s.startsWith("@@")) {
			p.classList.add("hunk")
			s = s.replace(/(^@@[^@]+@@).*/, "$1");
			inHead = false;
		}
		else if (s.startsWith("+")) {
			p.classList.add("added");
			p.classList.add(inHead ? "file" : "line");
		}
		else if (s.startsWith("-")) {
			p.classList.add("deleted");
			p.classList.add(inHead ? "file" : "line");
		}
		p.innerText = s;
		e.append(p);
	});
});
</script>
<?php
chdir(__DIR__);
echo file_get_contents("default.fhtml");
echo file_get_contents("diff-default.fhtml");
echo @file_get_contents("{$targetdir}/default.fhtml");
echo @file_get_contents("{$targetdir}/diff-default.fhtml");
?>
<pre class="diff">
</pre>
