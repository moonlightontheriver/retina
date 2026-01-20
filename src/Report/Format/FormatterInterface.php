<?php

declare(strict_types=1);

namespace Retina\Report\Format;

use Retina\Scanner\ScanResult;

interface FormatterInterface
{
    public function format(ScanResult $result): string;

    public function getExtension(): string;

    public function getMimeType(): string;
}
