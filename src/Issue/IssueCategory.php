<?php

declare(strict_types=1);

namespace Retina\Issue;

enum IssueCategory: string
{
    case UNDEFINED_VARIABLE = 'undefined_variable';
    case UNDEFINED_METHOD = 'undefined_method';
    case UNDEFINED_CLASS = 'undefined_class';
    case UNDEFINED_CONSTANT = 'undefined_constant';
    case UNDEFINED_FUNCTION = 'undefined_function';
    case UNDEFINED_PROPERTY = 'undefined_property';
    case TYPE_MISMATCH = 'type_mismatch';
    case RETURN_TYPE = 'return_type';
    case PARAMETER_TYPE = 'parameter_type';
    case UNUSED_VARIABLE = 'unused_variable';
    case UNUSED_PARAMETER = 'unused_parameter';
    case UNUSED_IMPORT = 'unused_import';
    case DEAD_CODE = 'dead_code';
    case SYNTAX_ERROR = 'syntax_error';
    case INVALID_EVENT_HANDLER = 'invalid_event_handler';
    case UNREGISTERED_LISTENER = 'unregistered_listener';
    case INVALID_PLUGIN_YML = 'invalid_plugin_yml';
    case MAIN_CLASS_MISMATCH = 'main_class_mismatch';
    case INVALID_API_VERSION = 'invalid_api_version';
    case DEPRECATED_API = 'deprecated_api';
    case ASYNC_TASK_MISUSE = 'async_task_misuse';
    case SCHEDULER_MISUSE = 'scheduler_misuse';
    case CONFIG_MISUSE = 'config_misuse';
    case PERMISSION_MISMATCH = 'permission_mismatch';
    case COMMAND_MISMATCH = 'command_mismatch';
    case RESOURCE_MISSING = 'resource_missing';
    case INVALID_EVENT_PRIORITY = 'invalid_event_priority';
    case CANCELLED_EVENT_ACCESS = 'cancelled_event_access';
    case THREAD_SAFETY = 'thread_safety';
    case MISSING_RETURN = 'missing_return';
    case INVALID_INHERITANCE = 'invalid_inheritance';
    case INTERFACE_VIOLATION = 'interface_violation';
    case ABSTRACT_VIOLATION = 'abstract_violation';
    case VISIBILITY_VIOLATION = 'visibility_violation';
    case STATIC_CALL_ERROR = 'static_call_error';
    case INSTANTIATION_ERROR = 'instantiation_error';
    case ARRAY_ACCESS_ERROR = 'array_access_error';
    case NULL_SAFETY = 'null_safety';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::UNDEFINED_VARIABLE => 'Undefined Variable',
            self::UNDEFINED_METHOD => 'Undefined Method',
            self::UNDEFINED_CLASS => 'Undefined Class',
            self::UNDEFINED_CONSTANT => 'Undefined Constant',
            self::UNDEFINED_FUNCTION => 'Undefined Function',
            self::UNDEFINED_PROPERTY => 'Undefined Property',
            self::TYPE_MISMATCH => 'Type Mismatch',
            self::RETURN_TYPE => 'Return Type Error',
            self::PARAMETER_TYPE => 'Parameter Type Error',
            self::UNUSED_VARIABLE => 'Unused Variable',
            self::UNUSED_PARAMETER => 'Unused Parameter',
            self::UNUSED_IMPORT => 'Unused Import',
            self::DEAD_CODE => 'Dead Code',
            self::SYNTAX_ERROR => 'Syntax Error',
            self::INVALID_EVENT_HANDLER => 'Invalid Event Handler',
            self::UNREGISTERED_LISTENER => 'Unregistered Listener',
            self::INVALID_PLUGIN_YML => 'Invalid plugin.yml',
            self::MAIN_CLASS_MISMATCH => 'Main Class Mismatch',
            self::INVALID_API_VERSION => 'Invalid API Version',
            self::DEPRECATED_API => 'Deprecated API Usage',
            self::ASYNC_TASK_MISUSE => 'AsyncTask Misuse',
            self::SCHEDULER_MISUSE => 'Scheduler Misuse',
            self::CONFIG_MISUSE => 'Config Misuse',
            self::PERMISSION_MISMATCH => 'Permission Mismatch',
            self::COMMAND_MISMATCH => 'Command Mismatch',
            self::RESOURCE_MISSING => 'Missing Resource',
            self::INVALID_EVENT_PRIORITY => 'Invalid Event Priority',
            self::CANCELLED_EVENT_ACCESS => 'Cancelled Event Access',
            self::THREAD_SAFETY => 'Thread Safety Violation',
            self::MISSING_RETURN => 'Missing Return Statement',
            self::INVALID_INHERITANCE => 'Invalid Inheritance',
            self::INTERFACE_VIOLATION => 'Interface Violation',
            self::ABSTRACT_VIOLATION => 'Abstract Class Violation',
            self::VISIBILITY_VIOLATION => 'Visibility Violation',
            self::STATIC_CALL_ERROR => 'Static Call Error',
            self::INSTANTIATION_ERROR => 'Instantiation Error',
            self::ARRAY_ACCESS_ERROR => 'Array Access Error',
            self::NULL_SAFETY => 'Null Safety Issue',
            self::OTHER => 'Other',
        };
    }

    public function isPocketMineSpecific(): bool
    {
        return match ($this) {
            self::INVALID_EVENT_HANDLER,
            self::UNREGISTERED_LISTENER,
            self::INVALID_PLUGIN_YML,
            self::MAIN_CLASS_MISMATCH,
            self::INVALID_API_VERSION,
            self::DEPRECATED_API,
            self::ASYNC_TASK_MISUSE,
            self::SCHEDULER_MISUSE,
            self::CONFIG_MISUSE,
            self::PERMISSION_MISMATCH,
            self::COMMAND_MISMATCH,
            self::RESOURCE_MISSING,
            self::INVALID_EVENT_PRIORITY,
            self::CANCELLED_EVENT_ACCESS,
            self::THREAD_SAFETY => true,
            default => false,
        };
    }
}
