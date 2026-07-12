<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This JavaScript handles the main page interactions:
** - Tab switching (file upload vs online table)
** - CSV file parsing (client-side)
** - Online table row management
** - Source host info loading (AJAX)
** - CSV template download
** - Form submission to preview page
*/
?>

(function($) {
	'use strict';

	$(function() {
		var data = window.hostCloneData || {};
		var sourceHostid = '';
		var parsedRows = [];

		// ===== i18n =====
		var L = data.lang || {};

		function t(key) {
			return L[key] || key;
		}

		// ===== CSV Column Definitions =====
		var CSV_COLUMNS = [
			'host',          // Host Name (*)
			'visible_name',  // Visible Name
			'ip',            // Interface IP (*)
			'port',          // Port
			'groups',        // Host Groups (semicolon-separated)
			'templates',     // Templates (semicolon-separated)
			'tags',          // Tags (tag=value;tag=value)
			'macros',        // Macros ({$MACRO}=value;{$MACRO}=value)
			'description'    // Description
		];

		function getCSVHeaders() {
			return [
				t('csv.header.host'),
				t('csv.header.visible_name'),
				t('csv.header.ip'),
				t('csv.header.port'),
				t('csv.header.groups'),
				t('csv.header.templates'),
				t('csv.header.tags'),
				t('csv.header.macros'),
				t('csv.header.description')
			];
		}

		// ===== Tab Switching =====
		$('.tab-btn').on('click', function() {
			var tab = $(this).data('tab');
			$('.tab-btn').removeClass('active');
			$(this).addClass('active');
			$('.tab-content').removeClass('active').hide();
			$('#tab-' + tab).addClass('active').show();
			updatePreviewButton();
		});

	// ===== Unified Host / Host Group Search =====
	var allHosts = data.hosts || [];
	var allGroups = data.hostGroups || [];
	var selectedTags = [];
	var searchInputTimer = null;
	var dropdownVisible = false;
	var activeItemIndex = -1;
	var currentFilteredItems = [];

	function getHostLabel(host) {
		var label = host.name || '';
		if (host.host && host.host !== host.name) {
			label += ' (' + host.host + ')';
		}
		return label;
	}

	function fuzzyMatch(text, keyword) {
		if (!keyword) return true;
		text = (text || '').toLowerCase();
		keyword = keyword.toLowerCase().trim();
		return text.indexOf(keyword) >= 0;
	}

	function renderTags() {
		var $container = $('#selected-tags');
		$container.empty();
		$.each(selectedTags, function(i, tag) {
			var label = tag.name;
			var icon = tag.type === 'host' ? 'H' : 'G';
			var tagHtml = '<span class="search-tag" data-index="' + i + '" data-type="' + tag.type + '" data-id="' + escapeHtml(tag.id) + '">' +
				'<span class="search-tag-icon">' + icon + '</span>' +
				'<span class="search-tag-name">' + escapeHtml(label) + '</span>' +
				'<span class="search-tag-remove" title="' + t('search.remove') + '">&times;</span>' +
				'</span>';
			$container.append(tagHtml);
		});
		applyFilters();
	}

	function addTag(type, id, name) {
		// Prevent duplicate tags
		for (var i = 0; i < selectedTags.length; i++) {
			if (selectedTags[i].type === type && selectedTags[i].id === id) {
				return;
			}
		}
		selectedTags.push({ type: type, id: id, name: name });
		renderTags();
		$('#host-search-text').val('').focus();
		renderDropdown('');
	}

	function removeTag(index) {
		selectedTags.splice(index, 1);
		renderTags();
	}

	// ===== Dropdown Rendering =====
	function renderDropdown(keyword) {
		var $dropdown = $('#host-search-dropdown');
		var $hostItems = $('#search-host-items');
		var $groupItems = $('#search-group-items');
		var $noMatch = $('#search-no-match');
		var $categories = $dropdown.find('.search-category');

		keyword = (keyword || '').toLowerCase().trim();

		var matchedHosts = [];
		$.each(allHosts, function(i, host) {
			var label = getHostLabel(host);
			if (fuzzyMatch(label, keyword) || fuzzyMatch(host.host, keyword)) {
				// Exclude already selected hosts
				var alreadySelected = false;
				for (var j = 0; j < selectedTags.length; j++) {
					if (selectedTags[j].type === 'host' && selectedTags[j].id === host.hostid) {
						alreadySelected = true;
						break;
					}
				}
				if (!alreadySelected) {
					matchedHosts.push(host);
				}
			}
		});

		var matchedGroups = [];
		$.each(allGroups, function(i, group) {
			if (fuzzyMatch(group.name, keyword)) {
				// Exclude already selected groups
				var alreadySelected = false;
				for (var j = 0; j < selectedTags.length; j++) {
					if (selectedTags[j].type === 'group' && selectedTags[j].id === group.groupid) {
						alreadySelected = true;
						break;
					}
				}
				if (!alreadySelected) {
					matchedGroups.push(group);
				}
			}
		});

		// Build current item list for keyboard navigation
		currentFilteredItems = [];
		$hostItems.empty();
		$groupItems.empty();

		if (matchedHosts.length === 0 && matchedGroups.length === 0) {
			$categories.hide();
			$noMatch.show();
			$dropdown.show();
			dropdownVisible = true;
			activeItemIndex = -1;
			return;
		}

		$categories.show();
		$noMatch.hide();

		// Hosts
		if (matchedHosts.length > 0) {
			$categories.filter('[data-category="host"]').show();
			$.each(matchedHosts, function(i, host) {
				var label = getHostLabel(host);
				var $item = $('<div class="search-item"></div>')
					.data('type', 'host')
					.data('id', host.hostid)
					.data('name', label);
				$item.append('<span class="search-item-icon host-icon"></span>');
				$item.append('<span class="search-item-name">' + escapeHtml(label) + '</span>');
				$hostItems.append($item);
				currentFilteredItems.push($item[0]);
			});
		} else {
			$categories.filter('[data-category="host"]').hide();
		}

		// Groups
		if (matchedGroups.length > 0) {
			$categories.filter('[data-category="group"]').show();
			$.each(matchedGroups, function(i, group) {
				var $item = $('<div class="search-item"></div>')
					.data('type', 'group')
					.data('id', group.groupid)
					.data('name', group.name);
				$item.append('<span class="search-item-icon group-icon"></span>');
				$item.append('<span class="search-item-name">' + escapeHtml(group.name) + '</span>');
				$groupItems.append($item);
				currentFilteredItems.push($item[0]);
			});
		} else {
			$categories.filter('[data-category="group"]').hide();
		}

		$dropdown.show();
		dropdownVisible = true;
		activeItemIndex = -1;
	}

	function hideDropdown() {
		$('#host-search-dropdown').hide();
		dropdownVisible = false;
		activeItemIndex = -1;
	}

	// ===== Keyboard Navigation =====
	function setActiveItem(index) {
		$(currentFilteredItems).removeClass('active');
		if (index >= 0 && index < currentFilteredItems.length) {
			activeItemIndex = index;
			var $item = $(currentFilteredItems[activeItemIndex]);
			$item.addClass('active');
			currentFilteredItems[activeItemIndex].scrollIntoView({ block: 'nearest' });
		} else {
			activeItemIndex = -1;
		}
	}

	// ===== Filter Source List by Selected Tags =====
	function applyFilters() {
		var selectedHostIds = [];
		var selectedGroupIds = [];

		$.each(selectedTags, function(i, tag) {
			if (tag.type === 'host') {
				selectedHostIds.push(tag.id);
			} else if (tag.type === 'group') {
				selectedGroupIds.push(tag.id);
			}
		});

		var filtered = [];
		$.each(allHosts, function(i, host) {
			var matchHost = selectedHostIds.length > 0 && selectedHostIds.indexOf(host.hostid) >= 0;
			var matchGroup = false;
			if (selectedGroupIds.length > 0) {
				$.each(host.groupids || [], function(j, gid) {
					if (selectedGroupIds.indexOf(gid) >= 0) {
						matchGroup = true;
						return false;
					}
				});
			}

			// If no tags selected, show all; otherwise show matching hosts
			if (selectedTags.length === 0) {
				filtered.push(host);
			} else if (matchHost || matchGroup) {
				filtered.push(host);
			}
		});

		// Rebuild source host select list
		var $select = $('#source-host-select');
		$select.empty();

		if (filtered.length === 0) {
			$select.append('<option value="" disabled>' + t('filter.no_match') + '</option>');
		} else {
			$.each(filtered, function(i, host) {
				var label = getHostLabel(host);
				var $opt = $('<option></option>').val(host.hostid).text(label);
				$select.append($opt);
			});
		}

		// Reset source host selection
		sourceHostid = '';
		$('#hidden-source-hostid').val('');
		$('#load-source-btn').prop('disabled', true);
		$('#source-host-info').hide().empty();
		updatePreviewButton();
	}

	// ===== Search Input Events =====
	$('#host-search-input').on('click', function(e) {
		if (!$(e.target).closest('.search-tag-remove').length) {
			$('#host-search-text').focus();
		}
	});

	$('#host-search-text').on('focus', function() {
		if (!dropdownVisible) {
			renderDropdown($(this).val());
		}
	});

	$('#host-search-text').on('input', function() {
		clearTimeout(searchInputTimer);
		var val = $(this).val();
		searchInputTimer = setTimeout(function() {
			renderDropdown(val);
		}, 150);
	});

	$('#host-search-text').on('keydown', function(e) {
		if (!dropdownVisible) {
			if (e.which === 40) { // down arrow: open dropdown
				renderDropdown($(this).val());
				e.preventDefault();
			}
			return;
		}

		if (e.which === 40) { // down arrow
			e.preventDefault();
			var next = activeItemIndex + 1;
			if (next >= currentFilteredItems.length) next = 0;
			setActiveItem(next);
		} else if (e.which === 38) { // up arrow
			e.preventDefault();
			var prev = activeItemIndex - 1;
			if (prev < 0) prev = currentFilteredItems.length - 1;
			setActiveItem(prev);
		} else if (e.which === 13) { // enter
			e.preventDefault();
			if (activeItemIndex >= 0 && activeItemIndex < currentFilteredItems.length) {
				var $item = $(currentFilteredItems[activeItemIndex]);
				addTag($item.data('type'), $item.data('id'), $item.data('name'));
			}
		} else if (e.which === 27) { // escape
			e.preventDefault();
			hideDropdown();
		}
	});

	// ===== Dropdown Item Selection =====
	$(document).on('mousedown', '#search-host-items .search-item, #search-group-items .search-item', function(e) {
		e.preventDefault();
		var $item = $(this);
		addTag($item.data('type'), $item.data('id'), $item.data('name'));
	});

	// ===== Remove Tag =====
	$(document).on('click', '.search-tag-remove', function(e) {
		e.stopPropagation();
		var index = $(this).closest('.search-tag').data('index');
		removeTag(index);
	});

	// ===== Click Outside to Close Dropdown =====
	$(document).on('click', function(e) {
		var $target = $(e.target);
		if (!$target.closest('.host-search-wrapper').length) {
			hideDropdown();
		}
	});

	// ===== Source Host Selection from List =====
	$('#source-host-select').on('change', function() {
		sourceHostid = $(this).val() || '';
		$('#hidden-source-hostid').val(sourceHostid);

		if (sourceHostid) {
			$('#load-source-btn').prop('disabled', false);
		} else {
			$('#load-source-btn').prop('disabled', true);
			$('#source-host-info').hide().empty();
		}
		updatePreviewButton();
	});

		$('#load-source-btn').on('click', function() {
			if (!sourceHostid) return;

			var btn = $(this);
			btn.prop('disabled', true).text(t('step1.loading'));

			$.ajax({
				url: 'zabbix.php?action=host.clone.source',
				method: 'POST',
				data: {
					source_hostid: sourceHostid
				},
				dataType: 'json',
				success: function(resp) {
					if (resp.success && resp.source_host) {
						displaySourceHostInfo(resp.source_host);
					} else {
						$('#source-host-info').show().html(
							'<div class="error-msg">' + (resp.error || t('step1.load_failed')) + '</div>'
						);
					}
				},
				error: function() {
					$('#source-host-info').show().html(
						'<div class="error-msg">' + t('step1.load_failed_network') + '</div>'
					);
				},
				complete: function() {
					btn.prop('disabled', false).text(t('step1.load_btn'));
				}
			});
		});

		function displaySourceHostInfo(host) {
			var typeNames = {1: t('iface.Agent'), 2: t('iface.SNMP'), 3: t('iface.IPMI'), 4: t('iface.JMX')};
			var html = '<h4>' + t('step1.source_host_label') + escapeHtml(host.name) + '</h4>';
			html += '<table class="list-table source-info-table">';

			// Interfaces
			html += '<tr><th>' + t('preview.interfaces') + '</th><td>';
			if (host.interfaces && host.interfaces.length > 0) {
				var ifaces = [];
				$.each(host.interfaces, function(i, iface) {
					var typeName = typeNames[iface.type] || (t('iface.Type') + iface.type);
					var addr = iface.useip == 1 ? iface.ip : iface.dns;
					ifaces.push(typeName + ': ' + addr + ':' + iface.port);
				});
				html += ifaces.join('<br/>');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Host Groups
			html += '<tr><th>' + t('col.host_groups') + '</th><td>';
			if (host.hostgroups && host.hostgroups.length > 0) {
				html += $.map(host.hostgroups, function(g) { return escapeHtml(g.name); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Templates
			html += '<tr><th>' + t('col.templates') + '</th><td>';
			if (host.parenttemplates && host.parenttemplates.length > 0) {
				html += $.map(host.parenttemplates, function(tp) { return escapeHtml(tp.name); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Tags
			html += '<tr><th>' + t('col.tags') + '</th><td>';
			if (host.tags && host.tags.length > 0) {
				html += $.map(host.tags, function(tp) { return escapeHtml(tp.tag + '=' + tp.value); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Macros
			html += '<tr><th>' + t('col.macros') + '</th><td>';
			if (host.macros && host.macros.length > 0) {
				html += $.map(host.macros, function(m) { return escapeHtml(m.macro + '=' + m.value); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Description
			html += '<tr><th>' + t('col.description') + '</th><td>' + escapeHtml(host.description || '') + '</td></tr>';

			html += '</table>';
			html += '<p class="inherited-note">' + t('step1.inherited_note') + '</p>';

			$('#source-host-info').show().html(html);
		}

		// ===== CSV File Upload =====
		$('#csv-file-input').on('change', function() {
			var file = this.files[0];
			$('#parse-csv-btn').prop('disabled', !file);
		});

		$('#parse-csv-btn').on('click', function() {
			var file = $('#csv-file-input')[0].files[0];
			if (!file) return;

			var encoding = $('#csv-encoding-select').val() || 'UTF-8';
			var reader = new FileReader();
			reader.onload = function(e) {
				var text = e.target.result;
				// Strip UTF-8 BOM if present
				if (text.charCodeAt(0) === 0xFEFF) {
					text = text.substring(1);
				}
				parsedRows = parseCSV(text);

				var resultDiv = $('#csv-parse-result');
				if (parsedRows.length === 0) {
					resultDiv.show().html('<div class="error-msg">' + t('csv.no_data') + '</div>');
					return;
				}

				// Validate rows
				var validCount = 0;
				var invalidCount = 0;
				$.each(parsedRows, function(i, row) {
					if (row.host && row.ip) {
						validCount++;
					} else {
						invalidCount++;
						row._invalid = true;
					}
				});

				var html = '<div class="csv-parse-summary">';
				html += '<span class="summary-item">' + t('csv.parsed_rows') + parsedRows.length + '</span>';
				html += '<span class="summary-item summary-ok">' + t('csv.valid_rows') + validCount + '</span>';
				if (invalidCount > 0) {
					html += '<span class="summary-item summary-conflict">' + t('csv.invalid_rows') + invalidCount + '</span>';
				}
				html += '</div>';

				// Show parsed data preview (first 10 rows)
				var csvHeaders = getCSVHeaders();
				html += '<div class="table-wrapper"><table class="list-table csv-preview-table"><thead><tr>';
				$.each(csvHeaders, function(i, h) {
					html += '<th>' + h + '</th>';
				});
				html += '</tr></thead><tbody>';
				var previewCount = Math.min(parsedRows.length, 10);
				for (var i = 0; i < previewCount; i++) {
					var row = parsedRows[i];
					var cls = row._invalid ? 'row-conflict' : 'row-ok';
					html += '<tr class="' + cls + '">';
					html += '<td>' + escapeHtml(row.host || '') + '</td>';
					html += '<td>' + escapeHtml(row.visible_name || '') + '</td>';
					html += '<td>' + escapeHtml(row.ip || '') + '</td>';
					html += '<td>' + escapeHtml(row.port || '') + '</td>';
					html += '<td>' + escapeHtml(row.groups || '') + '</td>';
					html += '<td>' + escapeHtml(row.templates || '') + '</td>';
					html += '<td>' + escapeHtml(row.tags || '') + '</td>';
					html += '<td>' + escapeHtml(row.macros || '') + '</td>';
					html += '<td>' + escapeHtml(row.description || '') + '</td>';
					html += '</tr>';
				}
				if (parsedRows.length > 10) {
					html += '<tr><td colspan="9" class="more-rows">' + t('csv.more_rows') + (parsedRows.length - 10) + t('csv.more_rows_suffix') + '</td></tr>';
				}
				html += '</tbody></table></div>';

				// Also populate the online table with parsed data
				populateTableFromRows(parsedRows);

				resultDiv.show().html(html);
				updatePreviewButton();
			};
			reader.readAsText(file, encoding);
		});

		// ===== CSV Parsing =====
		function parseCSV(text) {
			var rows = [];
			var lines = text.split(/\r\n|\r|\n/);
			var startIdx = 0;

			// Detect and skip header row
			if (lines.length > 0) {
				var firstLine = lines[0].toLowerCase().replace(/\s/g, '');
				if (firstLine.indexOf('hostname') >= 0 || firstLine.indexOf('host_name') >= 0 ||
					firstLine.indexOf('host') >= 0 || firstLine.indexOf('主机名称') >= 0) {
					startIdx = 1;
				}
			}

			for (var i = startIdx; i < lines.length; i++) {
				var line = lines[i].trim();
				if (line === '') continue;

				var fields = parseCSVLine(line);
				if (fields.length === 0) continue;

				// Pad fields to match column count
				while (fields.length < CSV_COLUMNS.length) {
					fields.push('');
				}

				var row = {};
				for (var j = 0; j < CSV_COLUMNS.length; j++) {
					row[CSV_COLUMNS[j]] = (fields[j] || '').trim();
				}

				// Skip completely empty rows
				var hasData = false;
				for (var key in row) {
					if (row[key] !== '') { hasData = true; break; }
				}
				if (hasData) {
					rows.push(row);
				}
			}

			return rows;
		}

		function parseCSVLine(line) {
			var fields = [];
			var current = '';
			var inQuotes = false;

			for (var i = 0; i < line.length; i++) {
				var char = line[i];

				if (char === '"') {
					if (inQuotes && i + 1 < line.length && line[i + 1] === '"') {
						// Escaped quote
						current += '"';
						i++;
					} else {
						inQuotes = !inQuotes;
					}
				} else if (char === ',' && !inQuotes) {
					fields.push(current);
					current = '';
				} else {
					current += char;
				}
			}

			fields.push(current);
			return fields;
		}

		// ===== CSV Template Download =====
		$('#download-template-btn').on('click', function() {
			var csvHeaders = getCSVHeaders();
			var csv = csvHeaders.join(',') + '\n';
			// Add example rows
			csv += 'web-server-01,Web Server 01,192.168.1.10,10050,Servers;Web Servers,Linux by Zabbix Agent;Nginx by HTTP,env=prod;os=linux,{$SNMP_COMMUNITY}=public,Web server 01\n';
			csv += 'db-server-01,DB Server 01,192.168.1.20,10050,Servers;Database Servers,MySQL by Zabbix Agent,env=prod;os=linux;role=db,{$MYSQL_PORT}=3306,Database server 01\n';

			var blob = new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'});
			var link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = 'host_batch_clone_template.csv';
			link.click();
		});

		// ===== Online Table Management =====
		$('#add-row-btn').on('click', function() {
			addTableRow();
		});

		$('#remove-row-btn').on('click', function() {
			$('#host-data-tbody input.row-checkbox:checked').each(function() {
				$(this).closest('tr').remove();
			});
			updateRowCount();
			updatePreviewButton();
		});

		$('#clear-table-btn').on('click', function() {
			if (confirm(t('table.clear_confirm'))) {
				$('#host-data-tbody').empty();
				addTableRow();
				updateRowCount();
				updatePreviewButton();
			}
		});

		$('#select-all-rows').on('change', function() {
			var checked = $(this).prop('checked');
			$('#host-data-tbody input.row-checkbox').prop('checked', checked);
		});

		function addTableRow(rowData) {
			rowData = rowData || {};
			var phRequired = t('table.placeholder.required');
			var phInherited = t('table.placeholder.inherited');
			var row = '<tr class="data-row">' +
				'<td class="col-checkbox"><input type="checkbox" class="row-checkbox" /></td>' +
				'<td><input type="text" class="input-host" value="' + escapeHtml(rowData.host || '') + '" placeholder="' + phRequired + '" /></td>' +
				'<td><input type="text" class="input-visible-name" value="' + escapeHtml(rowData.visible_name || '') + '" /></td>' +
				'<td><input type="text" class="input-ip" value="' + escapeHtml(rowData.ip || '') + '" placeholder="' + phRequired + '" /></td>' +
				'<td><input type="text" class="input-port" value="' + escapeHtml(rowData.port || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-groups" value="' + escapeHtml(rowData.groups || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-templates" value="' + escapeHtml(rowData.templates || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-tags" value="' + escapeHtml(rowData.tags || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-macros" value="' + escapeHtml(rowData.macros || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-description" value="' + escapeHtml(rowData.description || '') + '" placeholder="' + phInherited + '" /></td>' +
				'</tr>';
			$('#host-data-tbody').append(row);
			updateRowCount();
			updatePreviewButton();
		}

		function populateTableFromRows(rows) {
			$('#host-data-tbody').empty();
			$.each(rows, function(i, row) {
				addTableRow(row);
			});
			if (rows.length === 0) {
				addTableRow();
			}
		}

		function updateRowCount() {
			$('#row-count').text($('#host-data-tbody tr').length);
		}

		// ===== Preview Button Management =====
		function updatePreviewButton() {
			var hasSource = !!sourceHostid;
			var hasData = false;

			// Check if there's data in the table
			$('#host-data-tbody tr').each(function() {
				var host = $(this).find('.input-host').val();
				var ip = $(this).find('.input-ip').val();
				if (host && host.trim() && ip && ip.trim()) {
					hasData = true;
					return false; // break
				}
			});

			// Also check parsed CSV rows
			if (!hasData && parsedRows.length > 0) {
				$.each(parsedRows, function(i, row) {
					if (row.host && row.ip) {
						hasData = true;
						return false; // break
					}
				});
			}

			$('#preview-btn').prop('disabled', !(hasSource && hasData));

			if (!hasSource) {
				$('.preview-hint').text(t('preview.hint_no_source'));
			} else if (!hasData) {
				$('.preview-hint').text(t('preview.hint_no_data'));
			} else {
				$('.preview-hint').text(t('preview.hint_ready'));
			}
		}

		// Live validation
		$(document).on('input', '#host-data-tbody input', function() {
			updatePreviewButton();
		});

		// ===== Form Submission to Preview =====
		$('#preview-btn').on('click', function() {
			var hostData = collectHostData();
			if (hostData.length === 0) {
				alert(t('preview.alert_no_data'));
				return;
			}

			$('#hidden-source-hostid').val(sourceHostid);
			$('#hidden-host-data').val(JSON.stringify(hostData));
			$('#preview-form').submit();
		});

		function collectHostData() {
			var rows = [];
			$('#host-data-tbody tr').each(function() {
				var host = $(this).find('.input-host').val();
				var ip = $(this).find('.input-ip').val();

				if (!host || !ip) return; // skip
				host = host.trim();
				ip = ip.trim();
				if (!host || !ip) return; // skip

				rows.push({
					host: host,
					visible_name: ($(this).find('.input-visible-name').val() || '').trim(),
					ip: ip,
					port: ($(this).find('.input-port').val() || '').trim(),
					groups: ($(this).find('.input-groups').val() || '').trim(),
					templates: ($(this).find('.input-templates').val() || '').trim(),
					tags: ($(this).find('.input-tags').val() || '').trim(),
					macros: ($(this).find('.input-macros').val() || '').trim(),
					description: ($(this).find('.input-description').val() || '').trim()
				});
			});
			return rows;
		}

		// ===== Utility Functions =====
		function escapeHtml(str) {
			if (str === null || str === undefined) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		// ===== Initialize =====
		// Populate source host list (all hosts initially)
		applyFilters();

		// Start with one empty row (only if table body is empty)
		if ($('#host-data-tbody tr').length === 0) {
			addTableRow();
		}
		updatePreviewButton();
	});

})(jQuery);
