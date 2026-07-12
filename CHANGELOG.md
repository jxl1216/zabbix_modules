# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2] - 2026-07-11

### Added

- Added automatic host group creation: If host groups specified in CSV don't exist, they are now automatically created via Zabbix API
- Added existence detection with color coding in preview: Status badges for groups/templates (exists/will be created/not found/inherited)
- Added result report export: Download CSV-formatted import results after completion
- Added duplicate host name detection within batch
- Added template name conflict detection (host names must be unique across hosts and templates)
- Added responsive design for better mobile device support
- Added UTF-8/GBK encoding selection for CSV parsing

### Changed

- Improved CompatHelper class to better handle Zabbix 6.0/6.4/7.x API differences
- Enhanced conflict detection to show detailed error messages per host
- Improved CSV parsing to support quoted fields and auto-detect headers
- Updated documentation with detailed inheritance rules and configuration examples

### Fixed

- Fixed host group API parameter differences between Zabbix versions (selectGroups vs selectHostGroups)
- Fixed host group result key differences (groups vs hostgroups)
- Fixed preview page back button visibility: Changed button background from light gray to blue for better contrast
- Fixed CSRF validation compatibility across Zabbix versions
- Fixed duplicate import protection - added pre-creation existence check

## [1.1] - 2026-06-15

### Added

- Added online table entry mode for direct data input
- Added CSV template download functionality
- Added source host info preview before import
- Added field inheritance mechanism (port, groups, templates, tags, macros, description)

### Changed

- Refactored code structure following Zabbix module best practices
- Improved error handling and user feedback

## [1.0] - 2026-05-01

### Added

- Initial release with basic CSV import functionality
- Source host cloning with configuration inheritance
- Preview page with conflict detection
- AJAX-based import with progress feedback
- Zabbix 6.0/6.4 compatibility