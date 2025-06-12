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

const SESSIONVER = 1;
const SESSIONTIMEOUT = 1800;
const COOKIE_LIFETIME = 3600;
const PARAMHOLDTIME = 60;
const REGENERATEDURATION = 600;
const MAXSESSIONDURATION = 43200;

// $_SESSIONのキー
const KEY_SESSIONVER = "sessionvresion";
const KEY_AUTHAT = "authorized_at";
const KEY_REGENAT = "regenerated_at";
const KEY_EXTENDEDAT = "extended_at";
const KEY_EXPIREDAT = "expired_at";
const KEY_REQPARAM = "requestparam";

// クッキーのキー
const COOKIE_OLDSESSION = "PHPOLDSESSION";

function L($msg, $dig = 0) {
	$tr = (new Exception)->getTrace()[$dig];
	$hd = basename($tr["file"]) . "(" . $tr["line"] . ")";
	$eol = "; ";
	switch (PHP_SAPI) {
	case "cli":
	case "cli-server":
		$eol = "";
	}
	error_log("{$hd}: {$msg}{$eol}");
}

$usinghttps = false;

function getSessionParam(&$v, $k) {
	if (!isset($_SESSION[$k]))
		return false;
	$v = $_SESSION[$k];
	return true;
}

function isTimeout($k, $tout) {
	if (!getSessionParam($at, $k)) {
		L("no {$k} key in the session");
		return true;
	}
	if (time() - $at > $tout) {
		L("{$k} has timed out");
		return true;
	}
	return false;
}

function startSession() {
	global $usinghttps;
	if (session_status() == PHP_SESSION_ACTIVE)
		return;
	if (session_start([
			"use_strict_mode" => true,
			"cookie_secure" => $usinghttps,
			"cookie_httponly" => true,
			"cookie_samesite" => "Lax",
			"cookie_lifetime" => COOKIE_LIFETIME
		]) === false
	)
		SRVERR("failed to start session");
}

function regenerateSession() {
	session_regenerate_id();
	$sessionid = session_id();
	$_SESSION[KEY_REGENAT] = time();
	L("session ID regenerated: {$sessionid}");
}

function isAuthenticated() {
	startSession();
	if (
		!isTimeout(KEY_EXTENDEDAT, SESSIONTIMEOUT) &&
		!isTimeout(KEY_AUTHAT, MAXSESSIONDURATION) &&
		getSessionParam($sessionver, KEY_SESSIONVER) &&
		($sessionver == SESSIONVER)
	) {
		L("session valid");
		$_SESSION[KEY_EXTENDEDAT] = time();
		if (isTimeout(KEY_REGENAT, REGENERATEDURATION))
			regenerateSession();
		return true;
	}
	L("session has expired, or is invalid");
	return false;
}

function startNewSession() {
	L("starting new authenticated session");
	startSession();
	$oldid = $_COOKIE[COOKIE_OLDSESSION] ?? null;
	if ($oldid !== null) {
		L("got old session cookie: {$oldid}");
		$newid = session_id();
		session_write_close();
		session_id($oldid);
		startSession();
		if (
			(getSessionParam($sessionver, KEY_SESSIONVER) == false) ||
			($sessionver != SESSIONVER) ||
			(getSessionParam($expiredat, KEY_EXPIREDAT) == false) ||
			(time() >= $expiredat + PARAMHOLDTIME)
		) {
			// don't use it.
			L("session version mismatched, or too old, drop it");
			$_SESSION = array();
			session_write_close();
			session_id($newid);
			startSession();
		}
	}

	$_SESSION[KEY_SESSIONVER] = SESSIONVER;
	$_SESSION[KEY_AUTHAT] = time();
	$_SESSION[KEY_EXTENDEDAT] = time();
	unset($_SESSION[KEY_EXPIREDAT]);
	regenerateSession();
}

require_once __DIR__."/pathprefix.inc";
function exitWithNewSession() {
	startNewSession();
	$uri = prependPathPrefix($_SERVER['REQUEST_URI']);
	header("Location: {$uri}", true, 303);
	exit(0);
}

function checkAuthenticated($keepopen = false) {
	global $usinghttps;
	if (isAuthenticated()) {
		if (getSessionParam($postparam, KEY_REQPARAM)) {
			L("restoring parameters");
			unset($_SESSION[KEY_REQPARAM]);
			foreach ($postparam as $k => $v) {
				$_POST[$k] = $v;
				$_REQUEST[$k] = $v;
			}
		}
		if (!$keepopen)
			session_write_close();
		return true;
	}

	if (isset($_SESSION[KEY_AUTHAT])) {
		L("saving parameters");
		$_SESSION[KEY_REQPARAM] = $_POST;
		unset($_SESSION[KEY_AUTHAT]);
		unset($_SESSION[KEY_EXTENDEDAT]);
		$_SESSION[KEY_EXPIREDAT] = time();
		setcookie(COOKIE_OLDSESSION, session_id(), [
			"expires" => time() + PARAMHOLDTIME,
			"secure" => $usinghttps,
			"httponly" => true,
		]);
	}
	return false;
}

function logout() {
	startSession();
	$_SESSION = [];
}
