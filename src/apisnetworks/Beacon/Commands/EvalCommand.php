<?php

namespace apisnetworks\Beacon\Commands;

use apisnetworks\Beacon\Client;
use apisnetworks\Beacon\Helpers;
use apisnetworks\Beacon\Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
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
        $this->addOption('keyfile', null, InputOption::VALUE_REQUIRED, 'Use file for authentication key', Helpers::defaultKeyFile());
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format');
        $this->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'Alternative endpoint to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vars = $input->getArgument('vars');
        $args = array_map([$this, 'parse'], $vars);
        $format = $input->getOption('format') ?? 'php';
            // @TODO
        $c = '\\apisnetworks\\Beacon\\Formatter\\'. ucwords($this->format);
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
        $result = $soap->__call($method, $args);

        // @todo register on shutdown instead?
        if (!file_exists(Helpers::defaultKeyFile()) || $input->getOption('set')) {
            file_put_contents(Helpers::defaultKeyFile(), $soap->getKey());
        }

        $this->format($result, $input, $output);

    }

    protected function format($result, InputInterface $input, OutputInterface $output) {
        $ret = 0;
        $c = '\\apisnetworks\\Beacon\\Formatter\\'. ucwords($this->format);

        call_user_func(array($c, 'format'), $result);
    }

	protected function merge($stack)
	{
		if (is_array($stack)) {
			return $stack;
		}
		$str = '';
		while (!$stack->isEmpty()) {
			$el = $stack->shift();
			if ($el instanceof \SplStack || is_array($el)) {
				return $this->merge($el);
			}
			$str .= $el;
		}

		return $str;
	}

	/**
	 * @param $args
	 * @return mixed|\SplStack
	 */
	protected function parse(&$args)
	{
		if (is_scalar($args)) {
			$args = preg_split('//', $args, -1, PREG_SPLIT_NO_EMPTY);
		}
		$cmdargs = [];
		$stack = new \SplStack();
		$key = null;
		$inquotes = false;
		for (; false !== ($arg = current($args)); next($args)) {
			if ('' === $arg) {
				$cmdargs[] = '';
				continue;
			}
			if ($inquotes) {
				if ($inquotes === $arg && $stack->top() !== '\\') {
					// end quoted
					$inquotes = false;
				} else {
					$stack->push($arg);
				}
				continue;
			}
			switch ($arg) {
				case '"':
				case "'":
					$inquotes = $arg;
					continue 2;
				case ' ':
					if (!$inquotes) {
						continue 2;
					}
				case '[':
					next($args);
					$stack->push($this->parse($args));
					continue 2;
				case ']':
					if ($stack->isEmpty()) {
						return [];
					}
					$merged = $this->merge($stack);
					if ($key) {
						$cmdargs[$key] = $merged;
					} else {
						$cmdargs[] = $merged;
					}

					return $cmdargs;
				case '\\':
					$stack->push(next($args));
					break;
				case ':':
					$key = $this->merge($stack);

					$stack = new \SplStack();
					$cmdargs[$key] = $stack;
					continue 2;
				case ',':
					$merged = $this->merge($stack);
					if ($key) {
						$cmdargs[$key] = $merged;
						$key = null;
					} else {
						$cmdargs[] = $merged;
					}
					$stack = new \SplStack();
					continue 2;
			}
			$stack->push($arg);
		}
		$stack = $this->merge($stack);
		if (!is_array($stack)) {
			return $stack;
		}
		$cmdargs[] = $stack;

		return array_pop($cmdargs);
	}
}
