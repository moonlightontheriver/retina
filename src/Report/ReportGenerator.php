<?php

declare(strict_types=1);

namespace Retina\Report;

use Retina\Report\Format\FormatterInterface;
use Retina\Scanner\ScanResult;
use Symfony\Component\Filesystem\Filesystem;

class ReportGenerator
{
    private FormatterInterface $formatter;
    private Filesystem $filesystem;

    public function __construct(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
        $this->filesystem = new Filesystem();
    }

    public function generate(ScanResult $result, string $outputPath): void
    {
        $content = $this->formatter->format($result);

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }

        $this->filesystem->dumpFile($outputPath, $content);
    }

    public function generateToString(ScanResult $result): string
    {
        return $this->formatter->format($result);
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }
}
