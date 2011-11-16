#!/usr/bin/php
<?php
/**
 * Copyright 2011 Ralf Claussnitzer
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

$silent_exit=FALSE;
$sockets=array();

define('CHILD_EXIT', 0);
define('CHILD_OK', 1);
define('CHILD_CRASH', 2);

function set_silent_exit($flag) {
	global $silent_exit;
	$silent_exit = $flag;
}

function shutdown() {
	global $sockets;
	$err = error_get_last();
	if ($err) {
		switch ($err['type']) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_USER_ERROR:
			case E_COMPILE_ERROR:
				socket_write($sockets[2], CHILD_CRASH);
				exit;
		}
	}
	global $silent_exit;
	if (!$silent_exit) socket_write($sockets[2], CHILD_EXIT);
}


function exec_srv($statement=NULL) {
	global $sockets;
recursion:
	if ($statement != NULL) {
		eval($statement);
		$err = error_get_last();
		if (($err) && ($err['type'] == E_PARSE)) {
			exit;
		}
		socket_write($sockets[2], CHILD_OK);
	}

	while (1) {
		$statement = socket_read($sockets[1], 300, PHP_NORMAL_READ);
		switch (pcntl_fork()) {
			case -1:
				echo "Forking failed. Statement not executed.\n";
				$status = CHILD_CRASH;
				exit;
			case 0:
				goto recursion;
		}
		$status = socket_read($sockets[3], 1);
		socket_write($sockets[1], $status);
		set_silent_exit($status == CHILD_OK);
		if ($status != CHILD_CRASH) exit;
		pcntl_wait($_st);
	}
}


function shell() {
	global $sockets;
	switch(pcntl_fork()) {
		case -1:
			die("Cannot fork");
		case 0:
			exec_srv();
			exit();
	}
	while (1) {
		$line = readline("psh > ");
		if ($line == "") continue;
		readline_add_history($line);
		socket_write($sockets[0], $line."\n");
		$rec = socket_read($sockets[0], 1);
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

