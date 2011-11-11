#!/usr/bin/php
<?php

function receive($sockets, $from) {
	$data = socket_read($sockets[$from], 2048, PHP_NORMAL_READ);
	return unserialize(base64_decode($data));
}

function send($data, $sockets, $to) {
	socket_write($sockets[$to], base64_encode(serialize($data))."\n");
}

function exec_srv($sockets) {
	$rec = "";
	while (1) {
		$rec = receive($sockets, 1);
		eval($rec);
	}
}

function spawn($function, array $params = array()) {
	switch ($child = pcntl_fork()) {
		case -1:
			throw new Exception("Could not fork.");
		case 0:
			call_user_func_array($function, $params);
			exit;			
		default:
			while(1) {
				pcntl_waitpid($child, &$status);
				switch ($status) {
					case 0:
						exit;
					default:
						throw new Exception("Child crashed");
				}
			}
	}
}

function shell($sockets) {
	$i=0;
	while (++$i) {
		$line = readline("$i> ");
		readline_add_history($line);
		send($line, $sockets, 0);
	}
}

/*
try {
	spawn('pop');
} catch (Exception $t) {
	echo $t->getMessage()."\n";
}
*/
