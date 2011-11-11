#!/usr/bin/php
<?php

function receive($sockets, $from) {
	$data = socket_read($sockets[$from], 2048, PHP_NORMAL_READ);
	return unserialize(base64_decode($data));
}

function send($data, $sockets, $to) {
	socket_write($sockets[$to], base64_encode(serialize($data))."\n");
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
	exec_srv($sockets);
}

function exec_srv($sockets) {
	$status = 1;
	while (1) {
		$status=1;
		$statement = receive($sockets, 1);
		$pid = spawn('evaluate', array($statement, $sockets));
		usleep(10000);
		$exit_child = pcntl_waitpid($pid, &$status, WNOHANG);
		send($status, $sockets, 1);
		if (($status == 0) || ($exit_child==$pid)) exit;
	}
}


function shell($sockets) {
	spawn('exec_srv', array($sockets));
	$i=0;
	$status = 1;
	while ($status != 0) {
		$i++;
		$line = readline(getmypid()." $i> ");
		readline_add_history($line);
		send($line, $sockets, 0);
		$status = receive($sockets, 0);
		pcntl_wait(&$s);
	}
}

$sockets = array();
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, &$sockets);
shell($sockets);
socket_close($sockets[0]);
socket_close($sockets[1]);

