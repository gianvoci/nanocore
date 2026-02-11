## NanoCore

NanoCore is a lightweight PHP library designed to handle routing, global variables, and utility functions in a simple and efficient manner. Ideal for lightweight projects, NanoCore provides a fast and intuitive solution without burdening your applications.

## Key Features

* Lightweight Routing: Define and manage routes with ease.
* Global Variables Management: Access and manipulate global variables securely.
* Utility Functions: Useful integrated functions to simplify daily development tasks.
* High Efficiency: Designed to be fast and consume minimal resources.
* Easy Integration: Easily integrates with any existing PHP project.

## Development Guidelines

* Always use the long `<?php` opening tag so the library loads on default PHP installs.
* Ensure HTTP responses use valid status codes (e.g., fallback to 500) when emitting JSON error payloads.
* Sanitize commands in `ExecDetach()` via `escapeshellcmd`/`escapeshellarg` before invoking `shell_exec`.
* Run `php -l NanoCore.php` after making changes to guard against syntax errors.

## Core File Reference

* `NanoCore.php` contains the main NanoCore class and provides routing, configuration management, utilities, and helpers used by the framework.
