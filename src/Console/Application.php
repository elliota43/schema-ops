<?php

namespace SchemaOps\Console;

use SchemaOps\Console\Commands\DiffCommand;
use SchemaOps\Console\Commands\StatusCommand;
use Symfony\Component\Console\Application as BaseApplication;
class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('SchemaOps', '0.1.0');

        $this->addCommand(new DiffCommand());
        $this->addCommand(new StatusCommand());
    }
}