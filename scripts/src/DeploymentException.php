<?php
namespace AutomatedGitDeployer;

use Exception;

class DeploymentException extends Exception
{

    public function __toString()
    {
        return $this->getMessage();
    }

}
