<?php
namespace AutomatedGitDeployer;

class Hook
{
    public $command;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function compileCommand(Repository $repository)
    {
        $compiledCommand = $this->command;
        $compiledCommand = str_replace('[[location]]', $repository->location, $compiledCommand);
        $compiledCommand = str_replace('[[name]]', $repository->name, $compiledCommand);
        $compiledCommand = str_replace('[[branch]]', $repository->branchName, $compiledCommand);
        return $compiledCommand;
    }
}
