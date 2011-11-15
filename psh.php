#!/usr/bin/php
<?php

error_reporting(E_ALL);

$signal=0;

function receive($sockets, $from) {
	$data = socket_read($sockets[$from], 2048, PHP_NORMAL_READ);
	return unserialize(base64_decode($data));
}

function signal_handler($sig) {
	global $signal;
	$signal = 1;
	$res = pcntl_wait(&$status);
  	if ($status > 0) {
		$signal = 2;
	}
}

function receive_status($sockets) {
	global $signal;
	$signal = 0;
	$rec=0;
	while (!($signal || $rec)) {
		usleep(1000);
		$rec = socket_read($sockets[3], 1);
		pcntl_signal_dispatch();
	}
	return $rec;
}

function send($data, $sockets, $to) {
	socket_write($sockets[$to], base64_encode(serialize($data))."\n");
}

function send_status($sockets, $status) {
	socket_write($sockets[2], $status);
}

function spawn($function, array $params = array()) {
	switch ($child = pcntl_fork()) {
		case -1:
			throw new Exception("Could not fork.");
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
	if ((NULL != $err) && ($err["line"]=1)) {
		switch ($err["type"]) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				exit;
			default:
		}
	}
	send_status($sockets, 1);
	exec_srv($sockets);
}


function exec_srv($sockets) {
	global $signal;
	while (1) {
		$status=1;
		$statement = receive($sockets, 1);
		$pid = spawn('evaluate', array($statement, $sockets));
		$status = receive_status($sockets);
		if ($signal == 1) $status='exit';
		send($status, $sockets, 1);
		if ($signal < 2) { exit; }
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
		$rec = receive($sockets, 0);
	      	if ($rec === 'exit') break;
		pcntl_signal_dispatch();
	}
}

pcntl_signal(SIGCHLD, 'signal_handler');
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair1);
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair2);
$sockets = array_merge($pair1, $pair2);
socket_set_nonblock($sockets[3]);
shell($sockets);
socket_close($sockets[0]);
socket_close($sockets[1]);
socket_close($sockets[2]);
socket_close($sockets[3]);

