#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\ClassLoader\ClassLoader;
use Command\ImportCsvInDataBaseCommand;

$loader = new ClassLoader();
$loader->addPrefix('Command', __DIR__.'/');
$loader->register();

$application = new Application();
$application->add(new ImportCsvInDataBaseCommand());
$application->run();