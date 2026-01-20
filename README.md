# Retina

Static analyzer for PocketMine-MP plugins. Scans your plugin code and finds errors, deprecated API usage, thread safety issues, and other problems before you run it.

## Installation

### macOS and Linux

```bash
curl -sSL https://raw.githubusercontent.com/moonlightontheriver/retina/main/install.sh | bash
```

### Windows (PowerShell)

```powershell
iwr -useb https://raw.githubusercontent.com/moonlightontheriver/retina/main/install.ps1 | iex
```

### Windows (CMD)

Download and run `install.bat` from the releases page.

### Requirements

- PHP 8.1 or higher
- Git
- Composer

## Usage

Scan current directory:
```bash
retina run
```

Scan specific plugin:
```bash
retina run /path/to/plugin
```

Create config file:
```bash
retina init
```

## Command Line Options

### Basic Options

- `--format`, `-f` - Output format: `md`, `json`, `txt`, `html` (default: `md`)
- `--output`, `-o` - Output file path (default: `retina-report.<format>`)
- `--level`, `-l` - Analysis strictness level 1-9 (default: `6`)
- `--console-only`, `-c` - Print to console only, don't create file
- `--simple`, `-s` - Generate simplified report without code snippets
- `--no-progress` - Disable progress output

### Filtering Options

Exclude specific issue categories:
```bash
retina run --exclude-categories=unused_variable,unused_import
```

Disable specific analyzers:
```bash
retina run --exclude-analyzers=DeprecatedApi,ThreadSafety
```

Exclude severity levels:
```bash
retina run --exclude-severities=info,hint
```

## Configuration File

Create a `retina.yml` in your plugin directory to customize behavior:

```yaml
level: 6
reportFormat: md
simpleReport: false

paths:
  - src

excludePaths:
  - vendor
  - tests

excludeCategories:
  - unused_variable
  - unused_import

excludeAnalyzers:
  - DeprecatedApi

excludeSeverities:
  - info
  - hint

rules:
  undefinedVariable: true
  deprecatedApiUsage: true
  threadSafetyViolation: true
```

## Available Analyzers

- `PluginYml` - Validates plugin.yml structure and required fields
- `MainClass` - Checks main class exists and extends PluginBase
- `PhpFile` - General PHP syntax and structure validation
- `EventHandler` - Validates event handler methods
- `Listener` - Checks listener registration
- `Command` - Validates command implementations
- `Permission` - Checks permission definitions
- `AsyncTask` - Detects AsyncTask misuse
- `Scheduler` - Validates scheduler usage
- `Config` - Checks config file handling
- `Resource` - Validates resource file access
- `DeprecatedApi` - Finds deprecated PocketMine-MP API usage
- `ThreadSafety` - Detects thread safety violations
- `PHPStan` - Runs PHPStan analysis

## Issue Categories

### PHP Standard Issues
- `undefined_variable` - Variable used before definition
- `undefined_method` - Method doesn't exist
- `undefined_class` - Class not found
- `undefined_constant` - Constant not defined
- `undefined_function` - Function doesn't exist
- `undefined_property` - Property doesn't exist
- `type_mismatch` - Type doesn't match expected
- `return_type` - Return type error
- `parameter_type` - Parameter type error
- `unused_variable` - Variable declared but never used
- `unused_parameter` - Parameter never used
- `unused_import` - Import statement not needed
- `dead_code` - Unreachable code
- `syntax_error` - PHP syntax error
- `missing_return` - Missing return statement
- `invalid_inheritance` - Invalid class inheritance
- `interface_violation` - Interface not properly implemented
- `abstract_violation` - Abstract class violation
- `visibility_violation` - Visibility modifier error
- `static_call_error` - Static method call error
- `instantiation_error` - Cannot instantiate class
- `array_access_error` - Invalid array access
- `null_safety` - Potential null pointer issue

### PocketMine-MP Specific Issues
- `invalid_event_handler` - Event handler signature wrong
- `unregistered_listener` - Listener not registered
- `invalid_plugin_yml` - plugin.yml has errors
- `main_class_mismatch` - Main class doesn't match plugin.yml
- `invalid_api_version` - API version format invalid
- `deprecated_api` - Using deprecated PocketMine-MP API
- `async_task_misuse` - AsyncTask used incorrectly
- `scheduler_misuse` - Scheduler used incorrectly
- `config_misuse` - Config handling error
- `permission_mismatch` - Permission definition mismatch
- `command_mismatch` - Command definition mismatch
- `resource_missing` - Resource file not found
- `invalid_event_priority` - Event priority invalid
- `cancelled_event_access` - Accessing cancelled event incorrectly
- `thread_safety` - Thread safety violation

## Category Presets

Use presets to exclude multiple related categories:

- `unused` - Excludes: unused_variable, unused_parameter, unused_import
- `undefined` - Excludes: undefined_variable, undefined_method, undefined_class, undefined_constant, undefined_function, undefined_property
- `pocketmine` - Excludes all PocketMine-MP specific categories

Example:
```bash
retina run --exclude-categories=unused,undefined
```

## Severity Levels

Issues are categorized by severity:

- `error` - Critical issues that will cause crashes
- `warning` - Problems that should be fixed
- `info` - Suggestions for improvement
- `hint` - Minor style or optimization suggestions

## Report Formats

### Markdown (default)
Human-readable format with code snippets and suggestions.

### JSON
Machine-readable format for CI/CD integration.

### Text
Plain text format for console output.

### HTML
Web-based interactive report with filtering and search.

### Simple Reports

Add `--simple` or set `simpleReport: true` in config to generate minimal reports showing only:
- File path
- Line number
- Category
- Severity
- Message

No code snippets or suggestions included.

## Examples

Strict analysis with HTML report:
```bash
retina run -l 9 -f html -o report.html
```

Quick scan without unused variable warnings:
```bash
retina run --exclude-categories=unused_variable -c
```

Scan with multiple exclusions:
```bash
retina run --exclude-categories=unused,undefined --exclude-severities=hint
```

Generate simple JSON report:
```bash
retina run -f json -s -o results.json
```

## Requirements

- PHP 8.1 or higher
- Composer (for global installation)

## License

MIT
