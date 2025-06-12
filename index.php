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

$rawpath = $_SERVER['PATH_INFO'] ?? "";
if (substr($rawpath, -1) != "/") {
	$qstr = $_SERVER['QUERY_STRING'] ?? "";
	if (strlen($qstr > 0))
		$qstr = "?{$qstr}";
	header("Location: {$scriptpath}index.php{$path}{$qstr}", true, 301);
	return;
}

$base = $scriptpath."index.php";
$url = $base.$path;

$hidedots = 1;
$sortby = "n";
$descending = false;
$sortsign = 1;

$linkopt = [];

if (isset($_REQUEST["dots"])) {
	$linkopt["dots"] = 1;
	$hidedots = 0;
}

$sortby_values = array_flip([ "t", "s", "n" ]);
if (isset($sortby_values[$_REQUEST["sort"] ?? null])) {
	$sortby = $_REQUEST["sort"];
	$linkopt["sort"] = $sortby;
}
if (isset($_REQUEST["rev"])) {
	$descending = intval($_REQUEST["rev"]);
	$linkopt["rev"] = 1;
}
if ($descending)
	$sortsign = -1;

function dirent_compare_by_name($a, $b) {
	global $sortsign;
	return $sortsign * strcmp($a["name"], $b["name"]);
}

function dirent_compare_by_name_asc($a, $b) {
	return strcmp($a["name"], $b["name"]);
}

function dirent_compare_by_time($a, $b) {
	global $sortsign;
	return $sortsign * ($a["mtime"] - $b["mtime"]);
}

function dirent_compare_by_size($a, $b) {
	global $sortsign;
	return $sortsign * ($a["size"] - $b["size"]);
}

function get_sort_function($forDirectory = false) {
	global $sortby;
	switch ($sortby) {
	case "n":
		return "dirent_compare_by_name";
	case "t":
		return "dirent_compare_by_time";
	case "s":
		if ($forDirectory)
			return "dirent_compare_by_name_asc";
		return "dirent_compare_by_size";
	}
	return null;
}

function putLink($url, $text, $merge = [], $remove = []) {
	global $linkopt;
	$opt = [];
	if ($merge !== null) {
		$opt = $linkopt;
		foreach ($remove as $v)
			unset($opt[$v]);
		$opt = array_merge($opt, $merge);
	}
	if (is_string($url)) {
		echo '<a href="',
			htmlspecialchars($url);
		if (count($opt) > 0)
			echo "?", http_build_query($opt);
		echo '">';
	}
	echo htmlspecialchars($text);
	if (is_string($url))
		echo "</a>";
}

function putBreadcrumb($url, $path) {
	echo "[";
	putLink($url, "root");
	echo "]";
	foreach (explode("/", $path) as $v) {
		if (strlen($v) < 1)
			continue;
		$url .= "{$v}/";
		echo "/";
		putLink($url, $v);
	}
}

function putSortLink($url, $flag, $text) {
	global $sortby, $descending;
	$opt = [ "sort" => $flag ];
	echo "<span class='button";
	if ($flag == $sortby) {
		echo " selected";
		if ($descending)
			; // echo " reversed";
		else
			$opt["rev"] = 1;
	}
	echo "'>";
	putLink($url, $text, $opt, [ "rev" ]);
	echo "</span> ";
}

function putDotsLink($url) {
	global $hidedots;
	echo "<span class='button";
	if ($hidedots) {
		echo "'>";
		putLink($url, "dot files", [ "dots" => 1 ]);
	}
	else {
		echo " selected'>";
		putLink($url, "dot files", [], [ "dots" ]);
	}
	echo "</span> ";
}

function putSymbolicLink($link, $merge = [], $remove = []) {
	global $base;
	if ($link["url"] === false)
		echo "<s class='dangling'>";
	if (!is_string($link["url"]))
		echo htmlspecialchars($link["dir"]);
	else {
		if ($link["inTree"])
			echo "<span class='document-root'></span>";
		putLink($base.$link["url"], $link["dir"], $merge, $remove);
	}
	echo $link["name"];
	if ($link["url"] === false)
		echo "</s>";
}

$tz = new DateTimeZone(date_default_timezone_get());
$dt = (new DateTime())->setTimeZone($tz);
function str_time($mtime) {
	global $dt;
	return "<span class='date'>".
		$dt->setTimeStamp($mtime)->format("D, d M Y H:i:s").
		"</span>". PHP_EOL;
}

