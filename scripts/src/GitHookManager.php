<?php
namespace AutomatedGitDeployer;

use Psr\Log\LoggerInterface;

class GitHookManager
{

  public $homeLocation;
  public $webAccessKey;
  public $logger;
  public $loggingDateFormat = 'Y-m-d H:i:sP';
  public $repositories = [];

  private $privateKeyContents;
  private $knownHosts = [];

  private $gitBinary;

  public function __construct()
  {
  }

  public function setPrivateKey($contents)
  {
    $this->privateKeyContents = $contents;
  }

  public function addKnownHost($line)
  {
    $this->knownHosts[] = $line;
  }

  public function addRepository(Repository $repository)
  {
    $this->repositories[] = $repository;
  }

  public function getRepositoryById($id)
  {
    foreach ($this->repositories as $repository) {
      if ($repository->id === $id) {
        return $repository;
      }
    }
    return null;
  }

  public function getAllRepositories()
  {
    return $this->repositories;
  }

  public function getTempDir()
  {
    return PROJECT_ROOT . '/tmp';
  }

  public function initalize($gitBinary = 'git')
  {
    $this->gitBinary = $gitBinary;

    $tempDir = $this->getTempDir();
    if (!is_dir($tempDir)) {
      mkdir($tempDir, 0600);
    }

    $this->privateKeyLocation = tempnam($tempDir, 'git-automated-deployer-ENVIRONMENT-PKEY');
    if ($this->privateKeyLocation === false) {
      $msg = 'Unable to create temp file for private key!';
      throw new DeploymentException($msg);
    }

    $this->knownHostsLocation = tempnam($tempDir, 'git-automated-deployer-ENVIRONMENT-KNOWN-HOSTS');
    if ($this->knownHostsLocation === false) {
      $msg = 'Unable to create temp file for known hosts!';
      throw new DeploymentException($msg);
    }

    file_put_contents($this->privateKeyLocation, implode("\n", $this->privateKeyContents));
    file_put_contents($this->knownHostsLocation, implode("\n", $this->knownHosts));
  }

  /**
   * Executes the necessary commands to deploy the website.
   */
  public function execute(Repository $repository)
  {
    if ($this->logger !== null) {
      $this->logger->info('Attempting to update repository. ' . $repository);
    }

    $workingDir = $repository->location;
    $gitDir = $workingDir . '/.git';

    $gitArgs = '--work-tree=' . escapeshellarg($workingDir);
    $gitArgs .= ' --git-dir=' . escapeshellarg($gitDir);

    if (is_dir($workingDir)) {
      // Check if there's a repository
      $args = [$gitArgs, 'status -s'];
      $result = self::executeCommand($this->gitBinary, $args);
      if ($result['code'] === 128) {
        // There's exists no repository
        $msg = 'Unexpected state! Folder exists with no git repo.';
        if ($this->logger !== null) {
          $this->logger->error($msg);
        }
        throw new DeploymentException($msg);
      }
    } else {
      $args = ['clone', $repository->origin, $repository->location];
      $result = self::executeCommand($this->gitBinary, $args);
      if ($result['code'] !== 0) {
        $output = self::formatCommandResult($result);
        $msg = 'Error when cloning!' . PHP_EOL
            . 'Command: ' . $result['cmd'] . PHP_EOL
            . 'Output:' . PHP_EOL
            . $output;
        if ($this->logger !== null) {
          $this->logger->error($msg);
        }
        throw new DeploymentException($msg);
      }
      $this->logger->info('Cloned repository at ' . $repository->location);
    }

    // Reset repository to head
    $args = [$gitArgs, 'clean -f --quiet'];
    $result = self::executeCommand($this->gitBinary, $args);
    if ($result['code'] !== 0) {
      $output = self::formatCommandResult($result);
      $msg = 'Error when reseting repo!' . PHP_EOL . $output;
      if ($this->logger !== null) {
        $this->logger->error($msg);
      }
      throw new DeploymentException($msg);
    }

    // Pull Changes
    $args = [$gitArgs, 'pull'];
    $result = self::executeCommand($this->gitBinary, $args);
    if ($result['code'] !== 0) {
      $output = self::formatCommandResult($result);
      $msg = 'Error when pulling!' . PHP_EOL
          . 'Command: ' . $result['cmd'] . PHP_EOL
          . 'Output:' . PHP_EOL . $output;
      if ($this->logger !== null) {
        $this->logger->error($msg);
      }
      throw new DeploymentException($msg);
    }

    // Execute Hooks
    foreach ($repository->hooksPostUpdate as $hook) {
      $args = $hook->compileCommand($repository);
      $result = self::executeCommand($args);
      $code = $result['code'];
      if ($code !== 0) {
        $msg = 'The "post-update" hook failed! Code: ' . $code . ', Command: ' . $result['cmd'];
        $output = self::formatCommandResult($result);
        if (strlen($output) > 0) {
          $msg .= PHP_EOL . 'Hook Output:' . PHP_EOL . $output;
        }
        if ($this->logger !== null) {
          $this->logger->error($msg);
        }
        throw new DeploymentException($msg);
      }
    }

    $args = ['-R og-rx', $gitDir];
    $result = self::executeCommand('chmod', $args);
    if ($result['code'] !== 0) {
      $output = self::formatCommandResult($result);
      $msg = 'Error when securing repo!' . PHP_EOL . $output;
      if ($this->logger !== null) {
        $this->logger->error($msg);
      }
      throw new DeploymentException($msg);
    }

    if ($this->logger !== null) {
      $this->logger->info('Successfully updated repo! ' . $repository);
    }
  }

