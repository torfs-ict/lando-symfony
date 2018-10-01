<?php

namespace Lando;

use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{
    /**
     * @var CommandSettings
     */
    protected $settings;

    final public function __construct(CommandSettings $settings, string $name = null)
    {
        parent::__construct($name);
        $this->settings = $settings;
    }
}