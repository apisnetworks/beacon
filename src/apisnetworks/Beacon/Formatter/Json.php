<?php

namespace apisnetworks\Beacon\Formatter;
use apisnetworks\Beacon\FormatterInterface;

class Json implements FormatterInterface {
	public static function format($args) {
		print json_encode($args);
	}
}