<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ListenerAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        $listeners = $this->context->getListeners();
        $registeredListeners = $this->findRegisteredListeners();

        foreach ($listeners as $fqcn => $listener) {
            $this->validateListenerInterface($listener);
            $this->checkListenerRegistration($fqcn, $listener, $registeredListeners);
        }

        return $this->issues;
    }

    private function validateListenerInterface(array $listener): void
    {
        $file = $listener['file'];
        $line = $listener['line'];

        if (!in_array('pocketmine\\event\\Listener', $listener['implements'])) {
            $hasListenerInterface = false;
            foreach ($listener['implements'] as $interface) {
                if (str_ends_with($interface, '\\Listener')) {
                    $hasListenerInterface = true;
                    break;
                }
            }

            if (!$hasListenerInterface) {
                $hasEventHandler = false;
                foreach ($listener['methods'] as $method) {
                    $params = $method['params'] ?? [];
                    if (!empty($params)) {
                        $paramType = $params[0]['type'] ?? '';
                        if (str_contains($paramType, 'Event')) {
                            $hasEventHandler = true;
                            break;
                        }
                    }
                }

                if ($hasEventHandler) {
                    $this->addIssue(
                        "Class {$listener['fqcn']} has event handlers but does not implement Listener interface",
                        $file,
                        $line,
                        IssueCategory::UNREGISTERED_LISTENER,
                        IssueSeverity::ERROR,
                        'listener_no_interface',
                        'Add "implements \\pocketmine\\event\\Listener" to the class declaration'
                    );
                }
            }
        }
    }

    private function findRegisteredListeners(): array
    {
        $registered = [];

        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $traverser = new NodeTraverser();
            $visitor = new class extends NodeVisitorAbstract {
                public array $found = [];

                public function enterNode(Node $node)
                {
                    if ($node instanceof Node\Expr\MethodCall) {
                        if ($node->name instanceof Node\Identifier &&
                            $node->name->toString() === 'registerEvents') {

                            $args = $node->args;
                            if (count($args) >= 1) {
                                $listenerArg = $args[0]->value;

                                if ($listenerArg instanceof Node\Expr\Variable) {
                                    $this->found[] = [
                                        'type' => 'variable',
                                        'name' => $listenerArg->name,
                                        'line' => $node->getLine(),
                                    ];
                                } elseif ($listenerArg instanceof Node\Expr\New_) {
                                    if ($listenerArg->class instanceof Node\Name) {
                                        $this->found[] = [
                                            'type' => 'class',
                                            'name' => $listenerArg->class->toString(),
                                            'line' => $node->getLine(),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    return null;
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            foreach ($visitor->found as $item) {
                $registered[] = $item;
            }
        }

        return $registered;
    }

    private function checkListenerRegistration(string $fqcn, array $listener, array $registeredListeners): void
    {
        $shortName = $this->getShortClassName($fqcn);
        $isRegistered = false;

        foreach ($registeredListeners as $reg) {
            if ($reg['type'] === 'class') {
                $regShort = $this->getShortClassName($reg['name']);
                if ($regShort === $shortName || $reg['name'] === $fqcn) {
                    $isRegistered = true;
                    break;
                }
            }
        }

        if (!$isRegistered && $this->hasEventHandlers($listener)) {
            $this->addIssue(
                "Listener class {$listener['fqcn']} does not appear to be registered with registerEvents() in the scanned code",
                $listener['file'],
                $listener['line'],
                IssueCategory::UNREGISTERED_LISTENER,
                IssueSeverity::INFO,
                'listener_not_registered',
                'Ensure this listener is registered via $this->getServer()->getPluginManager()->registerEvents() in onEnable() or elsewhere. This check may produce false positives if registration happens dynamically or in external classes.'
            );
        }
    }

    private function hasEventHandlers(array $listener): bool
    {
        foreach ($listener['methods'] as $method) {
            $params = $method['params'] ?? [];
            if (!empty($params)) {
                $paramType = $params[0]['type'] ?? '';
                if (str_contains($paramType, 'Event')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
