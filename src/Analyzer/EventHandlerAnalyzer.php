<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;

class EventHandlerAnalyzer extends AbstractAnalyzer
{
    private const VALID_PRIORITIES = [
        'LOWEST', 'LOW', 'NORMAL', 'HIGH', 'HIGHEST', 'MONITOR'
    ];

    public function analyze(): array
    {
        $this->issues = [];

        foreach ($this->context->getListeners() as $fqcn => $listener) {
            $this->analyzeListener($listener);
        }

        return $this->issues;
    }

    private function analyzeListener(array $listener): void
    {
        $file = $listener['file'];

        foreach ($listener['methods'] as $methodName => $method) {
            $this->analyzeEventHandler($file, $methodName, $method, $listener);
        }
    }

    private function analyzeEventHandler(string $file, string $methodName, array $method, array $listener): void
    {
        $params = $method['params'] ?? [];
        $docComment = $method['docComment'] ?? '';

        if (empty($params)) {
            return;
        }

        $firstParam = $params[0];
        $paramType = $firstParam['type'] ?? null;

        if ($paramType === null) {
            return;
        }

        if (!$this->isEventType($paramType)) {
            return;
        }

        if (!$method['isPublic']) {
            $this->addIssue(
                "Event handler '$methodName' must be public to be registered",
                $file,
                $method['line'],
                IssueCategory::INVALID_EVENT_HANDLER,
                IssueSeverity::ERROR,
                'event_handler_not_public',
                'Change the method visibility to public'
            );
        }

        if ($method['isStatic']) {
            $this->addIssue(
                "Event handler '$methodName' should not be static",
                $file,
                $method['line'],
                IssueCategory::INVALID_EVENT_HANDLER,
                IssueSeverity::ERROR,
                'event_handler_static',
                'Remove the static modifier from the event handler'
            );
        }

        if (count($params) !== 1) {
            $this->addIssue(
                "Event handler '$methodName' should have exactly one parameter (the event)",
                $file,
                $method['line'],
                IssueCategory::INVALID_EVENT_HANDLER,
                IssueSeverity::WARNING,
                'event_handler_params',
                'Event handlers should only accept the event object as a parameter'
            );
        }

        $this->validatePriorityAttribute($file, $methodName, $method, $docComment);
        $this->validateHandlesCancelledAttribute($file, $methodName, $method, $docComment);
    }

    private function isEventType(string $type): bool
    {
        return str_starts_with($type, 'pocketmine\\event\\') || 
               (str_ends_with($type, 'Event') && str_contains($type, '\\'));
    }

    private function validatePriorityAttribute(string $file, string $methodName, array $method, string $docComment): void
    {
        if (preg_match('/@priority\s+(\w+)/i', $docComment, $matches)) {
            $priority = strtoupper($matches[1]);
            if (!in_array($priority, self::VALID_PRIORITIES)) {
                $this->addIssue(
                    "Invalid event priority '$priority' in handler '$methodName'. Valid: " . implode(', ', self::VALID_PRIORITIES),
                    $file,
                    $method['line'],
                    IssueCategory::INVALID_EVENT_PRIORITY,
                    IssueSeverity::ERROR,
                    'invalid_event_priority'
                );
            }

            if ($priority === 'MONITOR') {
                $this->addIssue(
                    "Event handler '$methodName' uses MONITOR priority. Do not modify event state at this priority.",
                    $file,
                    $method['line'],
                    IssueCategory::INVALID_EVENT_HANDLER,
                    IssueSeverity::INFO,
                    'monitor_priority_warning',
                    'MONITOR priority is for observation only, do not modify the event or call setCancelled()'
                );
            }
        }
    }

    private function validateHandlesCancelledAttribute(string $file, string $methodName, array $method, string $docComment): void
    {
        if (preg_match('/@handleCancelled/i', $docComment)) {
            $this->addIssue(
                "Event handler '$methodName' handles cancelled events. Ensure this is intentional.",
                $file,
                $method['line'],
                IssueCategory::CANCELLED_EVENT_ACCESS,
                IssueSeverity::INFO,
                'handles_cancelled_event',
                'Consider if handling cancelled events is necessary'
            );
        }
    }
}
