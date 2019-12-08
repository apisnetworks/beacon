<?php

	namespace apisnetworks\Beacon\Commands;

	use apisnetworks\Beacon\Client;
	use apisnetworks\Beacon\Formatter;
	use apisnetworks\Beacon\Helpers;
	use Symfony\Component\Console\Command\Command;
	use Symfony\Component\Console\Input\InputArgument;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Input\InputOption;
	use Symfony\Component\Console\Output\OutputInterface;

	class EvalCommand extends Command
	{
		protected $format = 'php';

		protected function configure()
		{
			$this->setName('exec');
			$this->setAliases(['e', 'eval']);
			$this->setDescription('Explicitly call a command');
			$this->addArgument('service', InputArgument::REQUIRED, 'service name');
			$this->addArgument('vars', InputArgument::IS_ARRAY, 'service parameters');
			$this->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Use authentication key');
			$this->addOption('set', null, InputOption::VALUE_NONE, 'Set key as default');
			$this->addOption('keyfile', null, InputOption::VALUE_REQUIRED, 'Use file for authentication key',
				Helpers::defaultKeyFile());
			$this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format');
			$this->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'Alternative endpoint to use');
		}

		protected function execute(InputInterface $input, OutputInterface $output)
		{
			$vars = $input->getArgument('vars');
			$args = array_map('static::parseArgs', $vars);
			$format = $input->getOption('format') ?? 'php';
			// @TODO
			$c = '\\apisnetworks\\Beacon\\Formatter\\' . ucwords($this->format);
			if (!class_exists($c)) {
				throw new \InvalidArgumentException("Unknown output format specified ${format} provided");
			}
			$this->format = $format;
			if (null === ($key = $input->getOption('key'))) {
				if (null === ($keyfile = $input->getOption('keyfile'))) {
					$keyfile = Helpers::getStorage() . DIRECTORY_SEPARATOR . Helpers::KEY_FILE;
				}
				$key = trim(file_get_contents($keyfile));
			}
			$endpoint = $input->getOption('endpoint') ?? Client::ENDPOINT;
			$soap = new Client($key, $endpoint, [
				'trace' => $output->isVeryVerbose()
			]);
			$method = $input->getArgument('service');
			$method = str_replace([':','-'], '_', $method);
			$result = $soap->__call($method, $args);

			// @todo register on shutdown instead?
			if (!file_exists(Helpers::defaultKeyFile()) || $input->getOption('set')) {
				file_put_contents(Helpers::defaultKeyFile(), $soap->getKey());
			}

			$this->format($result, $input, $output);

		}

		protected function format($result, InputInterface $input, OutputInterface $output)
		{
			$c = '\\apisnetworks\\Beacon\\Formatter\\' . ucwords($this->format);
			$c::format($result);
		}

		/**
		 * Parse an argument into its components
		 *
		 * When working with commas as an argument value, escape the parameter
		 * or double-quote, e.g.
		 * siteinfo,tpasswd="'foo,bar,baz'" or siteinfo,tpasswd='foo\,bar\,baz'
		 * and not siteinfo,tpasswd='foo,bar,baz'
		 *
		 * @param string|array $args
		 * @return bool|float|int|mixed|\SplStack|string
		 */
		public static function parseArgs(&$args, int $inlist = 0)
		{
			if (is_scalar($args)) {
				$args = preg_split('//', $args, -1, PREG_SPLIT_NO_EMPTY);
			}
			$cmdargs = [];
			$stack = new \SplStack();
			$key = null;
			$inquotes = false;
			$valueExpected = false;
			for (; false !== ($arg = current($args)); next($args)) {
				if ('' === $arg) {
					$cmdargs[] = '';
					continue;
				}
				if ($inquotes) {
					$stack->push($arg);
					if ($inquotes === $arg && $stack->top() !== '\\') {
						// end quoted
						$inquotes = false;
					}
					continue;
				}
				switch ($arg) {
					case '"':
					case "'":
						$stack->push($arg);
						$inquotes = $arg;
						continue 2;
					case ' ':
						if (!$inquotes && $valueExpected) {
							continue 2;
						}
						break;
					case '[':
						next($args);
						$stack->push(static::parseArgs($args, $inlist + 1));
						continue 2;
					case ']':
						if (!$inlist) {
							break;
						}
						$inlist--;
						if ($stack->isEmpty()) {
							return [];
						}
						$merged = static::merge($stack);
						if ($key) {
							$cmdargs[$key] = $merged;
						} else {
							$cmdargs[] = $merged;
						}

						return $cmdargs;
					case '\\':
						$stack->push(next($args));
						continue 2;
					case ':':
						if (!$inlist) {
							break;
						}

						// peek ahead to see what other chars are
						// valid: '[foo:bar,baz:que]', invalid: '[foo:bar:baz]'
						for ($i = key($args) + 1, $n = \count($args); $i < $n; $i++) {
							if ($args[$i] === ',' || ($args[$i] === ']' && $args[$i - 1] !== '\\')) {
								break;
							}
							if ($args[$i] === ':') {
								while (next($args) !== false) {
									$cur = current($args);
									if ($cur === ']') {
										prev($args);
										break 3;
									}
									$arg .= current($args);
								}
							}
						}

						$key = ltrim(static::merge($stack));

						$stack = new \SplStack();
						$valueExpected = true;
						$cmdargs[$key] = $stack;
						continue 2;
					case ',':
						if (!$inlist) {
							break;
						}
						$merged = static::merge($stack);
						if ($key) {
							$cmdargs[$key] = $merged;
							$valueExpected = false;
							$key = null;
						} else {
							$cmdargs[] = $merged;
						}
						$stack = new \SplStack();
						continue 2;
				}
				$stack->push($arg);
			}
			$stack = static::merge($stack);
			if (!\is_array($stack)) {
				return $stack;
			}
			$cmdargs[] = $stack;

			return array_pop($cmdargs);
		}

		private static function merge($stack)
		{
			if (\is_array($stack)) {
				return $stack;
			}
			$str = '';
			if ($stack->isEmpty()) {
				return $str;
			}
			while (!$stack->isEmpty()) {
				$el = $stack->shift();
				if ($el instanceof \SplStack || \is_array($el)) {
					return static::merge($el);
				}
				$str .= $el;
			}
			// impossible to parse empty string as PHP doesn't recognize "" from CLI
			if (($str[0] === "'" || $str[0] === '"') && $str[-1] === $str[0]) {
				return substr($str, 1, -1);
			} else if ($str === "null" || $str === 'None') {
				// backwards compatibility, support None or null
				return null;
			} else if (is_numeric($str)) {
				return false === strpos($str, '.') ? (int)$str : (float)$str;
			} else if ($str === "false") {
				return false;
			} else if ($str === "true") {
				return true;
			}

			return $str;
		}
	}
