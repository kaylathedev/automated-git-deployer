<?php
function process($manager, $key, $logger) {
  if (!$manager->compareWebAccessKeys($key)) {
    $logger->info('User provided the wrong key on ' . full_url($_SERVER));
    return ['message' => 'Invalid key', 'code' => 1];
  }

  $input = file_get_contents('php://input');
  file_put_contents('plzremove-' . time(), json_encode(json_decode($input), JSON_PRETTY_PRINT));
  $payload = json_decode($input, true);
  if (!is_array($payload)) {
    $logger->info('Client failed to provide repository information');
    return ['message' => 'No repository specified!', 'code' => 1];
  }

  $update = true;
  $id     = $payload['repository']['full_name'];
  $repo   = $manager->getRepositoryById($id);
  if ($repo === null) {
    $logger->info('Unable to find repository! ' . $id);
    return ['message' => 'Unable to find repository!', 'code' => 1];
  }

  if (!empty($payload['push']['changes'])) {
    // Assume that we can't update.
    $update = false;

    // Check for a specific branch.
    foreach ($payload['push']['changes'] as $change) {
      if ($change['new']['type'] === 'branch' && $change['new']['name'] === $repo->branchName) {
        $update = true;
        break;
      }
    }
  }

  if (!$update) {
    $logger->info('BitBucket sent a useless request. ' . json_encode($data));
    return ['message' => 'Invalid request!'];
  }

  $logger->info('BitBucket initiated a repository update: ' . $repo);
  try {
    $manager->initalize();

    $manager->cloneIfNotExists($repo);
    $manager->clean($repo);
    $manager->pull($repo);
    $manager->runHooks($repo);
    $manager->secure($repo);

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
