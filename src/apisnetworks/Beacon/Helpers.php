<?php

namespace apisnetworks\Beacon;

class Helpers {
	/**
	 * Beacon storage directory for key + cache
	 */
	const BEACON_STORAGE = '.beacon/';
	/**
	 * Default beacon key
	 */
	const KEY_FILE = 'beacon.key';

	public static function getStorage() {
		if (PHP_EOL === "\r\n") {
			$home = isset($_SERVER['USERPROFILE']) ? $_SERVER['USERPROFILE'] : '';
		} else {
			$home = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : '';
		}
		return $home . DIRECTORY_SEPARATOR .
			static::BEACON_STORAGE;
	}

	public static function prepStorage() {
		$dir = Helpers::getStorage();
		if (is_dir($dir)) {
			return true;
		}
		return mkdir($dir) && chmod($dir, 0700);
	}

	public static function getHash($file) {
		return sha1($file);
	}

	public static function getCachePath($file) {
		return static::getStorage() . DIRECTORY_SEPARATOR .
			'cache' . DIRECTORY_SEPARATOR .
			static::getHash($file);
	}

	/**
	 * Default key used by Beacon
	 *
	 * @return string
	 */
	public static function defaultKeyFile() {
		return static::getStorage() . DIRECTORY_SEPARATOR .
			static::KEY_FILE;
	}
}