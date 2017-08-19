<?php
namespace AutomatedGitDeployer;

class Repository
{

  public $name;
  public $id;
  public $branchName;
  public $location;
  public $origin;
  public $remoteRepository;
  public $hooksPostUpdate = [];

  public function __construct($name = null, $id = null, $branchName = null, $location = null, $origin = null, $remoteRepository = 'origin')
  {
    $this->name = $name;
    $this->id = $id;
    $this->branchName = $branchName;
    $this->location = $location . DIRECTORY_SEPARATOR;
    $this->origin = $origin;
    $this->remoteRepository = $remoteRepository;
  }

  public function __toString()
  {
    $data = '[' . $this->name . ' / ' . $this->remoteRepository . ' ' . $this->branchName . '] <--> [' . $this->location . ']';
    return $data;
  }
}
