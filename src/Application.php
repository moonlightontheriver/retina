<?php

declare(strict_types=1);

namespace Retina;

use Retina\Command\RunCommand;
use Retina\Command\InitCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public const VERSION = '1.0.0';
    public const NAME = 'Retina';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->add(new RunCommand());
        $this->add(new InitCommand());

        $this->setDefaultCommand('list');
    }

    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment> - PocketMine-MP Plugin Static Analyzer',
            self::NAME,
            self::VERSION
        );
    }
}
