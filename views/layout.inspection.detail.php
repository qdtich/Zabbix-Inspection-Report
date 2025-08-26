<?php

if ($data['host_sel'] == -1) {
    $html_page = (new CHtmlPage())->setTitle(_('Inspection detail'));

    $n = 1;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([(new CColHeader(_('Description')))->addStyle('width:15%'), (new CColHeader(_('Type')))->addStyle('width: 3%'), (new CColHeader(_('Value')))->addStyle('width: 4%'), _('Threshold'), (new CColHeader(_('Analysis')))->addStyle('width: 45%'), _('Suggestion')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    foreach ($data['result_analysis'] as $data_result_analysis) {
        ${"info_table_" . strval($n)}->addRow([
            $data_result_analysis['description'],
            $data_result_analysis['type'],
            $data_result_analysis['value'],
            $data_result_analysis['threshold'],
            $data_result_analysis['analysis'],
            (new CSpan($data_result_analysis['suggestion']))->addClass(ZBX_STYLE_GREEN)
        ]);
    }

    $html_page
        ->addItem((new CDiv(''))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem(_('Analysis of inspection results'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Server is running'), _('Host count ( enabled / disabled )'), _('Template count'), _('Item count ( enabled / disabled / not supported )'), _('Trigger count ( enabled / disabled [ problem / ok ] )'), _('User count'), _('NVPS'), _('HA stat')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        (new CSpan(($data['zabbix_system_info']['status']['is_running'] ? _('Yes') : _('No'))))->addClass($data['zabbix_system_info']['status']['is_running'] ? ZBX_STYLE_COLOR_POSITIVE : ZBX_STYLE_COLOR_NEGATIVE),
        $data['zabbix_system_info']['status']['hosts_count'] . ' ( ' . $data['zabbix_system_info']['status']['hosts_count_monitored'] . ' / ' . $data['zabbix_system_info']['status']['hosts_count_not_monitored'] . ' )',
        $data['zabbix_system_info']['status']['hosts_count_template'],
        $data['zabbix_system_info']['status']['items_count'] . ' ( ' . $data['zabbix_system_info']['status']['items_count_monitored'] . ' / ' . $data['zabbix_system_info']['status']['items_count_disabled'] . ' / ' . $data['zabbix_system_info']['status']['items_count_not_supported'] . ' )',
        $data['zabbix_system_info']['status']['triggers_count'] . ' ( ' . $data['zabbix_system_info']['status']['triggers_count_enabled'] . ' / ' . $data['zabbix_system_info']['status']['triggers_count_disabled'] . ' [ ' . $data['zabbix_system_info']['status']['triggers_count_on'] . ' / ' . $data['zabbix_system_info']['status']['triggers_count_off'] . ' ] )',
        $data['zabbix_system_info']['status']['users_count'],
        round($data['zabbix_system_info']['status']['vps_total'], 2),
        ($data['zabbix_system_info']['ha_cluster_enabled'] ? _('Enabled') : _('Disabled'))
    ]);

    $html_page
        ->addItem(_('System information'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([(new CColHeader(_('Hostname')))->addStyle('width: 8%'), _('Uname'), _('IP'), _('Version'), _('CPU num'), _('Mem size')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    if (count($data['zabbix_server_info']) > 0) {
        foreach ($data['zabbix_server_info'] as $zabbix_server_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_server_info['zabbix_server_hostname'],
                $zabbix_server_info['zabbix_server_uname'],
                $zabbix_server_info['zabbix_server_ip'],
                $zabbix_server_info['zabbix_server_version'],
                $zabbix_server_info['zabbix_server_cpu_num'],
                $zabbix_server_info['zabbix_server_mem_total_size'] . ' GB'
            ]);
        }
    }

    if (count($data['zabbix_proxy_info']) > 0) {
        foreach ($data['zabbix_proxy_info'] as $zabbix_proxy_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_info['zabbix_proxy_hostname'],
                $zabbix_proxy_info['zabbix_proxy_uname'],
                $zabbix_proxy_info['zabbix_proxy_ip'],
                $zabbix_proxy_info['zabbix_proxy_version'],
                $zabbix_proxy_info['zabbix_proxy_cpu_num'],
                $zabbix_proxy_info['zabbix_proxy_mem_total_size'] . ' GB'
            ]);
        }
    }

    if (count($data['zabbix_database_info']) > 0) {
        foreach ($data['zabbix_database_info'] as $zabbix_database_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_database_info['zabbix_database_hostname'],
                $zabbix_database_info['zabbix_database_uname'],
                $zabbix_database_info['zabbix_database_ip'],
                $zabbix_database_info['zabbix_database_version'],
                $zabbix_database_info['zabbix_database_cpu_num'],
                $zabbix_database_info['zabbix_database_mem_total_size'] . ' GB'
            ]);
        }
    }

    if ((count($data['zabbix_server_info']) > 0) || (count($data['zabbix_proxy_info']) > 0) || (count($data['zabbix_database_info']) > 0)) {
        $html_page
            ->addItem(_('Server overview'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));
    }

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([(new CColHeader(_('Hostname')))->addStyle('width: 8%'), _('Host count'), _('Item count'), _('Mode'), _('State'), _('NVPS')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    if (count($data['zabbix_proxy_info']) > 0) {
        foreach ($data['zabbix_proxy_info'] as $zabbix_proxy_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_info['zabbix_proxy_hostname'],
                $zabbix_proxy_info['zabbix_proxy_hosts_count'],
                $zabbix_proxy_info['zabbix_proxy_items_count'],
                $zabbix_proxy_info['zabbix_proxy_mode'],
                $zabbix_proxy_info['zabbix_proxy_state'],
                $zabbix_proxy_info['zabbix_proxy_nvps']
            ]);
        }

        $html_page
            ->addItem(_('Proxy information'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));
    }

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Database Version'), _('Buffer pool utilization'), _('Insert per second'), _('Select per second'), _('Connections per second'), _('Max used connections'), _('Slave IO running'), _('Slave SQL running')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    if (count($data['zabbix_database_info']) > 0) {
        foreach ($data['zabbix_database_info'] as $zabbix_database_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_database_info['zabbix_database_dbver'],
                [(new CSpan($zabbix_database_info['zabbix_database_bpu']))->addClass($zabbix_database_info['zabbix_database_bpu'] > 80 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                $zabbix_database_info['zabbix_database_cir'],
                $zabbix_database_info['zabbix_database_csr'],
                $zabbix_database_info['zabbix_database_cps'],
                $zabbix_database_info['zabbix_database_used_conn'],
                $zabbix_database_info['zabbix_database_slave_io'],
                $zabbix_database_info['zabbix_database_slave_sql']
            ]);
        }

        $html_page
            ->addItem(_('Database information'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));
    }

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Queue'), _('Queue over 10m (TH: < 30)'), _('Discovery queue (TH: < 30)'), _('LLD queue (TH: < 30)'), _('Preprocessing queue (TH: < 30)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        $data['zabbix_queue']['queue'],
        [(new CSpan($data['zabbix_queue']['queue_over_10m']))->addClass($data['zabbix_queue']['queue_over_10m'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue']['discovery_queue']))->addClass($data['zabbix_queue']['discovery_queue'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue']['lld_queue']))->addClass($data['zabbix_queue']['lld_queue'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue']['preprocessing_queue']))->addClass($data['zabbix_queue']['preprocessing_queue'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)]
    ]);

    $html_page
        ->addItem(_('Queue overview'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Zabbix agent (over 10m)'), _('Zabbix agent active (over 10m)'), _('Simple check (over 10m)'), _('SNMP agent (over 10m)'), _('Database monitor (over 10m)'), _('HTTP agent (over 10m)'), _('Calculated (over 10m)'), _('Script (over 10m)'), _('Browser (over 10m)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_queue_overview']['zabbix_agent']))->addClass($data['zabbix_queue_overview']['zabbix_agent'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['zabbix_agent_active']))->addClass($data['zabbix_queue_overview']['zabbix_agent_active'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['simple_check']))->addClass($data['zabbix_queue_overview']['simple_check'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['snmp_agent']))->addClass($data['zabbix_queue_overview']['snmp_agent'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['database_monitor']))->addClass($data['zabbix_queue_overview']['database_monitor'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['http_agent']))->addClass($data['zabbix_queue_overview']['http_agent'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['calculated']))->addClass($data['zabbix_queue_overview']['calculated'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['script']))->addClass($data['zabbix_queue_overview']['script'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)],
        [(new CSpan($data['zabbix_queue_overview']['browser']))->addClass($data['zabbix_queue_overview']['browser'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)]
    ]);

    $html_page
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Scheduled check'), _('Delayed by'), _('Host'), _('Name'), _('Proxy')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    if ($data['zabbix_queue_detail']['queue_detail_data'] != []) {
        $c = 0;
        foreach ($data['zabbix_queue_detail']['queue_detail_data'] as $itemid => $item_queue_detail_data) {
            if ($c < 10) {
                if (!array_key_exists($itemid, $data['items'])) {
                    continue;
                }

                $item = $data['items'][$itemid];
                $host = reset($item['hosts']);

                ${"info_table_" . strval($n)}->addRow([
                    zbx_date2str(DATE_TIME_FORMAT_SECONDS, $item_queue_detail_data['nextcheck']),
                    zbx_date2age($item_queue_detail_data['nextcheck']),
                    $host['name'],
                    $item['name'],
                    array_key_exists($data['hosts'][$item['hostid']]['proxyid'], $data['proxies'])
                        ? $data['proxies'][$data['hosts'][$item['hostid']]['proxyid']]['name']
                        : ''
                ]);
            }
            else {
                break;
            }

            $c++;
        }
    }
    else {
        ${"info_table_" . strval($n)}->addRow([0, 0, 0, 0, 0]);
    }

    $html_page
        ->addItem(_('Queue detail'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Host name'), _('Trigger description'), _('Trigger priority')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    foreach ($data['zabbix_problem_info'] as $zabbix_trigger_info) {
        ${"info_table_" . strval($n)}->addRow([
            $zabbix_trigger_info['name'],
            $zabbix_trigger_info['description'],
            $zabbix_trigger_info['priority']
        ]);
    }

    $html_page
        ->addItem(_('Problem overview'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Server name'), _('CPU util (TH: < 90 %)'), _('CPU IOwait (TH: < 30 %)'), _('CPU load'), _('Memory Util (TH: < 90 %)'), _('SWAP util (TH: < 30 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    foreach ($data['zabbix_server_performance'] as $zabbix_server_perf) {
        ${"info_table_" . strval($n)}->addRow([
            $zabbix_server_perf['server_name'],
            [(new CSpan($zabbix_server_perf['cpu_util']))->addClass($zabbix_server_perf['cpu_util'] < 90 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
            [(new CSpan($zabbix_server_perf['cpu_iowait']))->addClass($zabbix_server_perf['cpu_iowait'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
            $zabbix_server_perf['cpu_load'],
            [(new CSpan($zabbix_server_perf['mem_util']))->addClass($zabbix_server_perf['mem_util'] < 90 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
            [(new CSpan($zabbix_server_perf['swap_util']))->addClass($zabbix_server_perf['swap_util'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
        ]);
    }

    $html_page
        ->addItem(_('Server performance metric'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Configuration cache (TH: < 75 %)'), _('History index cache (TH: < 75 %)'), _('History write cache (TH: < 75 %)'), _('Trend function cache of misses (TH: < 30 %)'), _('Trend function cache of unique requests (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_cache']['rcache_buffer_pused']))->addClass($data['zabbix_server_cache']['rcache_buffer_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_cache']['wcache_index_pused']))->addClass($data['zabbix_server_cache']['wcache_index_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_cache']['wcache_history_pused']))->addClass($data['zabbix_server_cache']['wcache_history_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_cache']['tcache_pmisses']))->addClass($data['zabbix_server_cache']['tcache_pmisses'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_cache']['tcache_pitems']))->addClass($data['zabbix_server_cache']['tcache_pitems'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem(_('Server cache metric'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Trend write cache (TH: < 75 %)'), _('Value cache (TH: < 75 %)'), _('Value cache hits'), _('Value cache misses'), _('VMware cache (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_cache']['wcache_trend_pused']))->addClass($data['zabbix_server_cache']['wcache_trend_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_cache']['vcache_buffer_pused']))->addClass($data['zabbix_server_cache']['vcache_buffer_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        $data['zabbix_server_cache']['vcache_cache_hits'],
        $data['zabbix_server_cache']['vcache_cache_misses'],
        [(new CSpan($data['zabbix_server_cache']['vmware_buffer_pused']))->addClass($data['zabbix_server_cache']['vmware_buffer_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Alerter (TH: < 75 %)'), _('Alert manager (TH: < 75 %)'), _('Alert syncer (TH: < 75 %)'), _('Availability manager (TH: < 75 %)'), _('Configuration syncer (TH: < 75 %)'), _('Configuration syncer worker (TH: < 75 %)'), _('Connector manager (TH: < 75 %)'), _('Connector worker (TH: < 75 %)'), _('Discovery manager (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_alerter_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_alerter_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_alert_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_alert_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_alert_syncer_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_alert_syncer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_availability_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_availability_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_configuration_syncer_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_configuration_syncer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_configuration_syncer_worker_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_configuration_syncer_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_connector_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_connector_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_connector_worker_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_connector_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_discovery_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_discovery_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem(_('Server internal processes metric'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Discovery worker (TH: < 75 %)'), _('Escalator (TH: < 75 %)'), _('History poller (TH: < 75 %)'), _('History syncer (TH: < 75 %)'), _('Housekeeper (TH: < 75 %)'), _('IPMI manager (TH: < 75 %)'), _('LLD manager (TH: < 75 %)'), _('LLD worker (TH: < 75 %)'), _('Preprocessing manager (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_discovery_worker_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_discovery_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_escalator_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_escalator_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_history_poller_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_history_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_history_syncer_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_history_syncer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_housekeeper_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_housekeeper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_ipmi_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_ipmi_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_lld_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_lld_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_lld_worker_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_lld_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_preprocessing_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_preprocessing_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Preprocessing worker (TH: < 75 %)'), _('Proxy group manager (TH: < 75 %)'), _('Report manager (TH: < 75 %)'), _('Report writer (TH: < 75 %)'), _('Self-monitoring (TH: < 75 %)'), _('Service manager (TH: < 75 %)'), _('Task manager (TH: < 75 %)'), _('Timer (TH: < 75 %)'), _('Trigger housekeeper (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_preprocessing_worker_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_preprocessing_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_proxy_group_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_proxy_group_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_report_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_report_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_report_writer_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_report_writer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_self-monitoring_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_self-monitoring_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_service_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_service_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_task_manager_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_task_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_timer_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_timer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_internal_processes'][0]['process_trigger_housekeeper_avg_busy']))->addClass($data['zabbix_server_internal_processes'][0]['process_trigger_housekeeper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('Agent poller (TH: < 75 %)'), _('Browser poller (TH: < 75 %)'), _('HTTP agent poller (TH: < 75 %)'), _('HTTP poller (TH: < 75 %)'), _('ICMP pinger (TH: < 75 %)'), _('Internal poller (TH: < 75 %)'), _('IPMI poller (TH: < 75 %)'), _('Java poller (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_agent_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_agent_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_browser_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_browser_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_http_agent_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_http_agent_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_http_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_http_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_icmp_pinger_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_icmp_pinger_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_internal_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_internal_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_ipmi_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_ipmi_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_java_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_java_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem(_('Server data collector processes metric'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([_('ODBC poller (TH: < 75 %)'), _('poller (TH: < 75 %)'), _('Proxy poller (TH: < 75 %)'), _('SNMP poller (TH: < 75 %)'), _('SNMP trapper (TH: < 75 %)'), _('Trapper (TH: < 75 %)'), _('Unreachable poller (TH: < 75 %)'), _('VMware collector (TH: < 75 %)')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    ${"info_table_" . strval($n)}->addRow([
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_odbc_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_odbc_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_proxy_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_proxy_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_snmp_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_snmp_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_snmp_trapper_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_snmp_trapper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_trapper_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_trapper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_unreachable_poller_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_unreachable_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
        [(new CSpan($data['zabbix_server_collector_processes'][0]['process_vmware_collector_avg_busy']))->addClass($data['zabbix_server_collector_processes'][0]['process_vmware_collector_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
    ]);

    $html_page
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    if (count($data['zabbix_proxy_info']) > 0) {
        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Proxy name'), _('CPU util (TH: < 90 %)'), _('CPU IOwait (TH: < 30 %)'), _('CPU load'), _('Memory Util (TH: < 90 %)'), _('SWAP util (TH: < 30 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_performance'] as $zabbix_proxy_perf) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_perf['proxy_name'],
                [(new CSpan($zabbix_proxy_perf['cpu_util']))->addClass($zabbix_proxy_perf['cpu_util'] < 90 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_perf['cpu_iowait']))->addClass($zabbix_proxy_perf['cpu_iowait'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                $zabbix_proxy_perf['cpu_load'],
                [(new CSpan($zabbix_proxy_perf['mem_util']))->addClass($zabbix_proxy_perf['mem_util'] < 90 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_perf['swap_util']))->addClass($zabbix_proxy_perf['swap_util'] < 30 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        }

        $html_page
            ->addItem(_('Proxy performance metric'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

        $n++;

        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Name'), _('Configuration cache (TH: < 75 %)'), _('History index cache (TH: < 75 %)'), _('History write cache (TH: < 75 %)'), _('VMware cache (TH: < 75 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_cache'] as $zabbix_proxy_cache) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_cache['name'],
                [(new CSpan($zabbix_proxy_cache['rcache_buffer_pused']))->addClass($zabbix_proxy_cache['rcache_buffer_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_cache['wcache_index_pused']))->addClass($zabbix_proxy_cache['wcache_index_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_cache['wcache_history_pused']))->addClass($zabbix_proxy_cache['wcache_history_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_cache['vmware_buffer_pused']))->addClass($zabbix_proxy_cache['vmware_buffer_pused'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        };

        $html_page
            ->addItem(_('Proxy cache metric'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

        $n++;

        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Name'), _('Availability manager (TH: < 75 %)'), _('Configuration syncer (TH: < 75 %)'), _('Data sender (TH: < 75 %)'), _('Discovery manager (TH: < 75 %)'), _('Discovery worker (TH: < 75 %)'), _('History syncer (TH: < 75 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_internal_process'] as $zabbix_proxy_internal_process) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_internal_process['name'],
                [(new CSpan($zabbix_proxy_internal_process['process_availability_manager_avg_busy']))->addClass($zabbix_proxy_internal_process['process_availability_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_configuration_syncer_avg_busy']))->addClass($zabbix_proxy_internal_process['process_configuration_syncer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_data_sender_avg_busy']))->addClass($zabbix_proxy_internal_process['process_data_sender_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_discovery_manager_avg_busy']))->addClass($zabbix_proxy_internal_process['process_discovery_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_discovery_worker_avg_busy']))->addClass($zabbix_proxy_internal_process['process_discovery_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_history_syncer_avg_busy']))->addClass($zabbix_proxy_internal_process['process_history_syncer_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        };

        $html_page
            ->addItem(_('Proxy internal process metric'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

        $n++;

        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Name'), _('Housekeeper (TH: < 75 %)'), _('IPMI manager (TH: < 75 %)'), _('Preprocessing manager (TH: < 75 %)'), _('Preprocessing worker (TH: < 75 %)'), _('Self-monitoring (TH: < 75 %)'), _('Task manager (TH: < 75 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_internal_process'] as $zabbix_proxy_internal_process) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_internal_process['name'],
                [(new CSpan($zabbix_proxy_internal_process['process_housekeeper_avg_busy']))->addClass($zabbix_proxy_internal_process['process_housekeeper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_ipmi_manager_avg_busy']))->addClass($zabbix_proxy_internal_process['process_ipmi_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_preprocessing_manager_avg_busy']))->addClass($zabbix_proxy_internal_process['process_preprocessing_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_preprocessing_worker_avg_busy']))->addClass($zabbix_proxy_internal_process['process_preprocessing_worker_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_self_monitoring_avg_busy']))->addClass($zabbix_proxy_internal_process['process_self_monitoring_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_internal_process['process_task_manager_avg_busy']))->addClass($zabbix_proxy_internal_process['process_task_manager_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        };

        $html_page
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

        $n++;

        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Name'), _('Agent poller (TH: < 75 %)'), _('Browser poller (TH: < 75 %)'), _('HTTP agent poller (TH: < 75 %)'), _('HTTP poller (TH: < 75 %)'), _('ICMP pinger (TH: < 75 %)'), _('Internal poller (TH: < 75 %)'), _('IPMI poller (TH: < 75 %)'), _('Java poller (TH: < 75 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_collector_process'] as $zabbix_proxy_collector_process) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_collector_process['name'],
                [(new CSpan($zabbix_proxy_collector_process['process_agent_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_agent_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_browser_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_browser_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_http_agent_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_http_agent_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_http_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_http_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_icmp_pinger_avg_busy']))->addClass($zabbix_proxy_collector_process['process_icmp_pinger_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_internal_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_internal_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_ipmi_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_ipmi_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        };

        $html_page
            ->addItem(_('Proxy collector process metric'))
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER));

        $n++;

        ${"info_table_" . strval($n)} = (new CTableInfo())
            ->setHeader([_('Name'), _('ODBC poller (TH: < 75 %)'), _('Poller (TH: < 75 %)'), _('SNMP poller (TH: < 75 %)'), _('SNMP trapper (TH: < 75 %)'), _('Trapper (TH: < 75 %)'), _('Unreachable poller (TH: < 75 %)'), _('VMware collector (TH: < 75 %)')])
            ->setHeadingColumn(0)
            ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

        foreach ($data['zabbix_proxy_collector_process'] as $zabbix_proxy_collector_process) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_collector_process['name'],
                [(new CSpan($zabbix_proxy_collector_process['process_odbc_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_odbc_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_snmp_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_snmp_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_snmp_trapper_avg_busy']))->addClass($zabbix_proxy_collector_process['process_snmp_trapper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_trapper_avg_busy']))->addClass($zabbix_proxy_collector_process['process_trapper_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_unreachable_poller_avg_busy']))->addClass($zabbix_proxy_collector_process['process_unreachable_poller_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %'],
                [(new CSpan($zabbix_proxy_collector_process['process_vmware_collector_avg_busy']))->addClass($zabbix_proxy_collector_process['process_vmware_collector_avg_busy'] < 75 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED), ' %']
            ]);
        };

        $html_page
            ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
            ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

        $n++;
    }

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([(new CColHeader(_('Hostname')))->addStyle('width: 8%'), _('Log error')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    $pre = new CPre();
    if (count($data['zabbix_server_info']) > 0) {
        foreach ($data['zabbix_server_info'] as $zabbix_server_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_server_info['zabbix_server_hostname'],
                $pre->addItem([$zabbix_server_info['zabbix_server_log'], BR()])
            ]);
        }
    }

    if (count($data['zabbix_proxy_info']) > 0) {
        foreach ($data['zabbix_proxy_info'] as $zabbix_proxy_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_info['zabbix_proxy_hostname'],
                $pre->addItem([$zabbix_proxy_info['zabbix_proxy_log'], BR()])
            ]);
        }
    }

    $html_page
        ->addItem(_('Log error information'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->addItem((new CDiv('<br>'))->addClass(ZBX_STYLE_CONTAINER)->addClass(ZBX_STYLE_VISIBILITY_HIDDEN));

    $n++;

    ${"info_table_" . strval($n)} = (new CTableInfo())
        ->setHeader([(new CColHeader(_('Hostname')))->addStyle('width: 8%'), _('Configration')])
        ->setHeadingColumn(0)
        ->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

    $pre = new CPre();
    if (count($data['zabbix_server_info']) > 0) {
        foreach ($data['zabbix_server_info'] as $zabbix_server_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_server_info['zabbix_server_hostname'],
                $pre->addItem([$zabbix_server_info['zabbix_server_conf'], BR()])
            ]);
        }
    }

    if (count($data['zabbix_proxy_info']) > 0) {
        foreach ($data['zabbix_proxy_info'] as $zabbix_proxy_info) {
            ${"info_table_" . strval($n)}->addRow([
                $zabbix_proxy_info['zabbix_proxy_hostname'],
                $pre->addItem([$zabbix_proxy_info['zabbix_proxy_conf'], BR()])
            ]);
        }
    }

    $html_page
        ->addItem(_('Configuration information'))
        ->addItem((new CDiv(${"info_table_" . strval($n)}))->addClass(ZBX_STYLE_CONTAINER))
        ->show();
}
elseif ($data['host_sel'] == 0) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=0');
    exit;
}
elseif ($data['host_sel'] == 1) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=1');
    exit;
}
elseif ($data['host_sel'] == 2) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=2');
    exit;
}
elseif ($data['host_sel'] == 3) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=3');
    exit;
}
elseif ($data['host_sel'] == 4) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=4');
    exit;
}
elseif ($data['host_sel'] == 5) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=5');
    exit;
}
elseif ($data['host_sel'] == 6) {
    header('Location: ' . 'http://' . $_SERVER['HTTP_HOST'] . '/' . (new CUrl('zabbix.php'))->setArgument('action', 'inspection.report')->getUrl() . '&sel=6');
    exit;
}