<?php

namespace Atlas\Console;

use Atlas\Console\Commands\DiffCommand;
use Atlas\Console\Commands\StatusCommand;
use Symfony\Component\Console\Application as BaseApplication;
class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Atlas', '0.1.0');

        $this->addCommand(new DiffCommand());
        $this->addCommand(new StatusCommand());
    }
}