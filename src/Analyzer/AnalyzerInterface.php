<?php

declare(strict_types=1);

namespace Retina\Analyzer;

interface AnalyzerInterface
{
    public function analyze(): array;

    public function getName(): string;
}