$NUMFMT = new NumberFormatter(Locale::getDefault(), NumberFormatter::DECIMAL);
$NUMFMT->setAttribute(NumberFormatter::GROUPING_USED, 1);
$NUMFMT->setAttribute(NumberFormatter::GROUPING_SIZE, 3);

function list_entries(&$entries, $url, $forDirectory = false) {
	global $NUMFMT;
	foreach ($entries as $dirent) {
		$name = $dirent["name"];
		echo "<li data-filename='",
			htmlspecialchars($name),
			"'>", str_time($dirent["mtime"]),
			" <span class='filesize";
		if ($forDirectory)
			echo " directory'>DIR";
		else
			echo "'>", $NUMFMT->format($dirent["size"]);
		echo "</span> ";
		echo "<span class='file-context-menu-popper'>...</span> ";
		if (!$forDirectory && preg_match("/\\.php\$/", $name))
			echo htmlspecialchars($name);
		else if ($forDirectory)
			putLink($url.$name."/", $name, null);
		else if ($dirent["isFile"])
			putLink($url.$name, $name, null);
		else
			echo "<i class='special'>", htmlspecialchars($name), "</i>";
		if ($dirent["isLink"]) {
			echo " -> ";
			putSymbolicLink($dirent["next"]);
			if ($dirent["final"]["url"] === false)
				echo " <span class='dangling-indicator'></span>";
			else {
				echo " <span class='final-target'>(";
				putSymbolicLink($dirent["final"]);
				echo ")</span>";
			}
		}
		echo PHP_EOL;
	}
}

function isAbsolute($s) {
	return str_starts_with($s, "/");
}

function makeAbsolute($target) {
	global $rootpath, $path;
	if (isAbsolute($target))
		return $target;
	return $rootpath.$path.$target;
}

function splitTargetPath($target, $isRealPath) {
	global $rootpath, $path;
	if ($target == false)
		return [ "dir" => "", "name" => "", "url" => false ];
	if (preg_match('|(.*)(/+[^/]+/*)$|', $target, $m) != 1)
		return [ "dir" => "", "name" => $target, "url" => null ];
	$realpath = $isRealPath ? $m[1] : realpath(makeAbsolute($m[1]));
	$dir = $m[1];
	$url = null; // assume out of tree
	$inTree = false;
	$realroot = realpath($rootpath);
	if (!is_string($realpath) || !is_dir($realpath))
		$url = false;	// dangling
	else {
		if (str_starts_with($realpath, $realroot)) {
			$url = substr($realpath, strlen($realroot));
			if ($isRealPath) {
				$dir = $url;
				$inTree = true;
			}
		}
	}
	return [ "dir" => $dir, "name" => $m[2], "inTree" => $inTree, "url" => $url ];
}

