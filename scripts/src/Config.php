<?php
namespace AutomatedGitDeployer;

use Exception;
use AutomatedGitDeployer\GitHookManager;
use AutomatedGitDeployer\Repository;

define('PROJECT_ROOT', dirname(dirname(__DIR__)));

class Config
{

  public static function createGitHookManagerFromDefaults()
  {
    return self::createGitHookManager(PROJECT_ROOT . '/config/config.json');
  }

  public static function createGitHookManager($file)
  {
    $rawConfig = file_get_contents($file);
    if (false === $rawConfig) {
      throw new Exception('Error while trying to access the config file!');
    }
    $jsonConfig = json_decode($rawConfig, true);
    if (null === $jsonConfig) {
      throw new Exception('The configuration file is not a json object!');
    }

    $manager = new GitHookManager();

    foreach ($jsonConfig['repos'] as $rawRepo) {
      $repoName = $rawRepo['name'];
      $repoId = $rawRepo['id'];
      $repoBranch = $rawRepo['branch'];
      $repoLocation = $rawRepo['location'];
      $repoRemote = $rawRepo['remote'];

      $repo = new Repository($repoName, $repoId, $repoBranch, $repoLocation, $repoRemote);

      if (isset($rawRepo['hooks'])) {
        $hooks = $rawRepo['hooks'];
        if (isset($hooks['post-update'])) {
          foreach ($hooks['post-update'] as $command) {
            $repo->hooksPostUpdate[] = new Hook($command);
          }
        }
      }
      $manager->addRepository($repo);
    }

    if (isset($jsonConfig['home'])) {
      $manager->homeLocation = $jsonConfig['home'];
    }

    if (isset($jsonConfig['web-access-key'])) {
      $manager->webAccessKey = $jsonConfig['web-access-key'];
    }

    if (isset($jsonConfig['known-hosts'])) {
      foreach ($jsonConfig['known-hosts'] as $knownHost) {
        $manager->addKnownHost($knownHost);
      }
    }

    if (isset($jsonConfig['private-key'])) {
      $manager->setPrivateKey($jsonConfig['private-key']);
    }

    return $manager;
  }

}
