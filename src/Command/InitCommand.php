<?php

declare(strict_types=1);

namespace Retina\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class InitCommand extends Command
{
    protected static $defaultName = 'init';
    protected static $defaultDescription = 'Initialize Retina configuration in a plugin directory';

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription(self::$defaultDescription)
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'Path to the plugin directory (defaults to current directory)',
                '.'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command initializes Retina configuration files in a plugin directory.

<info>Usage:</info>
  <comment>retina init</comment>                  Initialize in current directory
  <comment>retina init /path/to/plugin</comment> Initialize in specific directory

This will create a <comment>retina.yml</comment> configuration file with customizable settings.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');

        $directory = realpath($directory);
        if ($directory === false || !is_dir($directory)) {
            $io->error('Invalid directory path specified.');
            return Command::FAILURE;
        }

        $configPath = $directory . '/retina.yml';
        if (file_exists($configPath)) {
            $io->warning('retina.yml already exists in this directory.');
            if (!$io->confirm('Do you want to overwrite it?', false)) {
                return Command::SUCCESS;
            }
        }

        $filesystem = new Filesystem();
        $config = $this->getDefaultConfig();

        try {
            $filesystem->dumpFile($configPath, $config);
            $io->success("Created retina.yml in $directory");

            $io->section('Configuration Options');
            $io->listing([
                'level: Analysis strictness level (1-9)',
                'paths: Directories to scan',
                'excludePaths: Directories to ignore',
                'rules: Enable/disable specific rules',
                'reportFormat: Default output format',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to create config: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getDefaultConfig(): string
    {
        return <<<'YAML'
# Retina Configuration
# PocketMine-MP Plugin Static Analyzer

# Analysis strictness level (1-9, higher = stricter)
level: 6

# Paths to scan (relative to plugin root)
paths:
  - src

# Paths to exclude from scanning
excludePaths:
  - vendor
  - tests

# Default report format (md, json, txt, html)
reportFormat: md

# Generate simplified reports (default: false)
# Simple reports show only file, line, category, severity, and message
# without code snippets or suggestions
simpleReport: false

# Exclude specific issue categories from reports
# Presets: unused, undefined, pocketmine
# Or specify individual categories like: unused_variable, unused_import
excludeCategories: []
#  - unused_variable
#  - unused_import

# Disable specific analyzers
# Available: PluginYml, MainClass, PhpFile, EventHandler, Listener,
#            Command, Permission, AsyncTask, Scheduler, Config,
#            Resource, DeprecatedApi, ThreadSafety, PHPStan
excludeAnalyzers: []
#  - DeprecatedApi
#  - ThreadSafety

# Exclude severity levels from reports
# Valid: error, warning, info, hint
excludeSeverities: []
#  - info
#  - hint

# Custom rules configuration
rules:
  # PHP Standard Rules
  undefinedVariable: true
  undefinedMethod: true
  undefinedClass: true
  undefinedConstant: true
  undefinedFunction: true
  typeMismatch: true
  unusedVariable: true
  unusedParameter: true
  unusedImport: true
  deadCode: true
  
  # PocketMine-MP Specific Rules
  invalidEventHandler: true
  unregisteredListener: true
  invalidPluginYml: true
  mainClassMismatch: true
  invalidApiVersion: true
  deprecatedApiUsage: true
  asyncTaskMisuse: true
  schedulerMisuse: true
  configMisuse: true
  permissionMismatch: true
  commandMismatch: true
  resourceMissing: true
  invalidEventPriority: true
  cancelledEventAccess: true
  threadSafetyViolation: true

# Ignore specific error codes
ignoreErrors: []

# PHPStan baseline file (for gradual adoption)
baseline: null
YAML;
    }
}