function list_dir($path) {
	global $rootpath, $url, $hidedots;
	$abspath = $rootpath.$path;
	$fd = @opendir($abspath);
	if ($fd === FALSE) {
		echo "<p>incorrect path";
		return;
	}
	$dirlist = array();
	$filelist = array();
	for (;;) {
		$dirent = readdir($fd);
		if ($dirent === FALSE)
			break;
		if ($hidedots && (substr($dirent, 0, 1) == "."))
			continue;
		$stat = lstat($abspath.$dirent);
		$absname = $abspath.$dirent;
		$ent = [
			"name" => $dirent, "mtime" => $stat["mtime"], "size" => $stat["size"],
			"isLink" => false
		];
		if (is_link($absname)) {
			$target = readlink($absname);
			$ent["isLink"] = true;
			$ent["next"] = splitTargetPath($target, false);
			$ent["final"] = splitTargetPath(realpath(makeAbsolute($target)), true);
		}
		if (is_dir($absname))
			$dirlist[] = $ent;
		else {
			$ent["isFile"] = is_file($absname);
			$filelist[] = $ent;
		}
	}
	closedir($fd);
	usort($dirlist, "dirent_compare_by_name");
	usort($dirlist, get_sort_function(true));
	usort($filelist, "dirent_compare_by_name");
	usort($filelist, get_sort_function());

	echo "<div class='item-lists'><ul class='directories'>", PHP_EOL;
	list_entries($dirlist, $url, true);
	echo "</ul>", PHP_EOL;

	echo "<ul class='files droptarget-current'>", PHP_EOL;
	list_entries($filelist, prependPathPrefix($path));
	echo "<li class='upload-target'>drop files here to upload", PHP_EOL;
	echo "</ul></div>", PHP_EOL;

	echo "<p id='thumbnail' class='thumbnail'>";
	$thumbcount = 0;
	foreach ($filelist as $dirent) {
		$name = $dirent["name"];
		$imgpath = $path.$name;
		$thpath = $path."th/".$name;
		if (!is_readable($rootpath.$thpath))
			continue;
		echo "<a href='",
			htmlspecialchars($imgpath),
			"'><img height=100 src='",
			htmlspecialchars($thpath),
			"'></a> ";
		$thumbcount++;
	}
	echo "<script>const thumbcount = {$thumbcount};</script>", PHP_EOL;
}
require __DIR__."/html-header.inc";
?>
<title><?php echo htmlspecialchars($path) ?></title>
<script>
const $D = document;
const $W = window;
function $ID(id) {
	return $D.getElementById(id);
}
function AEL(e, ...args) {
	e.addEventListener(...args);
	return _ => e.removeEventListener(...args);
}
function $AEL(...args) {
	return AEL($W, ...args);
}
function QS(e, ...args) {
	return e.querySelector(...args);
}
function $QS(...args) {
	return QS($D, ...args);
}
function toBase64(buf) {
	const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_=";
	buf = buf.reduce((s, v, i) => {
		switch (i % 3) {
		case 0:
			s.push(v >> 2);
			s.push((v & 0x03) << 4);
			break;
		case 1:
			s.push(s.pop() | (v >> 4));
			s.push((v & 0x0f) << 2);
			break;
		case 2:
			s.push(s.pop() | (v >> 6));
			s.push(v & 0x3f);
			break;
		}
		return s;
	}, []);
	while (buf.length % 4 != 0)
		buf.push(64);
	return buf.reduce((s, v) => s + alphabet[v], "");
}
const numfmt = (new Intl.NumberFormat()).format;
const JobQueue = {
default_concurrency: 10,
create(...arg) {
	let me = Object.create(JobQueue);
	me.init(...arg);
	return me;
},
init(opt = {}) {
	this.concurrency = opt.concurrency ?? this.default_concurrency;
	this.pending = [];
	this.doing = new Map();
},
queue(id, action, prio = 10) {
	this.pending[prio] ??= [];
	this.pending[prio].push({ id, action });
},
dequeue() {
	return this.pending.find(v => v?.length > 0)?.shift();
},
remove(id) {
	for (const q of this.pending) {
		const i = q.findIndex(v => v.id == id);
		if (i >= 0) {
			q.splice(i, 1);
			return;
		}
	}
},
empty() {
	for (const q of this.pending) {
		if (q?.length > 0)
			return false;
	}
	return true;
},
wait() {
	while (this.doing.size < this.concurrency) {
		if (this.empty())
			break;
		let job = this.dequeue();
		let id = job.id;
		this.doing.set(id, job.action().then(_ => id, _ => id));
	}
	if (this.doing.size == 0)
		return Promise.resolve();
	return Promise.race(this.doing.values()).then(id => {
		this.doing.delete(id);
		return this.wait();
	});
}
};

// Promise.withResolvers polyfill
function newPromise() {
	const result = {};
	result.promise = new Promise((ok, ng) => {
			result.resolve = ok;
			result.reject = ng;
		});
	return result;
}

const SerializedPromise = {
create(...args) {
	let me = Object.create(SerializedPromise);
	me.init(...args);
	return me;
},
init() {
	this.promise = Promise.resolve();
},
request(f) {
	const p = newPromise();
	const q = this.promise;
	this.promise = q.finally(
		_ => f().then(ok => p.resolve(ok), ng => p.reject(ng))
	);
	return p.promise;
}
};

function strHTML(s) {
	return s.toString().replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;");
}

function getDropTarget(e) {
	let t = e.closest(".droptarget-current");
	if (t != null)
		return t;
	t = e.closest(".directories");
	if (t != null)
		return e.closest("li");
	return null;
}

const fetchTimeoutSec = 30;
let aborter = new AbortController();
function fetchWithTimeout(url, opt = {}, timeoutSec = null, signals = null) {
	signals ??= [ aborter.signal ];
	timeoutSec ??= fetchTimeoutSec;
	signals.push(AbortSignal.timeout(timeoutSec * 1000));
	return fetch(url, { ...opt, signal: AbortSignal.any(signals) });
}

