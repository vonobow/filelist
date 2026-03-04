<?php
// Copyright 2025-2026 akamoz.jp
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

$hideDots = 1;
$sortby = "n";
$descending = 0;

$linkopt = [];

if (isset($_REQUEST["dots"])) {
	$linkopt["dots"] = 1;
	$hideDots = 0;
}
if (in_array($_REQUEST["sort"] ?? null, [ "t", "s", "n" ])) {
	$sortby = $_REQUEST["sort"];
	$linkopt["sort"] = $sortby;
}
if (isset($_REQUEST["rev"])) {
	$descending = intval($_REQUEST["rev"]);
	$linkopt["rev"] = 1;
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
			str_replace("%", "%25", htmlspecialchars($url));
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

function enumFilesystemItems($path) {
	global $rootpath, $url, $hideDots;
	$abspath = $rootpath.$path;
	$fd = @opendir($abspath);
	if ($fd === FALSE)
		return null;
	$dirlist = array();
	$filelist = array();
	for (;;) {
		$dirent = readdir($fd);
		if ($dirent === FALSE)
			break;
		if ($hideDots && (substr($dirent, 0, 1) == "."))
			continue;
		$ent = [ "name" => $dirent ];
		$absname = $abspath.$dirent;
		if (getenv(ENV_NO_FILESTAT) === false)
			$ent = array_merge($ent, getFileStat($absname));
		if (is_dir($absname))
			$dirlist[] = $ent;
		else {
			$ent["isFile"] = is_file($absname);
			if ($ent["isFile"]) {
				$th = $abspath."th/".$dirent;
				$ent["thumbnail"] = is_file($th) && is_readable($th);
			}
			$filelist[] = $ent;
		}
	}
	closedir($fd);
	return [
		"dirs" => $dirlist,
		"files" => $filelist
	];
}

function getExpandedIniSize($key) {
	$v = ini_get($key);
	if (is_numeric($v))
		return intval($v);
	$suf = substr($v, -1);
	$v = substr($v, 0, -1);
	if (!is_numeric($v)) {
		http_response_code(500);
		exit("cannot recognize setting for {$key}?");
	}
	$v = intval($v);
	switch (strtolower($suf)) {
	case "k": return $v * 1024;
	case "m": return $v * 1024 * 1024;
	case "g": return $v * 1024 * 1024 * 1024;
	}
	http_response_code(500);
	exit("unknown size suffix for {$key}");
}

$maxUploadSize = getExpandedIniSize("upload_max_filesize");
$maxPostSize = getExpandedIniSize("post_max_size");
$memorySize = getExpandedIniSize("memory_limit");
if ($maxPostSize > 0)
	$maxUploadSize = min($maxUploadSize, $maxPostSize);
if ($memorySize > 0)
	$maxUploadSize = min($maxUploadSize, $memorySize);
if ($maxUploadSize < 4096) {
	http_response_code(500);
	exit("max upload size is too small?");
}

require __DIR__."/html-header.inc";
require __DIR__."/go-up.pjs";
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
		if (q == null)
			continue;
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
	.then(resp => resp.text().then(msg => {
		if (resp.ok)
			return msg;
		if (resp.status == 413)
			throw "too large to upload: check web server settings";
		throw msg;
	}))
	.then(message => postSuccess(elem, id, message))
	.catch(message => postFailed(elem, id, message));
}

const scriptpath = <?=JJ($scriptpath)?>;
function uploadFile(file, path, elem, id) {
	postEvent(elem, "filelist-doing", { id });
	let form = new FormData();
	form.append("postedfile", file);
	return fetchAndCatch(elem, id,
		`${scriptpath}upload.php/${path}`,
		{ method: "post", body: form }
	);
}

const maxUploadSize = <?=$maxUploadSize?>;
const segmentedUploadLimitBytes = Math.min(10 * 1024 * 1024, Math.floor(maxUploadSize * 0.75));
const useSegmentedUpload = <?=JJ(getenv(ENV_TEMPDIR) !== false)?>;
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

