<?php

declare(strict_types = 0);

namespace Modules\InspectionReport\Actions;

use CController, CControllerResponseData, CRoleHelper;

class InspectionReport extends CController {
    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS);
    }

    protected function doAction(): void {
        $data['inspection_cycle'] = 0;
        if (strtotime(date("Y-m-d")) <= strtotime(date("Y") . '-06-30')) {
            $data['inspection_cycle'] = 1;
        }
        elseif ((strtotime(date("Y-m-d")) >= strtotime(date("Y") . '-07-01')) && (strtotime(date("Y-m-d")) <= strtotime(date("Y") . '-09-30'))) {
            $data['inspection_cycle'] = 2;
        }
        else {
            $data['inspection_cycle'] = 3;
        }

        $response = new CControllerResponseData($data);
        $this->setResponse($response);
    }
}