function fetchWithRetry(url, opt = {}, timeoutSec = null, signals = null, retry = 3) {
	timeoutSec ??= fetchTimeoutSec;
	return fetchWithTimeout(url, opt, timeoutSec, signals)
	.catch(v => {
		if (v.name == "TimeoutError" && retry > 0) {
			console.log("request timed out, retry");
			return fetchWithRetry(url, opt, timeoutSec * 2, signals, retry - 1);
		}
		throw v;
	});
}

function postEvent(elem, name, detail) {
	elem.dispatchEvent(new CustomEvent(name, { detail }));
}

function postSuccess(elem, id, message) {
	postEvent(elem, "filelist-success", { id, message });
}

function postFailed(elem, id, message) {
	postEvent(elem, "filelist-failed", { id, message });
}

function fetchAndCatch(elem, id, url, opt = {}) {
	url += `${url.includes("?")?"&":"?"}nologin=true`;
	return fetchWithRetry(url, opt)
	.catch(err => Promise.reject(err.toString()))
	.then(resp => resp.text().then(msg => resp.ok ? msg : Promise.reject(msg)))
	.then(message => postSuccess(elem, id, message))
	.catch(message => postFailed(elem, id, message));
}

const scriptpath = <?=J($scriptpath)?>;
function uploadFile(file, path, elem, id) {
	postEvent(elem, "filelist-doing", { id });
	let form = new FormData();
	form.append("postedfile", file);
	return fetchAndCatch(elem, id,
		`${scriptpath}upload.php/${path}`,
		{ method: "post", body: form }
	);
}

const segmentedUploadLimitBytes = 1536 * 1024;
const useSegmentedUpload = <?=J(getenv(ENV_TEMPDIR) !== false)?>;
const uploadInitMutex = SerializedPromise.create();
function initiateSegmentedUpload(q, file, path, elem, id) {
	return uploadInitMutex.request(_ => fetchWithRetry(
		`${scriptpath}upload-init.php/${path}?name=${
			encodeURIComponent(file.name)
		}&nologin=true`
	))
	.then(resp => {
		if (!resp.ok)
			return resp.text().then(msg => Promise.reject(msg));
		return resp.json();
	})
	.then(json => {
		if (json.uploadSessionId == null)
			throw("failed to initiate segmented upload");
		uploadSegment(q, file, elem, id, json.uploadSessionId);
	})
	.catch(message => postFailed(elem, id, message));
}

function uploadSegment(q, file, elem, id, usid) {
	const size = file.size;
	const numSegments = Math.ceil(file.size / segmentedUploadLimitBytes);
	const segmentSize = Math.ceil(size / numSegments);
	const promises = [
		file.arrayBuffer().then(buf => crypto.subtle.digest("SHA-256", buf))
	];
	const idlist = [];
	postEvent(elem, "filelist-init", { id, total: size });
	const remover = $AEL("beforeunload", _ => {
		const param = new URLSearchParams({ id: usid, nologin: true });
		navigator.sendBeacon(`${scriptpath}upload-cancel.php?${param}`);
	});
	let from = 0;
	while (from < size) {
		const to = Math.min(from + segmentSize, size);
		const segid = `${id}-${from}`;
		idlist.push(segid);
		const p = newPromise();
		promises.push(p.promise);
		const param = new URLSearchParams({ id: usid, pos: from, nologin: true });
		const body = file.slice(from, to)
		q.queue(segid, _ => {
			return fetchWithRetry(`${scriptpath}upload-segment.php?${param}`, {
				method: "POST",
				headers: {
					"Content-Type": "application/octet-stream"
				},
				body
			})
			.then(resp => {
				if (!resp.ok)
					return resp.text().then(msg => Promise.reject(msg));
				postEvent(elem, "filelist-progress", { id, size: body.size });
				p.resolve();
			})
			.catch(msg => { p.reject(msg); });
		}, 10);
		from = to;
	}
	const finid = `${id}-finish`;
	idlist.push(finid);
	q.queue(finid, _ => {
		return Promise.all(promises)
		.then(v => {
			postEvent(elem, "filelist-finalize", { id });
			const hash = toBase64(new Uint8Array(v[0]));
			const param = new URLSearchParams({ id: usid, hash, nologin: true });
			return fetchWithRetry(`${scriptpath}upload-finish.php?${param}`, {}, 60);
		})
		.then(resp => resp.text().then(msg => resp.ok ? msg : Promise.reject(msg)))
		.then(message => postSuccess(elem, id, message))
		.catch(message => {
			idlist.forEach(v => q.remove(v));
			q.queue(`${id}-cancel`, _ => {
				const param = new URLSearchParams({ id: usid, nologin: true });
				return fetchWithRetry(`${scriptpath}upload-cancel.php?${param}`, {}, 5, []);
			}, 10);
			postFailed(elem, id, message);
		})
		.finally(remover);
	}, 10);
}