const dateTimeFormatter = new Intl.DateTimeFormat("en-GB", {
	weekday: "short", year: "numeric", month: "short", day: "2-digit",
	hour: "2-digit", minute: "2-digit", second: "2-digit"
});
function formatDateTime(dt, fmt = dateTimeFormatter) {
	const v = fmt.formatToParts(dt)
		.reduce((out, v) => { out[v.type] = v.value; return out; }, {});
	return `${v.weekday}, ${v.day} ${v.month.substring(0, 3)} ${v.year} ${v.hour}:${v.minute}:${v.second}`;
}
function createNewElement(tag, cls = "") {
	const child = $D.createElement(tag);
	child.className = cls;
	return child;
}
function appendNewElement(parent, tag, cls = "") {
	const child = createNewElement(tag, cls);
	parent.append(child);
	return child;
}
function queryString(param) { // URLSearchParams
	const s = param.toString();
	if (s.length < 1)
		return "";
	return `?${s}`;
}
let linkopt = <?=JJ($linkopt)?>;
function appendLink(e, url, text, merge = {}, remove = []) {
	if (typeof url == "string") {
		const q = new URLSearchParams();
		if (merge !== null) {
			for (k in linkopt) q.set(k, linkopt[k]);
			for (v of remove) q.delete(v);
			for (k in merge) q.set(k, merge[k]);
		}
		const a = appendNewElement(e, "a");
		a.href = encodeURI(url) + queryString(q);
		a.innerText = text;
	}
	else
		e.append(text);
}
function appendSymbolicLink(e, link, final) {
	const base = <?=JJ($base)?>;
	if (link.url === false)
		e = appendNewElement(e, "s", "dangling");
	if (typeof link.url != "string")
		e.append(link.dir);
	else if (final) {
		const a = appendNewElement(e, "a", "document-root");
		a.href = <?=JJ($base)?>;
		appendLink(e, `${base}${link.url}`, link.url.replace(/\/+$/, ""));
	}
	else
		appendLink(e, `${base}${link.url}`, link.dir);
	e.append(link.name);
}

let sortBy = <?=JJ($sortby)?>;
let descending = <?=JJ($descending)?>;
let hideDots = <?=JJ($hideDots)?>;
function sortFunc(forDirectory) {
	switch (sortBy) {
	case "s": // by size
		if (descending)
			return (a, b) => b.size - a.size;
		else
			return (a, b) => a.size - b.size;
	case "t": // by time
		if (descending)
			return (a, b) => b.mtime - a.mtime;
		else
			return (a, b) => a.mtime - b.mtime;
	}
	if (descending)
		return (a, b) => a.name == b.name ? 0 : ( b.name < a.name ? -1 : 1 );
	return (a, b) => a.name == b.name ? 0 : ( a.name < b.name ? -1 : 1 );
}

function appendLinkInfo(li, v) {
	li.append(" -> ");
	appendSymbolicLink(li, v.next, false);
	li.append(" ");
	if (v.final.url === false)
		appendNewElement(li, "span", "dangling-indicator");
	else {
		const span = appendNewElement(li, "span", "final-target");
		span.append("(");
		appendSymbolicLink(span, v.final, true);
		span.append(")");
	}
}

function setFileEntryInfo(v, mtime, size, forDirectory) {
	if ("mtime" in v)
		mtime.innerText = formatDateTime(new Date(v.mtime * 1000));
	if (forDirectory) {
		if ("mtime" in v)
			size.innerText = "DIR";
		else {
			size.classList.add("load-stat");
			size.innerText = "SHOW STAT"
		}
	}
	else {
		if ("size" in v)
			size.innerText = numfmt(v.size);
		else {
			size.classList.add("load-stat");
			size.innerText = "show stat"
		}
	}
}

