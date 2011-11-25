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

class __PSH {

	const CHILD_EXIT = 0;
	const CHILD_OK = 1;
	const CHILD_CRASH = 2;

	private static $silent_exit=FALSE;
	private static $sockets=array();

	private static function set_silent_exit($flag) {
		self::$silent_exit = $flag;
	}

	public static function shutdown() {
		$err = error_get_last();
		if ($err) {
			switch ($err['type']) {
				case E_ERROR:
				case E_PARSE:
				case E_CORE_ERROR:
				case E_USER_ERROR:
				case E_COMPILE_ERROR:
					socket_write(self::$sockets[2], self::CHILD_CRASH);
					exit;
			}
		}
		if (!self::$silent_exit) socket_write(self::$sockets[2], self::CHILD_EXIT);
	}

	private static function exec_srv($statement=NULL) {
	recursion:
		if ($statement != NULL) {
			eval($statement);
			$err = error_get_last();
			if (($err) && ($err['type'] == E_PARSE)) {
				exit;
			}
			socket_write(self::$sockets[2], self::CHILD_OK);
		}

		while (1) {
			$statement = socket_read(self::$sockets[1], 300, PHP_NORMAL_READ);
			switch (pcntl_fork()) {
				case -1:
					echo "Forking failed. Statement not executed.\n";
					$status = self::CHILD_CRASH;
					exit;
				case 0:
					goto recursion;
			}
			$status = socket_read(self::$sockets[3], 1);
			socket_write(self::$sockets[1], $status);
			self::set_silent_exit($status == self::CHILD_OK);
			if ($status != self::CHILD_CRASH) exit;
			pcntl_wait($_st);
		}
	}

	private static function shell() {
		switch(pcntl_fork()) {
			case -1:
				die("Cannot fork");
			case 0:
				self::exec_srv();
				exit();
		}
		while (1) {
			$line = readline("psh > ");
			if ($line == "") continue;
			readline_add_history($line);
			socket_write(self::$sockets[0], $line."\n");
			$rec = socket_read(self::$sockets[0], 1);
				if ($rec == self::CHILD_EXIT) {
				break;
			}
		}
		self::set_silent_exit(TRUE);
	}

	public static function run() {
		socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair1);
		socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair2);
		self::$sockets = array_merge($pair1, $pair2);
		register_shutdown_function(array('__PSH', 'shutdown'));
		self::shell();
		socket_close(self::$sockets[0]);
		socket_close(self::$sockets[1]);
		socket_close(self::$sockets[2]);
		socket_close(self::$sockets[3]);
	}

}

__PSH::run();
