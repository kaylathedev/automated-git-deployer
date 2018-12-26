<?php
function process($manager, $key, $logger) {
  if (!$manager->compareWebAccessKeys($key)) {
    $logger->info('User provided the wrong key : key = ' . $key);
    return ['message' => 'Invalid key', 'code' => 1];
  }

  if (!isset($_GET['id'])) {
    $logger->info('User failed to provide a repository id');
    return ['message' => 'No repository specified!', 'code' => 1];
  }

  $id = trim($_GET['id']);
  $repo = $manager->getRepositoryById($id);

  if ($repo === null) {
    $logger->info('User provided a non-existent repository id: ' . $id);
    return ['message' => 'Unable to find repository!', 'code' => 1];
  }

  $logger->info('User started update of repository: ' . $repo);
  try {
    $manager->initalize();
    $manager->execute($repo);
  } catch (Exception $e) {
    $logger->error($e);
    $manager->cleanUp();
    return ['message' => 'Exception! ' . $e, 'code' => 1];
  }
  $manager->cleanUp();

  $logger->info('Successfully updated repository: ' . $repo);
  return ['message' => 'Finished update!', 'code' => 0];
}


date_default_timezone_set('America/New_York');

$projectDirectory = dirname(__DIR__);
require $projectDirectory . '/vendor/autoload.php';

use AutomatedGitDeployer\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;

$key     = isset($_GET['key']) ? $_GET['key'] : null;
$manager = Config::createGitHookManagerFromDefaults();
$logger  = new Logger('automated_git_deployer');

$logger->pushHandler(new SyslogHandler('php_automated_git_deployer'));
$manager->logger = $logger;

$result = process($manager, $key, $logger);

header('Content-type: application/json');
echo json_encode($result);
