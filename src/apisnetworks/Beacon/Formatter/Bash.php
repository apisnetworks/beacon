<?php

namespace apisnetworks\Beacon\Formatter;
use apisnetworks\Beacon\FormatterInterface;

class Bash implements FormatterInterface {
	public static function format($args) {
		if (is_scalar($args)) {
			print $args;
		} else {
			echo '(',
				join(" ", array_map(function($v, $k) {
					if (is_array($v)) {
						self::format($v);
					} else {
						return '[' . escapeshellarg($k) . ']=' .
							escapeshellarg($v);

					}
				}, $args, array_keys($args))),
			')';
		}
	}
}