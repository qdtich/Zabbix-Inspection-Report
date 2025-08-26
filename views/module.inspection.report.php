<?php

$html_page = (new CHtmlPage())->setTitle(_('Inspection report'));

$form_list = (new CFormList())->addRow(
    (new CDiv(_('This form allows you to view the inspection report of Zabbix components through a webpage or Excel format.')))
);

$cur_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
parse_str(parse_url($cur_url)['query'], $res);

if (!array_key_exists('sel', $res)) {
    $form_list->addRow(
        (new CLabel(_('Zabbix server'), 'zabbix_server_ids_ms'))->setAsteriskMark(),
        (new CMultiSelect([
            'name' => 'zabbix_server_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixServerForm',
                    'dstfld1' => 'zabbix_server_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        (new CLabel(_('Zabbix proxy'), 'zabbix_proxy_ids_ms')),
        (new CMultiSelect([
            'name' => 'zabbix_proxy_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixProxyForm',
                    'dstfld1' => 'zabbix_proxy_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        (new CLabel(_('Zabbix database'), 'zabbix_database_ids_ms')),
        (new CMultiSelect([
            'name' => 'zabbix_database_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixDatabaseForm',
                    'dstfld1' => 'zabbix_database_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    );
}
else {
    if ($res['sel'] == 0) {
        $form_list->addRow(
            (new CLabel(_('The server selection field cannot be empty. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 1) {
        $form_list->addRow(
            (new CLabel(_('The server has been mistakenly selected as a proxy. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 2) {
        $form_list->addRow(
            (new CLabel(_('The server has been mistakenly selected as a database. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 3) {
        $form_list->addRow(
            (new CLabel(_('The proxy has been mistakenly selected as a server. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 4) {
        $form_list->addRow(
            (new CLabel(_('The proxy has been mistakenly selected as a database. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 5) {
        $form_list->addRow(
            (new CLabel(_('The database has been mistakenly selected as a server. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }
    elseif ($res['sel'] == 6) {
        $form_list->addRow(
            (new CLabel(_('The database has been mistakenly selected as a proxy. Please reselect.')))->addClass(ZBX_STYLE_RED)
        );
    }

    $form_list->addRow(
        (new CLabel(_('Zabbix server'), 'zabbix_server_ids_ms'))->setAsteriskMark(),
        (new CMultiSelect([
            'name' => 'zabbix_server_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixServerForm',
                    'dstfld1' => 'zabbix_server_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        (new CLabel(_('Zabbix proxy'), 'zabbix_proxy_ids_ms')),
        (new CMultiSelect([
            'name' => 'zabbix_proxy_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixProxyForm',
                    'dstfld1' => 'zabbix_proxy_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    )
    ->addRow(
        (new CLabel(_('Zabbix database'), 'zabbix_database_ids_ms')),
        (new CMultiSelect([
            'name' => 'zabbix_database_ids[]',
            'object_name' => 'hosts',
            'data' => [],
            'multiple' => true,
            'popup' => [
                'parameters' => [
                    'srctbl' => 'hosts',
                    'srcfld1' => 'hostid',
                    'srcfld2' => 'host',
                    'dstfrm' => 'zabbixDatabaseForm',
                    'dstfld1' => 'zabbix_database_ids_'
                ]
            ]
        ]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
    );
}

$form_list->addRow(
    (new CLabel(_('Inspection cycle'), 'inspection_cycle'))->setAsteriskMark(),
    (new CRadioButtonList('inspection_cycle', (int) $data['inspection_cycle']))
        ->addValue(_('First quarter'), 0)
        ->addValue(_('Second quarter'), 1)
        ->addValue(_('Third quarter'), 2)
        ->addValue(_('Fourth quarter'), 3)
        ->setModern(true)
);

$form = (new CForm())
    ->setId('inspection-report-form')
    ->setName('inspectionReportForm')
    ->setAction((new CUrl('zabbix.php'))
        ->setArgument('action', 'inspection.detail')
        ->getUrl()
    )
    ->addItem(
        (new CTabView())
            ->addTab('inspection.report', _('Inspection report'), $form_list)
            ->setFooter(makeFormFooter(
                new CSubmit('generate', _('Generate')),
                [(new CSimpleButton(_('Reset')))->onClick("document.location = " . json_encode((new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl()))]
            ))
    );

$html_page->addItem($form)->show();