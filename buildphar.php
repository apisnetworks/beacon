<?php
$phar = new Phar(__DIR__ . '/../beacon.phar', 0, 'beacon.phar');
// add all files in the project
$phar->buildFromDirectory(dirname(__FILE__), '!(src|vendor)/.*\.php$!');
$phar->addFile('beacon.php');
$phar->setStub($phar->createDefaultStub('beacon.php'));
?>