<?php

declare(strict_types=1);

namespace Retina\Issue;

class Issue
{
    private string $message;
    private string $file;
    private int $line;
    private ?int $column;
    private IssueCategory $category;
    private IssueSeverity $severity;
    private string $code;
    private ?string $suggestion;
    private ?string $snippet;

    public function __construct(
        string $message,
        string $file,
        int $line,
        IssueCategory $category,
        IssueSeverity $severity,
        string $code = '',
        ?int $column = null,
        ?string $suggestion = null,
        ?string $snippet = null
    ) {
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
        $this->column = $column;
        $this->category = $category;
        $this->severity = $severity;
        $this->code = $code;
        $this->suggestion = $suggestion;
        $this->snippet = $snippet;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getRelativeFile(string $basePath): string
    {
        if (str_starts_with($this->file, $basePath)) {
            return ltrim(substr($this->file, strlen($basePath)), '/\\');
        }
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    public function getCategory(): IssueCategory
    {
        return $this->category;
    }

    public function getSeverity(): IssueSeverity
    {
        return $this->severity;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    public function getSnippet(): ?string
    {
        return $this->snippet;
    }

    public function setSnippet(string $snippet): void
    {
        $this->snippet = $snippet;
    }

    public function getLocation(): string
    {
        $location = "{$this->file}:{$this->line}";
        if ($this->column !== null) {
            $location .= ":{$this->column}";
        }
        return $location;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'category' => $this->category->value,
            'categoryLabel' => $this->category->getLabel(),
            'severity' => $this->severity->value,
            'severityLabel' => $this->severity->getLabel(),
            'code' => $this->code,
            'suggestion' => $this->suggestion,
            'snippet' => $this->snippet,
        ];
    }

    public static function create(
        string $message,
        string $file,
        int $line,
        IssueCategory $category,
        IssueSeverity $severity = IssueSeverity::ERROR
    ): self {
        return new self($message, $file, $line, $category, $severity);
    }
}
