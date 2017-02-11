<?php
namespace apisnetworks\Beacon;
require __DIR__.'/vendor/autoload.php';

use apisnetworks\Beacon\Commands;
use Symfony\Component\Console\Application;
Helpers::prepStorage();
$application = new Application('Beacon');
$application->add(new Commands\EvalCommand());
$application->add(new Commands\ImplementationCommand());
$application->run();