  public function cleanUp()
  {
    $files = glob($this->getTempDir());
    foreach ($files as $file) {
      if (is_file($file)) {
        unlink($file);
      }
    }
  }

  public static function formatCommandResult(array $result, $indent = true)
  {
    if (0 !== $result['code']) {
      $extraOutput = 'The command returned ' . $result['code'] . '.';
    } else {
      $extraOutput = 'The command was executed successfully!';
    }

    if (strlen($result['stdout']) > 0) {
      $extraOutput .= PHP_EOL . 'Output:' . PHP_EOL;
      $extraOutput .= self::indent($result['stdout']);
    }

    if (strlen($result['stderr']) > 0) {
      $extraOutput .= PHP_EOL . 'Errors:' . PHP_EOL;
      $extraOutput .= self::indent($result['stderr']);
    }

    if ($indent) {
      $extraOutput = self::indent($extraOutput);
    }
    return $extraOutput;
  }

  public function executeCommand($file, $args = [])
  {
    $descriptors = [
      ['pipe', 'r'], // stdin
      ['pipe', 'w'], // stdout
      ['pipe', 'w'], // stderr
    ];
    $pipes = [];
    $cwd = null;

    $env = [
      'GIT_SSH' => dirname(dirname(__DIR__)) . '/bin/ssh-git-wrapper',
      'TEMP_FILE_PRIVATE_KEY' => $this->privateKeyLocation,
      'TEMP_FILE_KNOWN_HOSTS' => $this->knownHostsLocation,
    ];
    if (null !== $this->homeLocation) {
      $env['HOME'] = $this->homeLocation;
    }

    $textCommand = $file . ' ' . implode(' ', $args) . ' 2>&1';
    $process = proc_open($textCommand, $descriptors, $pipes, $cwd, $env);

    // Close standard input
    fclose($pipes[0]);

    // Read standard output
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    // Read standard error
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $returnCode = (int) proc_close($process);
    return [
      'cmd' => $textCommand,
      'code' => $returnCode,
      'stdout' => $stdout,
      'stderr' => $stderr,
    ];
  }

  public static function indent($text, $indentation = 4)
  {
    if (is_int($indentation)) {
      $indentation = str_repeat(' ', $indentation);
    }

    $output = preg_replace('#\n#', '$0' . $indentation, $text);
    if (strlen($output) > 0) {
      $output = $indentation . $output;
    }
    return $output;
  }

  public function compareWebAccessKeys($key)
  {
    return 0 === strcmp($key, $this->webAccessKey);
  }
}