function list_entries(e, entries, url, forDirectory = false) {
<?php if (getenv(ENV_NO_FILESTAT) !== false): ?>
	const statbase = <?=JJ($scriptpath."get-filestat.php".$path)?>;
<?php endif ?>
	if (entries.length < 1)
		return;
	e.innerHTML = "";
	if (!forDirectory || sortBy != "s")
		entries.sort(sortFunc());
	for (const v of entries) {
		const name = v.name;
		const li = createNewElement("li");
		e.append(li);
		li.dataset.filename = name;
		const mtime = appendNewElement(li, "span", "date");
		li.append(" ");
		const size = appendNewElement(li, "span", "filesize");
		if (forDirectory)
			size.classList.add("directory");
		li.append(" ");
		appendNewElement(li, "span",  "file-context-menu-popper").innerText = "...";
		li.append(" ");
		if (!forDirectory && name.match(/\.php$/))
			li.append(name);
		else if (forDirectory)
			appendLink(li, `${url}${name}/`, name);
		else if (v.isFile)
			appendLink(li, `${url}${name}`, name);
		else
			appendNewElement(li, "i", "special").innerText = name;
<?php if (getenv(ENV_NO_FILESTAT) !== false): ?>
		li.dataset.staturl = `${statbase}${name}`;
<?php endif ?>
		setFileEntryInfo(v, mtime, size, forDirectory);
		if (v.isLink)
			appendLinkInfo(li, v);
	}
}

