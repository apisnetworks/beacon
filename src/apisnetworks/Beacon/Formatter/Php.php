<?php

namespace apisnetworks\Beacon\Formatter;
use apisnetworks\Beacon\FormatterInterface;

class Php implements FormatterInterface {
	public static function format($args) {
		if (is_scalar($args)) {
			print $args;
		} else {
			print_r($args);
		}
	}
}