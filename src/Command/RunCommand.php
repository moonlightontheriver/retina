<?php

declare(strict_types=1);

namespace Retina\Command;

use Retina\Scanner\PluginScanner;
use Retina\Report\ReportGenerator;
use Retina\Report\Format\MarkdownFormatter;
use Retina\Report\Format\JsonFormatter;
use Retina\Report\Format\TextFormatter;
use Retina\Report\Format\HtmlFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCommand extends Command
{
    protected static $defaultName = 'run';
    protected static $defaultDescription = 'Scan a PocketMine-MP plugin for errors and issues';

    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription(self::$defaultDescription)
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'Path to the plugin directory (defaults to current directory)',
                '.'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: md, json, txt, html (default: md)',
                'md'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path (defaults to retina-report.<format>)',
                null
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Analysis strictness level (1-9, default: 6)',
                '6'
            )
            ->addOption(
                'no-progress',
                null,
                InputOption::VALUE_NONE,
                'Disable progress output'
            )
            ->addOption(
                'console-only',
                'c',
                InputOption::VALUE_NONE,
                'Output to console only, do not generate file'
            )
            ->addOption(
                'simple',
                's',
                InputOption::VALUE_NONE,
                'Generate simplified report without code snippets and suggestions'
            )
            ->addOption(
                'exclude-categories',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of issue categories to exclude (e.g., unused,undefined)'
            )
            ->addOption(
                'exclude-analyzers',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of analyzers to disable (e.g., DeprecatedApi,ThreadSafety)'
            )
            ->addOption(
                'exclude-severities',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of severity levels to exclude (e.g., info,hint)'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command scans a PocketMine-MP plugin directory for errors and issues.

<info>Basic usage:</info>
  <comment>retina run</comment>                    Scan current directory
  <comment>retina run /path/to/plugin</comment>   Scan specific directory

<info>Output formats:</info>
  <comment>retina run -f md</comment>             Markdown report (default)
  <comment>retina run -f json</comment>           JSON report
  <comment>retina run -f txt</comment>            Plain text report
  <comment>retina run -f html</comment>           HTML web-based report

<info>Options:</info>
  <comment>retina run -o report.md</comment>      Custom output file
  <comment>retina run -l 8</comment>              Higher strictness level
  <comment>retina run -c</comment>                Console output only

HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        $format = strtolower($input->getOption('format'));
        $outputPath = $input->getOption('output');
        $level = (int) $input->getOption('level');
        $showProgress = !$input->getOption('no-progress');
        $consoleOnly = $input->getOption('console-only');

        $directory = realpath($directory);
        if ($directory === false || !is_dir($directory)) {
            $io->error('Invalid directory path specified.');
            return Command::FAILURE;
        }

        $filterConfig = $this->createFilterConfig($input, $directory);
        
        try {
            $filterConfig->expandPresets();
            $filterConfig->validate();
        } catch (\Retina\Filter\Exception\InvalidCategoryException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Retina\Filter\Exception\InvalidAnalyzerException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Retina\Filter\Exception\InvalidSeverityException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Retina\Filter\Exception\InvalidFilterConfigException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Retina - PocketMine-MP Plugin Scanner');
        $io->text("Scanning: <info>$directory</info>");

        $pluginYmlPath = $directory . '/plugin.yml';
        if (!file_exists($pluginYmlPath)) {
            $io->error("No plugin.yml found in $directory. Is this a PocketMine-MP plugin?");
            return Command::FAILURE;
        }

        $scanner = new PluginScanner($directory, $level, $io, $showProgress, $filterConfig);

        try {
            $io->section('Analyzing plugin...');
            $result = $scanner->scan();

            $io->newLine();

            if ($result->hasErrors()) {
                $io->warning(sprintf(
                    'Found %d issue(s) in %d file(s)',
                    $result->getTotalIssueCount(),
                    $result->getAffectedFileCount()
                ));
            } else {
                $io->success('No issues found!');
            }

            $io->section('Summary');
            $this->displaySummary($io, $result);

            if ($result->hasErrors() && !$consoleOnly) {
                $formatter = $this->getFormatter($format, $filterConfig->isSimpleReport());
                $generator = new ReportGenerator($formatter);

                if ($outputPath === null) {
                    $outputPath = $directory . '/retina-report.' . $formatter->getExtension();
                }

                $generator->generate($result, $outputPath);
                $io->success("Report saved to: $outputPath");
            }

            return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error('Scan failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function displaySummary(SymfonyStyle $io, \Retina\Scanner\ScanResult $result): void
    {
        $summary = $result->getSummary();

        $rows = [];
        foreach ($summary as $category => $count) {
            if ($count > 0) {
                $rows[] = [$category, $count];
            }
        }

        if (!empty($rows)) {
            $io->table(['Category', 'Count'], $rows);
        }

        $io->text(sprintf('Total files scanned: <info>%d</info>', $result->getScannedFileCount()));
        $io->text(sprintf('Total issues found: <comment>%d</comment>', $result->getTotalIssueCount()));
    }

    private function getFormatter(string $format, bool $simple = false): \Retina\Report\Format\FormatterInterface
    {
        if ($simple) {
            return match ($format) {
                'json' => new \Retina\Report\Format\Simple\SimpleJsonFormatter(),
                'txt', 'text' => new \Retina\Report\Format\Simple\SimpleTextFormatter(),
                'html', 'web' => new \Retina\Report\Format\Simple\SimpleHtmlFormatter(),
                default => new \Retina\Report\Format\Simple\SimpleMarkdownFormatter(),
            };
        }
        
        return match ($format) {
            'json' => new JsonFormatter(),
            'txt', 'text' => new TextFormatter(),
            'html', 'web' => new HtmlFormatter(),
            default => new MarkdownFormatter(),
        };
    }

    private function createFilterConfig(InputInterface $input, string $directory): \Retina\Filter\FilterConfig
    {
        $configPath = $directory . '/retina.yml';
        $fileConfig = [];
        
        if (file_exists($configPath)) {
            $retinaConfig = \Retina\Config\RetinaConfig::fromFile($configPath);
            $fileConfig = [
                'simpleReport' => $retinaConfig->isSimpleReport(),
                'excludeCategories' => $retinaConfig->getExcludeCategories(),
                'excludeAnalyzers' => $retinaConfig->getExcludeAnalyzers(),
                'excludeSeverities' => $retinaConfig->getExcludeSeverities(),
            ];
        }
        
        $cliConfig = [
            'simpleReport' => $input->getOption('simple'),
            'excludeCategories' => $this->parseCommaSeparated($input->getOption('exclude-categories')),
            'excludeAnalyzers' => $this->parseCommaSeparated($input->getOption('exclude-analyzers')),
            'excludeSeverities' => $this->parseCommaSeparated($input->getOption('exclude-severities')),
        ];
        
        $merged = [
            'simpleReport' => $cliConfig['simpleReport'] ?: $fileConfig['simpleReport'] ?? false,
            'excludeCategories' => !empty($cliConfig['excludeCategories']) 
                ? $cliConfig['excludeCategories'] 
                : ($fileConfig['excludeCategories'] ?? []),
            'excludeAnalyzers' => !empty($cliConfig['excludeAnalyzers']) 
                ? $cliConfig['excludeAnalyzers'] 
                : ($fileConfig['excludeAnalyzers'] ?? []),
            'excludeSeverities' => !empty($cliConfig['excludeSeverities']) 
                ? $cliConfig['excludeSeverities'] 
                : ($fileConfig['excludeSeverities'] ?? []),
        ];
        
        return \Retina\Filter\FilterConfig::fromArray($merged);
    }

    private function parseCommaSeparated(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        
        return array_map('trim', explode(',', $value));
    }
}
