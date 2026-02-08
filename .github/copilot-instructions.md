# GitHub Copilot Instructions

Read CLAUDE.md in the project root for complete project documentation, architecture, and coding conventions.

## Quick Summary
- PHP 8.5 MVC application (no framework)
- MySQL 8.0 database, Bootstrap 5 dark theme
- Docker: app + mysql + adminer
- PSR-4 autoloading under App\ namespace
- Type declarations on all method parameters and returns
- ARGON2ID for password hashing
- All user output escaped with htmlspecialchars()
