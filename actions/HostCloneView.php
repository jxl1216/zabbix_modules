<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

namespace Modules\HostBatchClone\Actions;

use CController,
	CControllerResponseData,
	API;
use Modules\HostBatchClone\CompatHelper;
use Modules\HostBatchClone\LangHelper;

/**
 * Main page controller for Host Batch Clone module.
 *
 * Displays the batch clone form with source host selection,
 * CSV upload, and online table entry.
 */
class HostCloneView extends CController {

	public function init(): void {
		if (method_exists($this, 'disableCsrfValidation')) {
			$this->disableCsrfValidation();
		}
		if (method_exists($this, 'disableSIDvalidation')) {
			$this->disableSIDvalidation();
		}
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		// Get all monitored hosts for the source host dropdown.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'name'],
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		// Get all host groups for reference.
		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		// Get all templates for reference.
		// Zabbix 6.0: API::Template() exists but may have slightly different behavior.
		$templates = API::Template()->get([
			'output' => ['templateid', 'host', 'name'],
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		$data = [
			'hosts' => $hosts,
			'host_groups' => $host_groups,
			'templates' => $templates,
			'lang' => LangHelper::getAllForJs()
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
