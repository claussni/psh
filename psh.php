#!/usr/bin/php
<?php

$silent_exit=FALSE;
$sockets=array();

error_reporting(E_ALL);

define('CHILD_EXIT', 0);
define('CHILD_OK', 1);
define('CHILD_CRASH', 2);

function receive($from) {
	global $sockets;
	$data = socket_read($sockets[$from], 2048, PHP_NORMAL_READ);
	return unserialize(base64_decode($data));
}

function receive_status($socket=3) {
	global $sockets;
	$status = socket_read($sockets[$socket], 1);
	return $status;
}

function send($data, $to) {
	global $sockets;
	socket_write($sockets[$to], base64_encode(serialize($data))."\n");
}

function send_status($status, $socket=2) {
	global $sockets;
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

function shutdown() {
	global $sockets;
	$err = error_get_last();
	if ($err) {
		switch ($err["type"]) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				send_status(CHILD_CRASH);
				return;
		}
	}
	global $silent_exit;
	if (!$silent_exit) send_status(CHILD_EXIT);
}


function exec_srv($statement=NULL) {
	global $sockets;
	global $silent_exit;
	while (1) {
		if (!$statement) {
			$statement = receive(1);
			if (spawn('exec_srv', array($statement))) {
				$status = receive_status();
			} else {
				echo "Forking failed. Statement not executed.\n";
				$status = CHILD_CRASH;
			}
			send_status($status, 1);
			$silent_exit = ($status == CHILD_OK);
			if (($status == CHILD_OK) || ($status == CHILD_EXIT)) break;
			pcntl_wait($_st);
			$statement=NULL;
		} else {
			eval($statement);
			$err = error_get_last();
			if (($err) && ($err['type'] == E_PARSE)) {
				die();
			}
			send_status(CHILD_OK);
		}
	}
}


function shell() {
	global $sockets;
	spawn('exec_srv');
	$i=0;
	while (1) {
		$i++;
		$line = readline(getmypid()." $i> ");
		readline_add_history($line);
		send($line, 0);
		$rec = receive_status(0);
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
register_shutdown_function('shutdown');
shell();
socket_close($sockets[0]);
socket_close($sockets[1]);
socket_close($sockets[2]);
socket_close($sockets[3]);

