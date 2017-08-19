#!/usr/bin/php
<?php

if (!isset($argv)) {
  echo 'Access denied', PHP_EOL;
  return;
}

if (count($argv) < 2) {
  echo 'You must pass the ids of the repositories as an argument!', PHP_EOL;
  return;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use AutomatedGitDeployer\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

$manager = Config::createGitHookManagerFromDefaults();
$logger  = new Logger('automated_git_deployer');
$logger->pushHandler(new StreamHandler('php://stdout'));
$manager->logger = $logger;

$repositories = [];

array_shift($argv);

$usedWildcard = false;
if (count($argv) === 1) {
  $firstArgument = $argv[0];
  if ('*' === $firstArgument) {
    $repositories = $manager->getAllRepositories();
    $usedWildcard = true;
  }
}

if (!$usedWildcard) {
  foreach ($argv as $argument) {
    $repository = $manager->getRepositoryById($argument);
    if (null === $repository) {
      $logger->error('The following repository does not exist : ' . $argument);
      return;
    }
    $repositories[] = $repository;
  }
}

try {
  $manager->initalize();

  foreach ($repositories as $repository) {
    $manager->execute($repository);
  }
} catch (Exception $e) {
  $logger->error($e);
}
$manager->cleanUp();
