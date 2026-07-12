# Contributing

Contributions are welcome! Please follow these guidelines to help maintain a high-quality codebase.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/jxl1216/zabbix_modules.git`
3. Create a feature branch: `git checkout -b feature/my-feature`
4. Make your changes
5. Commit your changes: `git commit -m 'Add some feature'`
6. Push to the branch: `git push origin feature/my-feature`
7. Create a Pull Request

## Development Environment

### Prerequisites

- Zabbix 6.0+ (6.0 LTS recommended for broader compatibility testing)
- PHP 7.4+ (Zabbix 6.0) / PHP 8.0+ (Zabbix 6.4+)
- Web server (Apache/Nginx)
- Git

### Setup

1. Copy the `HostBatchClone` directory to your Zabbix frontend modules directory:
   ```bash
   cp -r HostBatchClone /usr/share/zabbix/ui/modules/
   ```

2. Set file ownership:
   ```bash
   chown -R nginx:nginx /usr/share/zabbix/ui/modules/HostBatchClone/
   ```

3. Enable the module via Zabbix Web interface:
   - Administration → General → Modules
   - Click "Scan directory"
   - Enable "Host Batch Import"

## Coding Standards

### PHP

- Follow [Zabbix Coding Standards](https://www.zabbix.com/documentation/current/manual/extend/modules/coding_standards)
- Use strict typing: `declare(strict_types = 1);`
- Use PSR-4 autoloading (namespace `Modules\HostBatchClone`)
- Use Zabbix API for all database operations
- Handle Zabbix version differences via `CompatHelper` class

### JavaScript

- Use jQuery (as Zabbix uses jQuery)
- Wrap code in IIFE: `(function($) { 'use strict'; ... })(jQuery);`
- Follow Zabbix frontend conventions
- Use proper error handling for AJAX requests

### CSS

- Follow Zabbix design guidelines
- Use Zabbix color palette
- Maintain responsive design

## Testing

### Manual Testing

1. **Installation**: Verify module installs correctly on different Zabbix versions
2. **Basic Functionality**:
   - Select source host and load configuration
   - Upload CSV file and parse
   - Enter data via online table
   - Preview and verify conflict detection
   - Execute import and verify results
3. **Edge Cases**:
   - Empty CSV files
   - CSV files with invalid data
   - Duplicate host names
   - Non-existent host groups (should auto-create)
   - Non-existent templates (should be skipped)
4. **Version Compatibility**: Test on Zabbix 6.0, 6.4, 7.0, 7.4, 8.0

### Code Quality

- Run PHP linting: `php -l *.php actions/*.php views/*.php`
- Check for syntax errors in JavaScript
- Validate JSON files: `python -m json.tool manifest.json`

## Pull Request Guidelines

1. **Title**: Use descriptive title (e.g., "Fix: Host group creation fails in Zabbix 6.0")
2. **Description**: Explain what changes were made and why
3. **Testing**: Describe how the changes were tested
4. **Screenshots**: Include screenshots for UI changes
5. **Version Compatibility**: Note which Zabbix versions were tested

## Reporting Issues

1. **Bug Reports**:
   - Zabbix version
   - PHP version
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Error messages (if any)

2. **Feature Requests**:
   - Description of the feature
   - Use case
   - Proposed implementation (optional)

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0 License.