# Host Batch Clone Module

[中文](https://github.com/jxl1216/zabbix_modules/blob/master/README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+ versions.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 6.4.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes an intelligent version detection mechanism (`CompatHelper`) that automatically adapts to different Zabbix API parameter differences (e.g., `selectGroups` vs `selectHostGroups`) without manual configuration.

## Description

This is a Zabbix frontend module for batch cloning and importing a large number of hosts based on an existing monitored host configuration. The module adds a "Host Batch Import" menu item under the "Data collection" menu in the Zabbix Web interface, supporting both CSV file import and online table entry methods, with preview, conflict detection, and real-time import progress feedback.
![alt text](images/image.png)
![alt text](images/image-1.png)
![alt text](images/image-2.png)  

## Features

- **Source Host Cloning**: Select any existing monitored host as a clone template, inheriting its full configuration (interfaces, groups, templates, tags, macros, etc.)

- **Dual Data Entry Modes**:
  - CSV File Upload: Supports UTF-8 and GBK encoding, automatic header detection
  - Online Table Entry: Add/remove rows with real-time validation

- **Smart Field Inheritance**: Only host name and interface IP are required. Other fields (port, groups, templates, tags, macros, description) automatically inherit from the source host when left blank

- **Auto Host Group Creation**: Host groups specified in CSV that don't exist will be automatically created via API before association

- **Preview & Conflict Detection**: Full preview before import, auto-detects host name conflicts, missing required fields

- **Import Progress Feedback**: AJAX-based per-host creation with real-time progress bar and success/failure counts

- **Result Report Export**: Download CSV-formatted result report after import completion

- **Bilingual Support**: Interface supports Chinese/English switching

- **Responsive Design**: Adapts to different screen sizes

- **Modern UI**: Follows Zabbix native design style

## Installation Steps

### Install Module

```bash
# Zabbix 6.0 / 7.0 deployment
git clone https://github.com/jxl1216/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0 deployment
git clone https://github.com/jxl1216/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json

```bash
# ⚠️ If using Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' HostBatchClone/manifest.json
```

For Zabbix 7.0+ or 8.0+, no modification is needed.

### Enable Module

1. Set file ownership:
   ```bash
   chown -R nginx:nginx /usr/share/zabbix/ui/modules/HostBatchClone/
   # Or chown -R www-data:www-data /usr/share/zabbix/ui/modules/HostBatchClone/
   ```

2. Reload PHP-FPM:
   ```bash
   systemctl reload php-fpm
   ```

3. Log into Zabbix Web interface, navigate to **Administration → General → Modules**.

4. Click **Scan directory** to detect the new module, find "Host Batch Import" and enable it.

5. Refresh the page, the module will appear under **Data collection** menu as "Host Batch Import", after "Hosts".

## Notes

- **Performance Consideration**: Import uses serial per-host method, large batches may take longer. Recommended limit: 500 hosts per import.

- **Host Name Uniqueness**: Host names share namespace with templates in Zabbix and must be globally unique. Preview phase detects both host and template conflicts.

- **Template Dependency**: Templates specified in CSV must exist in Zabbix. Non-existent templates will be skipped during import.

- **CSV Encoding**: Use UTF-8 BOM encoding for CSV files to ensure proper display in both Excel and the application.

- **Data Accuracy**: Created hosts are based on a snapshot of the source host's current configuration. Changes to the source host during import won't affect already-created hosts.

## Development

The module is based on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration, routing, and static resource declarations
- `Module.php`: Menu registration
- `CompatHelper.php`: Zabbix 6.0/6.4/7.x API compatibility helper class
- `actions/`: Controllers (main page, preview, import, source host loading)
- `views/`: Page views (main page, preview, JSON response)
- `assets/js/`: JavaScript (CSV parsing, table management, AJAX import progress)
- `assets/css/`: Module stylesheet

For extension, refer to [Zabbix Module Development Documentation](https://www.zabbix.com/documentation/current/manual/extend/modules).

## License

This project is licensed under the GPL-2.0 License.