const fsysItems = <?=JJ(enumFilesystemItems($path))?>;
const filepath = <?=JJ(prependPathPrefix($path))?>;
function loadThumbnails() {
	const thumb = $ID("thumbnail");
	thumb.innerHTML = "";
	fsysItems.files.filter(v => v.thumbnail).forEach(v => {
		const a = appendNewElement(thumb, "a");
		a.href = `${filepath}${v.name}`;
		const img = appendNewElement(a, "img");
		img.height = 100;
		img.src = `${filepath}th/${v.name}`;
		thumb.append(" ");
	});
}
function listFilesystemItems() {
	const url = <?=JJ($base.$path)?>;
	const outer = $ID("filesystem-item-list");
	if (fsysItems == null) {
		outer.innerHTML = "";
		return;
	}
	list_entries(QS(outer, "ul.directories"), fsysItems.dirs, url, true);
	list_entries(QS(outer, "ul.files"), fsysItems.files, filepath);
}
$AEL("DOMContentLoaded", _ => {
	let selectedSortButton = $QS(`.commander button[data-sort-by="${sortBy}"]`);
	if (selectedSortButton == null) {
		selectedSortButton = $QS(`.commander button[data-sort-by="n"]`);
		sortBy = "n";
		delete linkopt.sort;
	}
	listFilesystemItems();
	let selected = $QS(".commander .selected");
	let url = new URL($D.location);
	let curRev = parseInt(url.searchParams.get("rev") ?? "0");
	let curSort = url.searchParams.get("sort") ?? "n";
	function isDropAllowed(ev) {
		if (!Array.from(ev.dataTransfer.items).some(v => v.kind == "file")) {
			ev.dataTransfer.dropEffect = "none";
			ev.dataTransfer.effectAllowed = "none";
			return false;
		}
		return true;
	}
	$AEL("dragenter", ev => {
		if (!isDropAllowed(ev))
			return;
		let e = getDropTarget(ev.target);
		if (e == null)
			return;
		ev.preventDefault();
		e.classList.add("dragging");
	});
	$AEL("dragover", ev => {
		if (!isDropAllowed(ev))
			return;
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

	if (fsysItems == null) {
		status.innerText = "incorrect path";
		status.classList.add("error");
	}
	else
		status.innerText = <?=JJ(realpath($rootpath.$path))?>;
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
		let path = <?=JJ($path)?>;
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
		const sel = ev.target.closest(".file-context-menu-popper");
		if (sel == null)
			return;
		selectedElement = sel;
		popupMenu.classList.add(CLS_UP);
		popupMenu.style.left = `${ev.pageX}px`;
		popupMenu.style.top = `${ev.pageY}px`;
	});
	const pathinfo = <?= JJ([
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
	function openContextUrl(ev, apiname, opt) {
		ev.preventDefault();
		const url = generateContextURL(apiname, opt);
		if (ev.shiftKey)
			open(url, "", "popup=false,top=-1");
		else if (ev.ctrlKey)
			open(url);
		else
			location = url;
	}
	AEL($ID("show-as-text"), "click", ev => {
		openContextUrl(ev, "download.php", { "content-type": "text" });
	});
	AEL($ID("show-as-json"), "click", ev => {
		openContextUrl(ev, "show-json.php");
	});
	AEL($ID("show-as-roff-man"), "click", ev => {
		openContextUrl(ev, "show-roff.php");
	});
	AEL($ID("show-as-diff"), "click", ev => {
		openContextUrl(ev, "show-diff.php");
	});
	AEL($ID("download"), "click", ev => {
		location = generateContextURL("download.php");
	});
	AEL($ID("select-files"), "click", _ => {
		const e = $D.createElement("input");
		e.type = "file";
		e.multiple = true;
		AEL(e, "change", ev => {
			const path = <?=JJ($path)?>;
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

	// commanders
	function handleSortButtons(ev, e, force) {
		// sort buttons
		if (e.dataset.sortBy == sortBy)
			descending = 1 - descending;
		else {
			const prev = QS(ev.currentTarget, `[data-sort-by="${sortBy}"]`);
			prev.classList.remove("selected", "reversed");
			e.classList.add("selected");
			sortBy = e.dataset.sortBy;
			descending = 0;
		}
		if (force)
			descending = 1;
		e.classList[descending?"add":"remove"]("reversed");
		const url = new URL(location);
		const params = url.searchParams;
		params.set("sort", sortBy);
		if (descending)
			params.set("rev", 1);
		else
			params.delete("rev");
		history.replaceState(null, "", url);
		listFilesystemItems();
	}
	function handleDotFiles(e, force) {
		const url = new URL(location);
		const params = url.searchParams;
		if (hideDots || force)	// make dots visible
			params.set("dots", 1);
		else	// make dots invisible
			params.delete("dots");
		location = url;	// re-generate item lists by PHP
	}
	function handleCommander(ev, force = false) {
		const e = ev.target.closest("button");
		if (e == null)
			return;
		ev.preventDefault();
		if (e.id == "dot-files")
			handleDotFiles(e, force);
		else if (e.dataset.sortBy)
			handleSortButtons(ev, e, force);
	}
	AEL($QS(".commander"), "click", ev => handleCommander(ev));
	AEL($QS(".commander"), "contextmenu", ev => handleCommander(ev, true));
	selectedSortButton.classList.add("selected");
	if (descending)
		selectedSortButton.classList.add("reversed");
	if (!hideDots)
		$ID("dot-files").classList.add("selected");

	// time zone
	const tzname = (new Intl.DateTimeFormat([], {
			hour: "2-digit", timeZoneName: "short"
		}))
		.formatToParts(Date.now())
		.find(v => v.type == "timeZoneName")
		.value;
	const tzval = (new Intl.DateTimeFormat([], {
			hour: "2-digit", timeZoneName: "longOffset"
		}))
		.formatToParts(Date.now())
		.find(v => v.type == "timeZoneName")
		.value
		.replace(/[a-z]*/i, "");
	$ID("timezone").innerText = `TZ=${tzname} (${tzval})`;

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

	// thumbnails
	const showThumbnail = $ID("show-thumbnail");
	if (fsysItems.files.some(v => v.thumbnail)) {
<?php if (getenv(ENV_NO_THUMBNAIL) === false): ?>
		loadThumbnails();
<?php else: ?>
		AEL($ID("load-thumbnail"), "click", _ => loadThumbnails());
<?php endif ?>
		AEL(showThumbnail, "click", _ => {
			$ID("thumbnail").scrollIntoView({
				block: "start", inline: "start"
			});
		})
	}
	else {
		showThumbnail.remove();
		$ID("thumbnail").remove();
	}
	AEL($ID("go-top"), "click", _ => {
		$QS(".content-body").scroll(0, 0);
	})

	// max upload size
	$QS("#max-upload-size span").innerText = numfmt(
		useSegmentedUpload ? segmentedUploadLimitBytes : maxUploadSize
	);

	// load file stat
<?php if (getenv(ENV_NO_FILESTAT) !== false): ?>
	$AEL("click", ev => {
		const span = ev.target.closest(".load-stat");
		if (span == null)
			return;
		const li = span.closest("li");
		fetch(li.dataset.staturl).then(resp => {
			if (!resp.ok) throw "failed to get filestat";
			return resp.json();
		})
		.then(json => {
			if (!json.success) throw "got error while getting filestat";
			const v = json.ent;
			console.log(v);
			const mtime = QS(li, "span.date");
			const size = QS(li, "span.filesize");
			setFileEntryInfo(v, mtime, size, v.isDir);
			if (v.isLink)
				appendLinkInfo(li, v);
			size.classList.remove("load-stat")
		});
	});
<?php endif ?>
});
</script>
<style type="text/css"><!--
body, .commander button {
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
h1, .info, .commander, #max-upload-size,
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
.filesize.load-stat {
	border-color: gray;
	border-radius: 0;
	color: gray;
	text-align: center;
	cursor: pointer;
}
.directory {
	text-align: center;
}
.animate {
	transition: all 0.3s;
}
.button, .commander button {
	display: inline-block;
	font-size: 70%;
	border: 1px solid #ccc;
	color: gray;
	padding: 3px;
	border-radius: 0.3em;
	cursor: pointer;
	background-color: white;
}
.commander button {
	transition: transform 0.3s;
}
button a {
	text-decoration: none;
	color: inherit;
}
.commander .selected {
	background-color: #eef;
	border-width: 2px;
	border-color: #88f;
	color: #66f;
	padding: 2px;
}
.commander .reversed {
	transform: rotate(180deg);
}
.commander .reversed, .file-context-menu p:hover {
	color: #eef;
	border-color: #ccf;
	background-color: #66f;
}
#timezone, #max-upload-size {
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
.status {
	border: solid 1px transparent;
	border-radius: 4px;
	padding: 0px 2px;
}
.status.error {
	border-color: #c00;
	font-weight: bold;
	color: #c00;
	background-color: #fee;
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
:is(.xfering, .modal) .dimmer {
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
	cursor: pointer;
}
.file-context-menu-popper:hover {
	background-color: #cff;
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
	user-select: none;
}
.file-context-menu.popped-up {
	display: block;
}
.file-context-menu p {
	border: 1px solid transparent;
	border-radius: 0.3em;
	padding: 0.1em;
	margin: 0.4em;
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
.document-root {
	text-decoration: none;
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
<p class="commander">
<?php if (getenv(ENV_NO_FILESTAT) === false): ?>
<button id="sort-by-time" data-sort-by="t">sort by time</button>
<button id="sort-by-size" data-sort-by="s">sort by size</button>
<?php endif ?>
<button id="sort-by-name" data-sort-by="n">sort by name</button>
<button id="dot-files">dot files</button>
<span id="go-top" class="button">top</span>
<span id="show-thumbnail" class="button">thumbnail</span>
<span id="timezone"></span>
<span class="version">ver <?=VERSION.(defined("VARIANT")?"-".VARIANT:"")?></span>
</header>
<div class="content-body">
<div id="filesystem-item-list" class="item-lists"><ul class="directories">
</ul><ul class="files droptarget-current">
<li class="upload-target">drop files here to upload
</ul></div>
<p id="thumbnail" class="thumbnail">
<button id="load-thumbnail">load thumbnail</button>
<hr>
<p class="foot-commander"><button id="select-files">select files to upload</button>
<button id="logout">logout</button>
<p id="max-upload-size"><?php
if (getenv(ENV_TEMPDIR) == false)
	echo ENV_TEMPDIR, " is not set, max upload size is <span></span> bytes";
else
	echo "segmented upload is enabled, segment size is <span></span> bytes";
?>
</div><!-- .content-body -->
</div><!-- .body -->
<div class="file-context-menu">
<p id="show-as-text">show as plaintext
<p id="show-as-json">pretty JSON
<p id="show-as-roff-man">show roff as manpage
<p id="show-as-diff">show as diff
<p id="download">download
</div>
</body>
</html>
