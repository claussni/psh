#!/usr/bin/php
<?php

function logg($msg) {
	echo "<".getmypid()."> $msg\n";
}

$sockets = array();
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
socket_set_nonblock($sockets[1]);

$i=0;
//while (++$i) {
$commands=array('$a=12;','echo $a;');
foreach($commands as $line) {
	//$line = readline(getmypid()." $i> ");
	$i++;
	logg("want's to execute $line");
		
	$pid = pcntl_fork();
	if ($pid>0) logg("started $pid"); 
	
	if ($pid == -1) {
		die('no fork');
	} else if ($pid) {
		usleep(1000);
		while(!socket_read($sockets[1], 1)) {
			sleep(1);
			if (-1 != pcntl_waitpid($pid, &$status, WNOHANG)) {
				goto next_cmd;
			}
		}
		exit;
next_cmd:
	} else {
		logg("executes $line");
		eval($line);
		logg("succeeded. send kill signal to parent.");
		socket_write($sockets[0], 1, 1);
	}
}

logg("no more commands.");

socket_close($sockets[0]);
socket_close($sockets[1]);

