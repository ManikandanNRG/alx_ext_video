# Contributing to Cloudflare Stream Moodle Plugin

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream.git`
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Make your changes
5. Test thoroughly
6. Commit with clear messages
7. Push to your fork
8. Create a Pull Request

## Development Setup

### Requirements
- Moodle 3.9+ development environment
- PHP 7.4+
- MySQL/PostgreSQL database
- Cloudflare Stream account (for testing)
- Git

### Local Development
1. Install Moodle in your local environment
2. Clone this plugin to `mod/assign/submission/cloudflarestream/`
3. Run Moodle upgrade: `php admin/cli/upgrade.php`
4. Configure Cloudflare credentials in plugin settings

## Coding Standards

### PHP
- Follow [Moodle Coding Style](https://moodledev.io/general/development/policies/codingstyle)
- Use PHPDoc comments for all functions and classes
- Run `php admin/cli/check_plugin.php` before committing
- Use type hints where possible (PHP 7.4+)

### JavaScript
- Follow Moodle's AMD module pattern
- Use ES6+ features
- Add JSDoc comments
- Lint with ESLint (Moodle configuration)

### Database
- Follow Moodle's XMLDB conventions
- Table names must be ≤ 28 characters (without mdl_ prefix)
- Always provide upgrade.php for schema changes
- Use proper indexes for performance

### Language Strings
- Add all user-facing text to language files
- Use descriptive string identifiers
- Provide help text for settings
- Follow Moodle's string naming conventions

## Testing

### Before Submitting PR
- [ ] Run PHPUnit tests: `vendor/bin/phpunit --testsuite assignsubmission_cloudflarestream_testsuite`
- [ ] Test plugin installation from scratch
- [ ] Test plugin upgrade from previous version
- [ ] Test in multiple browsers (Chrome, Firefox, Safari, Edge)
- [ ] Test on mobile devices
- [ ] Check for PHP errors in logs
- [ ] Verify database queries are efficient
- [ ] Test with different Moodle versions (3.9, 4.0, 4.1+)

### Writing Tests
- Add unit tests for new PHP classes
- Add integration tests for workflows
- Mock external API calls (Cloudflare)
- Test error conditions and edge cases

## Pull Request Guidelines

### PR Title Format
- `feat: Add video thumbnail generation`
- `fix: Correct database table name in logger`
- `docs: Update installation instructions`
- `test: Add unit tests for validator class`
- `refactor: Improve error handling in API client`

### PR Description Should Include
- What changes were made and why
- How to test the changes
- Screenshots (if UI changes)
- Related issue numbers
- Breaking changes (if any)

### PR Checklist
- [ ] Code follows Moodle coding standards
- [ ] All tests pass
- [ ] Documentation updated (if needed)
- [ ] CHANGELOG.md updated
- [ ] No merge conflicts
- [ ] Commits are clean and well-described

## Reporting Bugs

### Before Reporting
- Check if the issue already exists
- Test with latest version
- Verify it's not a configuration issue

### Bug Report Should Include
- Moodle version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Error messages (from logs)
- Screenshots (if applicable)

## Feature Requests

### Good Feature Requests Include
- Clear description of the feature
- Use case / problem it solves
- Proposed implementation (if you have ideas)
- Willingness to contribute code (optional but appreciated!)

## Code Review Process

1. Maintainers will review PRs within 1 week
2. Feedback will be provided as comments
3. Address feedback and push updates
4. Once approved, PR will be merged
5. Your contribution will be credited in CHANGELOG

## Security Issues

**Do not report security issues publicly!**

Email security concerns to: [your-email@example.com]

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

## License

By contributing, you agree that your contributions will be licensed under the GNU GPL v3 license.

## Questions?

- Open a GitHub Discussion
- Check existing issues and PRs
- Read the documentation in README.md

## Recognition

Contributors will be:
- Listed in CHANGELOG.md
- Credited in release notes
- Mentioned in README.md (for significant contributions)

Thank you for contributing! 🎉
