<?php
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
