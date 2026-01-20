<?php

declare(strict_types=1);

namespace Retina\Report\Format;

use Retina\Scanner\ScanResult;

class JsonFormatter implements FormatterInterface
{
    private bool $prettyPrint;

    public function __construct(bool $prettyPrint = true)
    {
        $this->prettyPrint = $prettyPrint;
    }

    public function format(ScanResult $result): string
    {
        $data = $result->toArray();

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    public function getExtension(): string
    {
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }
}
