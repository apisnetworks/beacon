<?php
namespace apisnetworks\Beacon;
require __DIR__.'/vendor/autoload.php';

use apisnetworks\Beacon\Commands;
use Symfony\Component\Console\Application;

ini_set('default_socket_timeout', max((int)ini_get('default_socket_timeout'), 300));
Helpers::prepStorage();
$application = new Application('Beacon');
$application->add(new Commands\EvalCommand());
$application->add(new Commands\ImplementationCommand());
$application->run();