function makeDirectory(path, elem, id) {
	return fetchAndCatch(elem, id,
		`${scriptpath}mkdir.php/${path}`,
		{ method: "post" }
	);
}

function UQID() {
	for (;;) {
		let id = `uqid-${Math.random().toString(36).replace(/^.*\./, "")}`;
		if (!$ID(id))
			return id;
	}
}

function uploadDataItem(q, item, path, elem) {
	function postQueueEvent(action) {
		let id = UQID();
		postEvent(elem, "filelist-queue", { action, id, path, name: item.name });
		return id;
	}
	// remove slashes from top and end of the string
	path = path.replace(/^\/+/, "").replace(/\/+$/, "");
	if (item.isFile) {
		// in case of files:
		// console.log(`file: ${item.fullPath}`); // a file exists
		// item.file(f => console.log(f)); // to get File object
		let id = postQueueEvent("uploading");
		q.queue(id, _ => {
			return (new Promise(ok => item.file(f => ok(f))))
				.then(f => {
					if (useSegmentedUpload)
						return initiateSegmentedUpload(q, f, path, elem, id);
					else
						return uploadFile(f, path, elem, id)
				});
		}, 20);
		return;
	}
	if (!item.isDirectory)
		throw "incorrect FileSystemEntry?";

	// in case of directories:
	// console.log(`dir: ${item.fullPath}`);	// a directory exists
	// item.createReader().readEntries(list => console.log(list)); // child entries

	// create directory
	let id = postQueueEvent("mkdir");
	path += `/${item.name}`;
	// repeat until the number of items returned from readEntries becomes 0.
	function readEntries(reader) {
		return new Promise(ok => reader.readEntries(list => ok(list)))
		.then(list => {
			if (list.length == 0)
				return Promise.resolve();
			list.forEach(i => uploadDataItem(q, i, path, elem));
			return readEntries(reader);
		});
	}
	q.queue(id, _ => {
		return makeDirectory(path, elem, id)
		.then(_ => readEntries(item.createReader()));
	}, 5);
}
$AEL("DOMContentLoaded", _ => {
	let selected = $QS(".commander .selected");
	let url = new URL($D.location);
	let curRev = parseInt(url.searchParams.get("rev") ?? "0");
	let curSort = url.searchParams.get("sort") ?? "n";
	function setReverse(e, r) {
		if (r)
			e.classList.add("reversed");
		else
			e.classList.remove("reversed");
	}
	if ($D.referrer) {
		let url = new URL($D.referrer);
		let prevSort = url?.searchParams.get("sort") ?? "n";
		let prevRev = parseInt(url?.searchParams.get("rev") ?? "0");
		if (prevSort == curSort && prevRev != curRev) {
			setReverse(selected, prevRev);
			$AEL("load", _ => {
				selected.classList.add("animate");
				setReverse(selected, curRev);
			});
		}
		else
			setReverse(selected, curRev);
	}
	$AEL("dragenter", ev => {
		let e = getDropTarget(ev.target);
		if (e == null)
			return;
		ev.preventDefault();
		e.classList.add("dragging");
	});
	$AEL("dragover", ev => {
		let e = getDropTarget(ev.target);
		if (e == null)
			return;
		ev.preventDefault();
		e.classList.add("dragging");
	});
	$AEL("dragleave", ev => {
		let e = getDropTarget(ev.target);
		if (e == null)
			return;
		e.classList.remove("dragging");
	});
	let result = $QS(".result");
	let status = $QS(".status");
	let pending = QS(result, ".pending-list");
	let doing = QS(result, ".doing-list");
	let success = QS(result, ".success-list");
	let failed = QS(result, ".failed-list");
	let job_queued = 0;
	let job_done = 0;
	let job_failed = 0;
	status.innerText = <?=J(realpath($rootpath.$path) ?: "")?>;
	function clearXfering() {
		document.body.classList.remove("xfering");
		aborter = new AbortController();
	}
	$AEL("drop", ev => {
		ev.preventDefault();
		let e = getDropTarget(ev.target);
		if (e == null)
			return;
		e.classList.remove("dragging");
		[ pending, doing, success, failed, status ].forEach(e => e.innerHTML = "");
		job_queued = 0;
		job_done = 0;
		job_failed = 0;
		let path = <?=J($path)?>;
		if (e.closest(".directories"))
			path += `${e.dataset.filename}`;

		document.body.classList.add("xfering");
		let jobqueue = JobQueue.create();
		for (let i of ev.dataTransfer.items) {
			switch (i.kind) {
			case "file":
				const ent = i.getAsEntry?.() ?? i.webkitGetAsEntry?.();
				uploadDataItem(jobqueue, ent, path, $W);
				break;
			case "string":
				i.getAsString(s => success.insertAdjacentHTML("beforeend",
					`<p class="miscinfo">string item ${strHTML(s)} is ignored`
				));
				break;
			default:
				success.insertAdjacentHTML("beforeend",
					`<p class="miscinfo">unknown item type ${strHTML(i.kind)} is ignored`
				);
			}
		}
		jobqueue.wait($W).then(_ => {
			clearXfering();
		});
	});
	function updateStatus(queued, done = 0, failed = 0) {
		job_queued += queued;
		job_done += done;
		job_failed += failed;
		status.innerText = `${job_failed} failed, in ${job_done} finished, of total ${job_queued} jobs`;
	}
	$AEL("filelist-queue", ev => {
		let info = ev.detail;
		let msg = `${info.action} ${info.path}/${info.name}`;
		pending.insertAdjacentHTML("beforeend",
			`<p id="${info.id}" class="pending">${strHTML(msg)}`
		);
		updateStatus(1);
	});
	function moveToDoing(e) {
		e.classList.remove("peinding");
		e.classList.add("doing");
		doing.append(e);
	}
	$AEL("filelist-doing", ev => {
		moveToDoing(QS(result, `#${ev.detail.id}`));
	});
	$AEL("filelist-init", ev => {
		const e = QS(result, `#${ev.detail.id}`);
		moveToDoing(e);
		const span = $D.createElement("span");
		e.append(" ", span);
		const total = ev.detail.total;
		span.tinyFileList = { total, done: 0 };
		span.innerText = `(0/${numfmt(total)} bytes)`
	});
	$AEL("filelist-progress", ev => {
		const span = QS(result, `#${ev.detail.id} span`);
		const t = span.tinyFileList;
		t.done += ev.detail.size;
		span.innerText = `(${numfmt(t.done)}/${numfmt(t.total)} bytes)`
	});
	$AEL("filelist-finalize", ev => {
		const span = QS(result, `#${ev.detail.id} span`);
		const t = span.tinyFileList;
		t.done += ev.detail.size;
		span.innerText = `(${numfmt(t.total)} bytes, finalizing)`
	});
	$AEL("filelist-success", ev => {
		let e = QS(result, `#${ev.detail.id}`);
		e.classList.remove("doing");
		e.classList.add("success");
		e.append(`: ${ev.detail.message.toString()}`);
		success.append(e);
		if (success.childElementCount > 3)
			success.firstElementChild.remove();
		updateStatus(0, 1);
	});
	$AEL("filelist-failed", ev => {
		let e = QS(result, `#${ev.detail.id}`);
		e.classList.remove("doing");
		e.classList.add("failed");
		e.append(`: ${ev.detail.message.toString()}`);
		failed.append(e);
		updateStatus(0, 1, 1);
	});
	const CLS_UP = "popped-up";
	const popupMenu = $QS(".file-context-menu");
	let selectedElement = null;
	$AEL("click", ev => {
		if (popupMenu == null)
			return;
		popupMenu.classList.remove(CLS_UP);
		selectedElement = ev.target.closest(".file-context-menu-popper");
		if (selectedElement == null)
			return;
		popupMenu.classList.add(CLS_UP);
		popupMenu.style.left = `${ev.pageX}px`;
		popupMenu.style.top = `${ev.pageY}px`;
	});
	const pathinfo = <?= J([
			"scriptpath" => $scriptpath,
			"path" => $path
		]) ?>;
	function generateContextURL(apiname, opt) {
		let optstr = "";
		if (opt != null)
			optstr = `?${(new URLSearchParams(opt)).toString()}`;
		return `${pathinfo.scriptpath}${apiname}${pathinfo.path}` +
			`${selectedElement.closest("li").dataset.filename}${optstr}`;
	}
	AEL($ID("show-as-text"), "click", _ => {
		location.href = generateContextURL("download.php", { "content-type": "text" });
	});
	AEL($ID("show-as-roff-man"), "click", _ => {
		location.href = generateContextURL("show-roff.php");
	});
	AEL($ID("download"), "click", _ => {
		location.href = generateContextURL("download.php");
	});
	AEL($ID("select-files"), "click", _ => {
		const e = $D.createElement("input");
		e.type = "file";
		e.multiple = true;
		AEL(e, "change", ev => {
			const path = <?=J($path)?>;
			document.body.classList.add("xfering");
			const jobqueue = JobQueue.create();
			for (let i = 0; i < e.files.length; i++) {
				const f = e.files.item(i);
				uploadDataItem(jobqueue, {
					isFile: true, isDirectory: false,
					file: fn => fn(f), name: f.name
				}, path, $W);
			}
			jobqueue.wait().then(_ => {
				clearXfering();
			});
		});
		e.click();
	});
	AEL($ID("logout"), "click", _ => {
		if (confirm("logging out?")) {
			location = `${scriptpath}logout.php`;
		}
	});
	AEL($ID("abort-button"), "click", _ => {
		aborter.abort("aborted by user");
	});
	$AEL("click", ev => {
		if (ev.target.closest(".document-root") == null)
			return;
		location.href = <?=J($base)?>;
	});
	for (let e of [
		$QS("header"), $QS(".item-lists"), $QS(".thumbnail"), $QS(".foot-commander")
	]) {
		const elems = Array.from(e.childNodes);
		e.insertAdjacentHTML("afterbegin", "<div class='dimmer-container'></div>");
		const inner = e.firstElementChild;
		for (child of elems)
			inner.append(child);
		inner.insertAdjacentHTML("beforeend", "<div class='dimmer'></div>");
	}
	const showThumbnail = $ID("show-thumbnail");
	const showThumbnailText = "thumbnail";
	const backToTopText = "top";
	if (thumbcount < 1)
		showThumbnail.remove();
	else {
		const e = showThumbnail;
		e.innerText = showThumbnailText;
		AEL(e, "click", _ => {
			if (e.innerText == backToTopText) {
				$QS(".content-body").scroll(0, 0);
				e.innerText = showThumbnailText;
			}
			else {
				$ID("thumbnail").scrollIntoView({
					block: "start", inline: "start"
				});
				e.innerText = backToTopText;
			}
		})
	}
});
</script>
<style type="text/css"><!--
body {
	margin: 0px;
	padding: 0px;
	line-height: 1.3;
	font-family: sans-serif;
}
.body {
	height: 100vh;
	width: 100vw;
	overflow: hidden;
	display: grid;
	grid-template-rows: auto 1fr;
}
header {
	overflow: auto;
	white-space: nowrap;
	border-bottom: 1px solid silver;
}
h1, .info, .commander,
:is(.item-lists, .thumbnail) .dimmer-container {
	padding: 2px 4px;
}
.commander {
	padding-bottom: 4px;
}
.foot-commander .dimmer-container {
	padding: 0px 4px 4px 4px;
}
.content-body {
	overflow-y: auto;
	overflow-x: hidden;
}
.item-lists, .foot-commander {
	width: 100%;
	overflow-x: auto;
}
h1 {
	font-size: 120%;
	margin: 0px;
}
p { margin: 0em; }
.droptarget-current, li {
	border: solid 2px transparent;
}
.dragging {
	border: solid 2px lime;
}
.upload-target {
	color: silver;
	list-style-type: none;
	border: solid 2px #eee;
	padding: 2px;
}
.dragging .upload-target {
	border-color: transparent;
}
:is(.directories, .files) li {
	white-space: nowrap;
}
.directories {
	list-style-type: "\1F4C1\ ";
}
.date {
	font-family: "courier new", monospace;
	letter-spacing: -0.2ex;
}
.filesize {
	display: inline-block;
	border: 1px solid #eee;
	border-radius: 0.3em;
	color: #777;
	text-align: right;
	padding: 0px 0.1em;
	font-size: 80%;
	min-width: 6em;
}
.directory {
	text-align: center;
}
.animate {
	transition: all 0.3s;
}
.button {
	display: inline-block;
	font-size: 70%;
	border: 1px solid #ccc;
	color: gray;
	padding: 3px;
	border-radius: 0.3em;
	cursor: pointer;
}
.button a {
	text-decoration: none;
	color: inherit;
}
.selected {
	background-color: #eef;
	border-width: 2px;
	border-color: #88f;
	color: #66f;
	padding: 2px;
}
.reversed {
	transform: rotate(180deg);
	color: #eef;
	border-color: #ccf;
	background-color: #66f;
}
.timezone {
	font-size: 80%;
	color: #666;
}
.version {
	font-size: 70%;
	color: #666;
	display: inline-block;
	border: 1px solid #888;
	border-radius: 4px;
	padding: 2px;
	line-height: 1;
}
.result, .status {
	font-size: 80%;
}
.result {
	top: 0px;
	padding: 0px;
	margin-bottom: 0.3em;
	max-height: 50vh;
	min-width: fit-content;
	overflow-y: scroll;
}
.result p {
	padding: 3px;
}
.result .pending {
	color: #bbb;
}
.result .pending::before {
	content: "pending - ";
}
.result .doing {
	color: black;
}
.result .doing::before {
	content: "doing - ";
}
.result .success {
	background-color: #0fc8;
	color: #086;
}
.result .success::before {
	content: "success - ";
}
.result .miscinfo {
	background-color: white;
	color: #888;
}
.result .miscinfo::before {
	content: "info - ";
}
.result .failed {
	background-color: #ff08;
	color: #800;
	font-weight: bold;
}
.result .failed::before {
	content: "FAILED - ";
}
ul {
	margin: 0px;
}
.xfering .no-dimmed {
	position: relative; z-index: 1;
	background-color: white;
}
div.dimmer {
	position: absolute;
	top: 0px; left: 0px;
	width: 100%; height: 100%;
	display: none;
	background-color: #0004;
}
.xfering .dimmer {
	display: block;
}
.dimmer-container {
	position: relative;
	min-width: fit-content;
}
.file-context-menu-popper {
	display: inline-block;
	border: 1px solid #ccc;
	border-radius: 0.3em;
	color: #444;
	padding: 0px 0.2em;
	font-size: 80%;
}
.directories .file-context-menu-popper {
	display: none;
}
.file-context-menu-popper:hover,
.file-context-menu p:hover {
	background-color: #cff;
	cursor: pointer;
}
.file-context-menu {
	display: none;
	position: absolute;
	margin-top: 0.5em;
	margin-left: 0.5em;
	background-color: #fffc;
	border: 2px solid #0ff;
	border-radius: 6px;
	font-size: 90%;
}
.file-context-menu.popped-up {
	display: block;
}
.file-context-menu p {
	margin: 0.5em;
}
#abort-button {
	font-weight: bold;
	color: red;
	border: 2px solid red;
	border-radius: 6px;
	background-color: transparent;
	display: none;
}
#abort-button:hover {
	cursor: pointer;
	color: white;
	background-color: red;
}
.xfering #abort-button {
	display: inline;
}
.foot-commander {
	white-space: nowrap;
}
.final-target {
	font-size: 90%;
	opacity: 0.5;
}
.document-root::before {
	content: "root";
	display: inline-block;
	font-size: 75%;
	margin: 0px 1px;
	padding: 1px;
	border: 1px solid black;
	border-radius: 4px;
	cursor: pointer;
}
.dangling {
	opacity: 0.5;
}
.dangling-indicator::before {
	content: "dangling";
	display: inline-block;
	font-size: 75%;
	color: white;
	background-color: gray;
	padding: 2px;
	border-radius: 4px;
}
.special {
	color: gray;
}
--></style>
<body>
<div class="body">
<header>
<h1><?php putBreadcrumb($base."/", $path) ?></h1>
<p class="info no-dimmed"><button id="abort-button">ABORT</button> <span class="status"></span>
<div class="result no-dimmed">
<div class="failed-list"></div>
<div class="success-list"></div>
<div class="doing-list"></div>
<div class="pending-list"></div>
</div>
<p class="commander"><?php putSortLink($url, "t", "sort by time") ?>
<?php putSortLink($url, "s", "sort by size") ?>
<?php putSortLink($url, "n", "sort by name") ?>
<?php putDotsLink($url) ?>
<span id="show-thumbnail" class="button"></span>
<span class="timezone">TZ=<?= $dt->format("T (O)")?></span>
<span class="version">ver <?=VERSION?></span>
</header>
<div class="content-body">
<?php list_dir($path) ?>
<p class="foot-commander"><button id="select-files">select files to upload</button>
<button id="logout">logout</button>
</div><!-- .content-body -->
</div><!-- .body -->
<div class="file-context-menu">
<p id="show-as-text">show as plaintext
<p id="show-as-roff-man">show roff as manpage
<p id="download">download
</div>
</body>
</html>
