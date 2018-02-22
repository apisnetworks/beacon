<?php

namespace apisnetworks\Beacon\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImplementationCommand extends Command
{
	const IMPLEMENTATION_SRC = 'https://raw.githubusercontent.com/apisnetworks/' .
		'apnscp-modules/master/modules/%s.php';
	protected function configure()
	{
		$this->setName('show');
		$this->setAliases(['i', 'implementation']);
		$this->setDescription('View command implementation');
		$this->addArgument('method', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$arg = $input->getArgument('method');
		$class = substr($arg, 0, strpos($arg, '_'));
		$method = substr($arg, strpos($arg, '_')+1);
		$url = sprintf(static::IMPLEMENTATION_SRC, $class);
		(new \apisnetworks\Beacon\ReflectionHandler($url))->getCodeFromMethod($method);
		return true;
	}
}