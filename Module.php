<?php

declare(strict_types = 0);

namespace Modules\InspectionReport;

use APP, CController, CWebUser, CMenuItem, Zabbix\Core\CModule;

class Module extends CModule {
	public function init(): void {
		$menu = _('Reports');

		APP::Component()->get('menu.main')->findOrAdd($menu)->getSubmenu()->insertAfter('Notifications', (new CMenuItem(_('Inspection report')))->setAction('inspection.report'));
	}

	public function onBeforeAction(CController $action): void {}

	public function onTerminate(CController $action): void {}
}