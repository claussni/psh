#!/usr/bin/php
<?php

$silent_exit=FALSE;
$sockets=array();

define('CHILD_EXIT', 0);
define('CHILD_OK', 1);
define('CHILD_CRASH', 2);

function set_silent_exit($flag) {
	global $silent_exit;
	$silent_exit = $flag;
}

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
				exit;
		}
	}
	global $silent_exit;
	if (!$silent_exit) send_status(CHILD_EXIT);
}


function exec_srv($statement=NULL) {
recursion:
	global $sockets;
	if ($statement != NULL) {
		eval($statement);
		$err = error_get_last();
		if (($err) && ($err['type'] == E_PARSE)) {
			exit;
		}
		send_status(CHILD_OK);
	}

	while (1) {
		$statement = receive(1);
		switch (pcntl_fork()) {
			case -1:
				echo "Forking failed. Statement not executed.\n";
				$status = CHILD_CRASH;
				exit;
			case 0:
				goto recursion;
		}
		$status = receive_status();
		send_status($status, 1);
		set_silent_exit($status == CHILD_OK);
		if ($status != CHILD_CRASH) exit;
		pcntl_wait($_st);
	}
}


function shell() {
	switch(pcntl_fork()) {
		case -1:
			die("Cannot fork");
		case 0:
			exec_srv();
			exit();
	}
	while (1) {
		$line = readline("psh > ");
		readline_add_history($line);
		send($line, 0);
		$rec = receive_status(0);
	      	if ($rec == CHILD_EXIT) {
			break;
		}
	}
	set_silent_exit(TRUE);
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

