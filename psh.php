#!/usr/bin/php
<?php

$silent_exit=FALSE;

error_reporting(E_ALL);

define('CHILD_EXIT', 0);
define('CHILD_OK', 1);
define('CHILD_CRASH', 2);

function receive($sockets, $from) {
	$data = socket_read($sockets[$from], 2048, PHP_NORMAL_READ);
	return unserialize(base64_decode($data));
}

function receive_status($sockets, $socket=3) {
	$status = socket_read($sockets[$socket], 1);
	return $status;
}

function send($data, $sockets, $to) {
	socket_write($sockets[$to], base64_encode(serialize($data))."\n");
}

function send_status($sockets, $status, $socket=2) {
	socket_write($sockets[$socket], $status);
}

function spawn($function, array $params = array()) {
	switch ($child = pcntl_fork()) {
		case -1:
			return NULL;
		case 0:
			call_user_func_array($function, $params);
			exit;			
		default:
			return $child;
	}
}

function evaluate($statement, $sockets) {
	eval($statement);
	$err = error_get_last();
	if (($err) && ($err['type'] == E_PARSE)) {
		die();
	}
	send_status($sockets, CHILD_OK);
	exec_srv($sockets);
}

function shutdown($sockets) {
	$err = error_get_last();
	if ($err) {
		switch ($err["type"]) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				send_status($sockets, CHILD_CRASH);
				return;
		}
	}
	global $silent_exit;
	if (!$silent_exit) send_status($sockets, CHILD_EXIT);
}


function exec_srv($sockets) {
	global $silent_exit;
	while (1) {
		$statement = receive($sockets, 1);
		if (spawn('evaluate', array($statement, $sockets))) {
			$status = receive_status($sockets);
		} else {
			echo "Forking failed. Statement not executed.\n";
			$status = CHILD_CRASH;
		}
		send_status($sockets, $status, 1);
		$silent_exit = ($status == CHILD_OK);
		if (($status == CHILD_OK) || ($status == CHILD_EXIT)) break;
		pcntl_wait($_st);
	}
}


function shell($sockets) {
	spawn('exec_srv', array($sockets));
	$i=0;
	while (1) {
		$i++;
		$line = readline(getmypid()." $i> ");
		readline_add_history($line);
		send($line, $sockets, 0);
		$rec = receive_status($sockets, 0);
	      	if ($rec == CHILD_EXIT) {
			break;
		}
	}
	global $silent_exit;
	$silent_exit=TRUE;
}


socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair1);
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair2);
$sockets = array_merge($pair1, $pair2);
register_shutdown_function('shutdown', $sockets);
shell($sockets);
socket_close($sockets[0]);
socket_close($sockets[1]);
socket_close($sockets[2]);
socket_close($sockets[3]);

