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
        $this->setName('eval');
        $this->setAliases(['e']);
        $this->setDescription('Explicitly call a command');                                                                                                                              $this->addArgument('service', InputArgument::REQUIRED, 'service name');
        $this->addArgument('vars', InputArgument::IS_ARRAY, 'service parameters');
        $this->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Use authentication key');
        $this->addOption('set', null, InputOption::VALUE_NONE, 'Set key as default');
        $this->addOption('keyfile', null, InputOption::VALUE_REQUIRED, 'Use file for authentication key', Helpers::defaultKeyFile());
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format');
        $this->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'Alternative endpoint to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $method = $input->getArgument('service');
        $args = $this->parse($input->getArgument('vars'));
        $format = $input->getOption('format') ?? 'php';
            // @TODO
        $c = '\\apisnetworks\\Beacon\\Formatter\\'. ucwords($this->format);
        if (!class_exists($c)) {
            throw new \InvalidArgumentException("Unknown output format specified ${format} provided");
        }
        $this->format = $format;
        if (null === ($key = $input->getOption('key'))) {
            if (null === ($keyfile = $input->getOption('keyfile'))) {
                $keyfile = Helpers::getStorage() . DIRECTORY_SEPARATOR . 'beacon.key';
            }
            $key = trim(file_get_contents($keyfile));
        }
        $endpoint = $input->getOption('endpoint') ?? Client::ENDPOINT;
        $soap = new Client($key, $endpoint, [
            'trace' => $input->getOption('verbose')
        ]);

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

    protected function parse($args) {
        $split = function($e) {
            if (false === strpos($e, ':'))
                return $e;
            return explode(':', $e, 2);

        };
        $cmdargs = [];
        while (false !== ($arg = current($args))) {
            if ($arg && $arg[0] == '[' && $arg[strlen($arg)-1] == ']') {
                // array
                $tmp = preg_split('/,\s*/', substr($arg,1,-1));
                $arg = array();
                for ($i=0,$n=sizeof($tmp); $i < $n; $i++) {
                    $t = $split($tmp[$i]);
                    if (is_array($t)) {
                        // trim the key if escaped
                        $parsed = $this->parse((array)trim($t[1], '\'"'));
                        $arg[trim($t[0], '\'"')] = array_shift($parsed);
                    } else {
                        $arg[$i] = trim($t, '\'"');
                    }
                }
                $k = key($args);
                if (is_int($k)) {
                    $cmdargs[] = $arg;
                } else {
                    $cmdargs[$k] = $arg;
                }
            } else {
                $cmdargs[] = $arg;
            }
            next($args);
        }
        return $cmdargs;
    }
}