# automated-git-deployer
Written in PHP, built on Unix, this project is designed to automatically handle deployment of all of your projects.

This project uses an organized structure. It allows you to configure your projects, and external/internal scripts can automatically pull the latest code.

### Requirements

* Unix-based OS with `bash`
* PHP must have access to the following commands
  - `git`
  - `export`
  - `ssh`
  - `chmod`
  - Including any other commands you have specified in your configuration file.
* PHP 5.4+

### Usage

Clone this repository. If needed, point your webserver to the `public` directory.

Create a new file in the `config` folder called `config.json`. Use the example file as a guideline.

Follow this link for more help on the configuration. [github.com/wafflestealer654/automated-git-deployer/wiki/Configuration](https://github.com/wafflestealer654/automated-git-deployer/wiki/Configuration)

### Notes

This script can run without root access. (TODO)

Tested on Debian Jessie (unstable).
