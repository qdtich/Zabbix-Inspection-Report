<?php

declare(strict_types = 0);

namespace Modules\InspectionReport\Actions;

use CController, CControllerResponseData, CControllerResponseFatal, CRoleHelper, CSystemInfoHelper, CZabbixServer, CSettingsHelper, CSessionHelper, API, CArrayHelper;

class InspectionDetail extends CController {
    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'zabbix_server_ids' => 'array_db hosts.hostid',
            'zabbix_proxy_ids' => 'array_db hosts.hostid',
            'zabbix_database_ids' => 'array_db hosts.hostid',
            'inspection_cycle' => 'in 0,1,2,3'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS);
    }

    protected function doAction(): void {
        $data['result_analysis'] = [];
        $result_analysis = [
            'description' => '',
            'type' => '',
            'value' => '',
            'threshold' => '',
            'analysis' => '',
            'suggestion' => ''
        ];
        $zbx_proxy_state = 0;
        $mysql_buffer_pool_util = 0;
        $mysql_slave_io = 0;
        $mysql_slave_sql = 0;
        $server_cpu_util = 0;
        $server_cpu_iowait = 0;
        $server_mem_util = 0;
        $server_swap_util = 0;
        $proxy_cpu_util = 0;
        $proxy_cpu_iowait = 0;
        $proxy_mem_util = 0;
        $proxy_swap_util = 0;
        $zbx_proxy_cache_rcache_buffer_pused = 0;
        $zbx_proxy_cache_wcache_index_pused = 0;
        $zbx_proxy_cache_wcache_history_pused = 0;
        $zbx_proxy_cache_vmware_buffer_pused = 0;
        $zbx_proxy_internal_process_availability_manager_avg_busy = 0;
        $zbx_proxy_internal_process_configuration_syncer_avg_busy = 0;
        $zbx_proxy_internal_process_data_sender_avg_busy = 0;
        $zbx_proxy_internal_process_discovery_manager_avg_busy = 0;
        $zbx_proxy_internal_process_discovery_worker_avg_busy = 0;
        $zbx_proxy_internal_process_history_syncer_avg_busy = 0;
        $zbx_proxy_internal_process_housekeeper_avg_busy = 0;
        $zbx_proxy_internal_process_ipmi_manager_avg_busy = 0;
        $zbx_proxy_internal_process_preprocessing_manager_avg_busy = 0;
        $zbx_proxy_internal_process_preprocessing_worker_avg_busy = 0;
        $zbx_proxy_internal_process_self_monitoring_avg_busy = 0;
        $zbx_proxy_internal_process_task_manager_avg_busy = 0;
        $zbx_proxy_collector_process_agent_poller_avg_busy = 0;
        $zbx_proxy_collector_process_browser_poller_avg_busy = 0;
        $zbx_proxy_collector_process_http_agent_poller_avg_busy = 0;
        $zbx_proxy_collector_process_http_poller_avg_busy = 0;
        $zbx_proxy_collector_process_icmp_pinger_avg_busy = 0;
        $zbx_proxy_collector_process_internal_poller_avg_busy = 0;
        $zbx_proxy_collector_process_ipmi_poller_avg_busy = 0;
        $zbx_proxy_collector_process_java_poller_avg_busy = 0;
        $zbx_proxy_collector_process_odbc_poller_avg_busy = 0;
        $zbx_proxy_collector_process_poller_avg_busy = 0;
        $zbx_proxy_collector_process_snmp_poller_avg_busy = 0;

        $zabbix_server_ids[] = $this->getInput('zabbix_server_ids', []);
        $zabbix_proxy_ids[] = $this->getInput('zabbix_proxy_ids', []);
        $zabbix_database_ids[] = $this->getInput('zabbix_database_ids', []);

        $data['host_sel'] = -1;
        
        if ($zabbix_server_ids[0] == []) {
            $data['host_sel'] = 0;
        }
        else {
            foreach ($zabbix_server_ids[0] as $zabbix_server_id) {
                $zabbix_server_host_temp = API::Host()->get([
                    'output' => ['hostid'],
                    'hostids' => $zabbix_server_id,
                    'selectParentTemplates' => [
                        'name'
                    ]
                ]);

                foreach ($zabbix_server_host_temp[0]['parentTemplates'] as $data_host_pt) {
                    if (stristr($data_host_pt['name'], 'proxy')) {
                        $data['host_sel'] = 1;
                        break;
                    }
                    elseif (stristr($data_host_pt['name'], 'mysql')) {
                        $data['host_sel'] = 2;
                        break;
                    }
                }
            }
        }
        
        if ($zabbix_proxy_ids != []) {
            foreach ($zabbix_proxy_ids[0] as $zabbix_proxy_id) {
                $zabbix_proxy_host_temp = API::Host()->get([
                    'output' => ['hostid'],
                    'hostids' => $zabbix_proxy_id,
                    'selectParentTemplates' => [
                        'name'
                    ]
                ]);

                foreach ($zabbix_proxy_host_temp[0]['parentTemplates'] as $data_host_pt) {
                    if (stristr($data_host_pt['name'], 'server')) {
                        $data['host_sel'] = 3;
                        break;
                    }
                    elseif (stristr($data_host_pt['name'], 'mysql')) {
                        $data['host_sel'] = 4;
                        break;
                    }
                }
            }
        }
        
        if ($zabbix_database_ids != []) {
            foreach ($zabbix_database_ids[0] as $zabbix_database_id) {
                $zabbix_database_host_temp = API::Host()->get([
                    'output' => ['hostid'],
                    'hostids' => $zabbix_database_id,
                    'selectParentTemplates' => [
                        'name'
                    ]
                ]);

                foreach ($zabbix_database_host_temp[0]['parentTemplates'] as $data_host_pt) {
                    if (stristr($data_host_pt['name'], 'server')) {
                        $data['host_sel'] = 5;
                        break;
                    }
                    elseif (stristr($data_host_pt['name'], 'proxy')) {
                        $data['host_sel'] = 6;
                        break;
                    }
                }
            }
        }
        
        if ($data['host_sel'] == -1) {
            $data['zabbix_server_info'] = [];
            $zabbix_server_info = [
                'zabbix_server_cpu_num' => 0,
                'zabbix_server_mem_total_size' => 0,
                'zabbix_server_hostname' => '',
                'zabbix_server_uname' => '',
                'zabbix_server_version' => '',
                'zabbix_server_ip' => '',
                'zabbix_server_conf' => '',
                'zabbix_server_log' => '',
                'zabbix_server_nvps' => 0
            ];

            foreach ($zabbix_server_ids[0] as $zabbix_server_id) {
                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.cpu.num'
                    ]
                ]);
                $zabbix_server_cpu_num = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_cpu_num'] = $zabbix_server_cpu_num[0]['value'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'vm.memory.size[total]'
                    ]
                ]);
                $zabbix_server_mem_total_size = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_mem_total_size'] = round($zabbix_server_mem_total_size[0]['value']/1024/1024/1024,2);

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.hostname'
                    ]
                ]);
                $zabbix_server_hostname = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_hostname'] = $zabbix_server_hostname[0]['value'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.uname'
                    ]
                ]);
                $zabbix_server_uname = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_uname'] = $zabbix_server_uname[0]['value'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'version'
                    ]
                ]);
                $zabbix_server_version = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_version'] = $zabbix_server_version[0]['value'];

                $zabbix_server_ip = API::HostInterface()->get([
                    'output' => ['ip'],
                    'hostids' => $zabbix_server_id
                ]);
                $zabbix_server_info['zabbix_server_ip'] = $zabbix_server_ip[0]['ip'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'zabbix.server.conf'
                    ]
                ]);
                $zabbix_server_conf = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 4,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_conf'] = $zabbix_server_conf[0]['value'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'zabbix.server.log'
                    ]
                ]);
                $zabbix_server_log = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 4,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_server_info['zabbix_server_log'] = $zabbix_server_log[0]['value'];

                array_push($data['zabbix_server_info'], $zabbix_server_info);
            }

            $data['zabbix_system_info'] = CSystemInfoHelper::getData();

            if ($data['zabbix_system_info']['status']['items_count_not_supported'] > 100) {
                $result_analysis['description'] = 'Too many unsupported monitoring items.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = strval($data['zabbix_system_info']['status']['items_count_not_supported']);
                $result_analysis['threshold'] = '< 100';
                $result_analysis['analysis'] = 'Too many unsupported monitoring items can lead to unnecessary resource consumption by the Zabbix server/proxy and the monitored devices.';
                $result_analysis['suggestion'] = 'Optimize and reduce unsupported monitoring items, such as disable unnecessary monitoring items.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($data['zabbix_system_info']['status']['triggers_count_on'] > 25) {
                $result_analysis['description'] = 'Too many alerting issues or problems.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = strval($data['zabbix_system_info']['status']['triggers_count_on']);
                $result_analysis['threshold'] = '< 25';
                $result_analysis['analysis'] = 'Too many alarms have led to the loss of the significance of monitoring and have a negative impact on the stable operation of production systems.';
                $result_analysis['suggestion'] = 'Optimize and reduce alarms, such as disable unnecessary monitoring items or triggers.';
                array_push($data['result_analysis'], $result_analysis);
            }

            $data['zabbix_proxy_info'] = [];
            $zabbix_proxy_info = [
                'zabbix_proxy_cpu_num' => 0,
                'zabbix_proxy_mem_total_size' => 0,
                'zabbix_proxy_hostname' => '',
                'zabbix_proxy_uname' => '',
                'zabbix_proxy_version' => '',
                'zabbix_proxy_ip' => '',
                'zabbix_proxy_conf' => '',
                'zabbix_proxy_log' => '',
                'zabbix_proxy_hosts_count' => 0,
                'zabbix_proxy_items_count' => 0,
                'zabbix_proxy_mode' => 'Unknown',
                'zabbix_proxy_state' => '',
                'zabbix_proxy_nvps' => 0
            ];

            if ($this->hasInput('zabbix_proxy_ids', [])) {
                foreach ($zabbix_proxy_ids[0] as $zabbix_proxy_id) {
                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.cpu.num'
                        ]
                    ]);
                    $zabbix_proxy_cpu_num = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 3,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_cpu_num'] = $zabbix_proxy_cpu_num[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'vm.memory.size[total]'
                        ]
                    ]);
                    $zabbix_proxy_mem_total_size = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 3,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_mem_total_size'] = round($zabbix_proxy_mem_total_size[0]['value']/1024/1024/1024,2);

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.hostname'
                        ]
                    ]);
                    $zabbix_proxy_hostname = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 1,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_hostname'] = $zabbix_proxy_hostname[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.uname'
                        ]
                    ]);
                    $zabbix_proxy_uname = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 1,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_uname'] = $zabbix_proxy_uname[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'version'
                        ]
                    ]);
                    $zabbix_proxy_version = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 1,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_version'] = $zabbix_proxy_version[0]['value'];

                    $zabbix_proxy_ip = API::HostInterface()->get([
                        'output' => ['ip'],
                        'hostids' => $zabbix_proxy_id
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_ip'] = $zabbix_proxy_ip[0]['ip'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'zabbix.proxy.conf'
                        ]
                    ]);
                    $zabbix_proxy_conf = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 4,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_conf'] = $zabbix_proxy_conf[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'zabbix.proxy.log'
                        ]
                    ]);
                    $zabbix_proxy_log = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 4,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zabbix_proxy_info['zabbix_proxy_log'] = $zabbix_proxy_log[0]['value'];

                    $zabbix_server_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_server_ids[0][0],
                        'search' => [
                            'key_' => 'zabbix.proxy.hosts[' . $zabbix_proxy_hostname[0]['value'] . ']'
                        ]
                    ]);
                    $zabbix_proxy_host = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_server_itemid[0],
                        'history' => 3,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    if ($zabbix_proxy_host != []) {
                        $zabbix_proxy_info['zabbix_proxy_hosts_count'] = $zabbix_proxy_host[0]['value'];
                    }

                    $zabbix_server_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_server_ids[0][0],
                        'search' => [
                            'key_' => 'zabbix.proxy.items[' . $zabbix_proxy_hostname[0]['value'] . ']'
                        ]
                    ]);
                    $zabbix_proxy_item = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_server_itemid[0],
                        'history' => 3,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    if ($zabbix_proxy_item != []) {
                        $zabbix_proxy_info['zabbix_proxy_items_count'] = $zabbix_proxy_item[0]['value'];
                    }

                    $zabbix_proxy_state = API::Proxy()->get([
                        'output' => ['operating_mode', 'state'],
                        'filter' => [
                            'name' => $zabbix_proxy_hostname[0]['value']
                        ]
                    ]);
                    if ($zabbix_proxy_state[0]['operating_mode'] == 0) {
                        $zabbix_proxy_info['zabbix_proxy_mode'] = 'Active';
                    }
                    elseif ($zabbix_proxy_state[0]['operating_mode'] == 1) {
                        $zabbix_proxy_info['zabbix_proxy_mode'] = 'Passive';
                    }
                    if ($zabbix_proxy_state[0]['state'] == 0) {
                        $zabbix_proxy_info['zabbix_proxy_state'] = 'Unknown';
                        $zbx_proxy_state = 1;
                    }
                    elseif ($zabbix_proxy_state[0]['state'] == 1) {
                        $zabbix_proxy_info['zabbix_proxy_state'] = 'Offline';
                        $zbx_proxy_state = 2;
                    }
                    elseif ($zabbix_proxy_state[0]['state'] == 2) {
                        $zabbix_proxy_info['zabbix_proxy_state'] = 'Online';
                    }

                    $zabbix_server_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_server_ids[0][0],
                        'search' => [
                            'key_' => 'zabbix.proxy.requiredperformance[' . $zabbix_proxy_hostname[0]['value'] . ']'
                        ]
                    ]);
                    $zabbix_proxy_nvps = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_server_itemid[0],
                        'history' => 0,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    if ($zabbix_proxy_nvps != []) {
                        $zabbix_proxy_info['zabbix_proxy_nvps'] = round($zabbix_proxy_nvps[0]['value'], 2);
                    }

                    array_push($data['zabbix_proxy_info'], $zabbix_proxy_info);
                }
            }

            if ($zbx_proxy_state > 0) {
                $result_analysis['description'] = 'The proxy status is abnormal.';
                $result_analysis['type'] = 'proxy';
                if ($zbx_proxy_state == 1) {
                    $result_analysis['value'] = 'Unknown';
                }
                elseif ($zbx_proxy_state == 2) {
                    $result_analysis['value'] = 'Offline';
                }
                $result_analysis['threshold'] = 'Online';
                $result_analysis['analysis'] = 'Abnormal proxy status can cause the monitored device data to be unable to be obtained normally, thus losing its monitoring significance.';
                $result_analysis['suggestion'] = 'Check and repair the proxy status to restore it to "Online".';
                array_push($data['result_analysis'], $result_analysis);
            }

            $data['zabbix_database_info'] = [];
            $zabbix_database_info = [
                'zabbix_database_cpu_num' => 0,
                'zabbix_database_mem_total_size' => 0,
                'zabbix_database_hostname' => '',
                'zabbix_database_uname' => '',
                'zabbix_database_version' => '',
                'zabbix_database_ip' => '',
                'zabbix_database_dbver' => '',
                'zabbix_database_bpu' => 0,
                'zabbix_database_cir' => 0,
                'zabbix_database_csr' => 0,
                'zabbix_database_cps' => 0,
                'zabbix_database_used_conn' => 0,
                'zabbix_database_slave_io' => '',
                'zabbix_database_slave_sql' => ''
            ];

            foreach ($zabbix_database_ids[0] as $zabbix_database_id) {
                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'system.cpu.num'
                    ]
                ]);
                $zabbix_database_cpu_num = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_cpu_num'] = $zabbix_database_cpu_num[0]['value'];

                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'vm.memory.size[total]'
                    ]
                ]);
                $zabbix_database_mem_total_size = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_mem_total_size'] = round($zabbix_database_mem_total_size[0]['value']/1024/1024/1024,2);

                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'system.hostname'
                    ]
                ]);
                $zabbix_database_hostname = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_hostname'] = $zabbix_database_hostname[0]['value'];

                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'system.uname'
                    ]
                ]);
                $zabbix_database_uname = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_uname'] = $zabbix_database_uname[0]['value'];

                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'version'
                    ]
                ]);
                $zabbix_database_version = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_version'] = $zabbix_database_version[0]['value'];

                $zabbix_database_ip = API::HostInterface()->get([
                    'output' => ['ip'],
                    'hostids' => $zabbix_database_id
                ]);
                $zabbix_database_info['zabbix_database_ip'] = $zabbix_database_ip[0]['ip'];

                $zabbix_database_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_database_id,
                    'search' => [
                        'key_' => 'mysql.version'
                    ]
                ]);
                $zabbix_database_dbver = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_database_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zabbix_database_info['zabbix_database_dbver'] = $zabbix_database_dbver[0]['value'];

                if (stristr($zabbix_database_dbver[0]['value'], 'mysql') || stristr($zabbix_database_dbver[0]['value'], 'mariadb')) {
                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.buffer_pool_utilization'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_bpu = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_bpu = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_bpu = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_bpu = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_bpu'] = round(max($zabbix_database_bpu)['value_max'], 2);
                    if (round(max($zabbix_database_bpu)['value_max'], 2) < 80) {
                        $mysql_buffer_pool_util = round(max($zabbix_database_bpu)['value_max'], 2);
                    }

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.com_insert.rate'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_cir = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_cir = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_cir = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_cir = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_cir'] = round(max($zabbix_database_cir)['value_max'], 2);

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.com_select.rate'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_csr = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_csr = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_csr = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_csr = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_csr'] = round(max($zabbix_database_csr)['value_max'], 2);

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.connections.rate'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_cps = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_cps = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_cps = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_cps = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_cps'] = round(max($zabbix_database_cps)['value_max'], 2);

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.max_used_connections'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_used_conn = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_used_conn = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_used_conn = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_used_conn = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_database_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_used_conn'] = round(max($zabbix_database_used_conn)['value_max'], 2);

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.replication.slave_io_running'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_slave_io = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_slave_io = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_slave_io = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_slave_io = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_slave_io'] = $zabbix_database_slave_io[0]['value'];
                    if (!stristr($zabbix_database_slave_io[0]['value'], 'yes')) {
                        $mysql_slave_io = 1;
                    }

                    $zabbix_database_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_database_id,
                        'search' => [
                            'key_' => 'mysql.replication.slave_sql_running'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_database_slave_sql = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_database_slave_sql = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_database_slave_sql = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_database_slave_sql = API::History()->get([
                            'output' => ['value', 'clock'],
                            'itemids' => $zabbix_database_itemid[0],
                            'history' => 1,
                            'sortfield' => 'clock',
                            'sortorder' => 'DESC',
                            'limit' => 1
                        ]);
                    }
                    $zabbix_database_info['zabbix_database_slave_sql'] = $zabbix_database_slave_sql[0]['value'];
                    if (!stristr($zabbix_database_slave_sql[0]['value'], 'yes')) {
                        $mysql_slave_sql = 1;
                    }
                }

                array_push($data['zabbix_database_info'], $zabbix_database_info);
            }

            if ($mysql_buffer_pool_util < 80) {
                $result_analysis['description'] = 'The MySQL\'s Buffer Pool Utilization is too Low.';
                $result_analysis['type'] = 'database';
                $result_analysis['value'] = strval($mysql_buffer_pool_util);
                $result_analysis['threshold'] = '> 80';
                $result_analysis['analysis'] = 'MySQL\'s Buffer Pool is used to cache data and improve system performance. For micro monitoring systems, this issue can be ignored, but for medium or large monitoring systems, it needs to be given special attention.';
                $result_analysis['suggestion'] = 'It is recommended that the database DBA investigate, optimize, and solve this issue.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($mysql_slave_io == 1) {
                $result_analysis['description'] = 'The MySQL Slave IO process is not running.';
                $result_analysis['type'] = 'database';
                $result_analysis['value'] = 'No';
                $result_analysis['threshold'] = 'Yes (If MySQL has enabled replication or HA)';
                $result_analysis['analysis'] = 'The MySQL Slave IO process is used for data replication between database nodes. If it is not running, the replication function will has a malfunction.';
                $result_analysis['suggestion'] = 'It is recommended that the database DBA investigate and solve this issue.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($mysql_slave_sql == 1) {
                $result_analysis['description'] = 'The MySQL Slave SQL process is not running.';
                $result_analysis['type'] = 'database';
                $result_analysis['value'] = 'No';
                $result_analysis['threshold'] = 'Yes (If MySQL has enabled replication or HA)';
                $result_analysis['analysis'] = 'The MySQL Slave SQL process is used for data replication between database nodes. If it is not running, the replication function will has a malfunction.';
                $result_analysis['suggestion'] = 'It is recommended that the database DBA investigate and solve this issue.';
                array_push($data['result_analysis'], $result_analysis);
            }

            // Obtain information about the active server
            $zabbix_ha_info = API::HaNode()->get([
                'output' => ['address', 'port', 'ha_nodeid'],
                'preservekeys' => true,
                'filter' => [
                    'status' => '3'
                ]
            ]);

            foreach ($zabbix_ha_info as $zabbix_ha_master) {
                $zabbix_ha_master_address = $zabbix_ha_master['address'];
                $zabbix_ha_master_port = $zabbix_ha_master['port'];
                $zabbix_ha_master_node_id = $zabbix_ha_master['ha_nodeid'];
            }

            $data['zabbix_queue'] = [
                'queue' => 0,
                'queue_over_10m' => 0,
                'discovery_queue' => 0,
                'lld_queue' => 0,
                'preprocessing_queue' => 0
            ];

            $zabbix_server_hostid = API::Host()->get([
                'output' => ['hostid'],
                'filter' => [
                    'host' => $zabbix_ha_master_address
                ]
            ]);

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'zabbix.stats[' . $zabbix_ha_master_address . ',' . $zabbix_ha_master_port . ',queue]'
                ]
            ]);
            if ($zabbix_server_itemid != []) {
                $data['zabbix_queue']['queue'] = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ])[0]['value'];
            }
            
            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'zabbix.stats[' . $zabbix_ha_master_address . ',' . $zabbix_ha_master_port . ',queue,10m]'
                ]
            ]);
            if ($zabbix_server_itemid != []) {
                $data['zabbix_queue']['queue_over_10m'] = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ])[0]['value'];

                if ($data['zabbix_queue']['queue_over_10m'] > 100) {
                    $result_analysis['description'] = 'The queue is too long.';
                    $result_analysis['type'] = 'queue';
                    $result_analysis['value'] = $data['zabbix_queue']['queue_over_10m'];
                    $result_analysis['threshold'] = '< 100';
                    $result_analysis['analysis'] = 'The long queue prevents Zabbix from processing monitoring items in a timely manner, affecting the timely detection and alerting of abnormal indicators.';
                    $result_analysis['suggestion'] = '1. Stop non essential monitoring items: Reducing unnecessary monitoring items can lower system load; 2. Increase monitoring interval: Increase the time interval of monitoring items appropriately to reduce frequent data collection; 3. Increasing cache size: Increasing CacheSize, HistoryCacheSize, and ValueSize can improve the system\'s processing power; 4. Convert monitoring items to active: Convert passive monitoring items to active and add StartTrappers parameter;5. Ensure clock synchronization: Ensure that the clocks of the server, proxy, and agent machines are consistent to avoid data delays caused by time differences.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'discovery_queue'
                ]
            ]);
            if ($zabbix_server_itemid != []) {
                $data['zabbix_queue']['discovery_queue'] = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ])[0]['value'];

                if ($data['zabbix_queue']['discovery_queue'] > 30) {
                    $result_analysis['description'] = 'The discovery queue is too long.';
                    $result_analysis['type'] = 'queue';
                    $result_analysis['value'] = $data['zabbix_queue']['discovery_queue'];
                    $result_analysis['threshold'] = '< 30';
                    $result_analysis['analysis'] = 'The long queue prevents Zabbix from processing monitoring items in a timely manner, affecting the timely detection and alerting of abnormal indicators.';
                    $result_analysis['suggestion'] = '1. Stop non essential monitoring items: Reducing unnecessary monitoring items can lower system load; 2. Increasing cache size: Increasing CacheSize, HistoryCacheSize, and ValueSize can improve the system\'s processing power; 3. Ensure clock synchronization: Ensure that the clocks of the server, proxy, and agent machines are consistent to avoid data delays caused by time differences; 4. Add StartDiscoverers parameter.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'lld_queue'
                ]
            ]);
            if ($zabbix_server_itemid != []) {
                $data['zabbix_queue']['lld_queue'] = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ])[0]['value'];

                if ($data['zabbix_queue']['lld_queue'] > 30) {
                    $result_analysis['description'] = 'The discovery queue is too long.';
                    $result_analysis['type'] = 'queue';
                    $result_analysis['value'] = $data['zabbix_queue']['lld_queue'];
                    $result_analysis['threshold'] = '< 30';
                    $result_analysis['analysis'] = 'The long queue prevents Zabbix from processing monitoring items in a timely manner, affecting the timely detection and alerting of abnormal indicators.';
                    $result_analysis['suggestion'] = '1. Stop non essential monitoring items: Reducing unnecessary monitoring items can lower system load; 2. Increasing cache size: Increasing CacheSize, HistoryCacheSize, and ValueSize can improve the system\'s processing power; 3. Ensure clock synchronization: Ensure that the clocks of the server, proxy, and agent machines are consistent to avoid data delays caused by time differences; 4. Add StartLLDProcessors parameter.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'preprocessing_queue'
                ]
            ]);
            if ($zabbix_server_itemid != []) {
                $data['zabbix_queue']['preprocessing_queue'] = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 3,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ])[0]['value'];

                if ($data['zabbix_queue']['preprocessing_queue'] > 30) {
                    $result_analysis['description'] = 'The discovery queue is too long.';
                    $result_analysis['type'] = 'queue';
                    $result_analysis['value'] = $data['zabbix_queue']['preprocessing_queue'];
                    $result_analysis['threshold'] = '< 30';
                    $result_analysis['analysis'] = 'The long queue prevents Zabbix from processing monitoring items in a timely manner, affecting the timely detection and alerting of abnormal indicators.';
                    $result_analysis['suggestion'] = '1. Stop non essential monitoring items: Reducing unnecessary monitoring items can lower system load; 2. Increasing cache size: Increasing CacheSize, HistoryCacheSize, and ValueSize can improve the system\'s processing power; 3. Add StartPreprocessors parameter.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_ov = new CZabbixServer($zabbix_ha_master_address, $zabbix_ha_master_port,
                timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
                timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
            );

            $queue_overview_data = $zabbix_server_ov->getQueue(CZabbixServer::QUEUE_OVERVIEW, CSessionHelper::getId(), CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));
            
            $data['zabbix_queue_overview'] = [
                'zabbix_agent' => 0,
                'zabbix_agent_active' => 0,
                'simple_check' => 0,
                'snmp_agent' => 0,
                'database_monitor' => 0,
                'http_agent' => 0,
                'calculated' => 0,
                'script' => 0,
                'browser' => 0
            ];

            if (count($queue_overview_data) > 0) {
                foreach ($queue_overview_data as $queue_info) {
                    if ($queue_info['itemtype'] == 0) {
                        $data['zabbix_queue_overview']['zabbix_agent'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 1) {
                        $data['zabbix_queue_overview']['zabbix_agent_active'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 2) {
                        $data['zabbix_queue_overview']['simple_check'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 3) {
                        $data['zabbix_queue_overview']['snmp_agent'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 4) {
                        $data['zabbix_queue_overview']['database_monitor'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 5) {
                        $data['zabbix_queue_overview']['http_agent'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 6) {
                        $data['zabbix_queue_overview']['calculated'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 7) {
                        $data['zabbix_queue_overview']['script'] = $queue_info['delay600'];
                    }
                    elseif ($queue_info['itemtype'] == 8) {
                        $data['zabbix_queue_overview']['browser'] = $queue_info['delay600'];
                    }
                }
            }

            $zabbix_server_dt = new CZabbixServer($zabbix_ha_master_address, $zabbix_ha_master_port,
                timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
                timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
            );

            $queue_detail_data = $zabbix_server_dt->getQueue(CZabbixServer::QUEUE_OVERVIEW, CSessionHelper::getId(), CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));

            $data['zabbix_queue_detail'] = [
                'items' => [],
			    'hosts' => [],
			    'queue_detail_data' => [],
			    'proxies' => []
            ];

            if (count($queue_detail_data) > 0) {
                $queue_detail_data = array_column($queue_detail_data, null, 'itemid');
                $items = CArrayHelper::renameObjectsKeys(API::Item()->get([
                    'output' => ['hostid', 'name_resolved'],
                    'selectHosts' => ['name'],
                    'itemids' => array_keys($queue_detail_data),
                    'webitems' => true,
                    'preservekeys' => true
                ]), ['name_resolved' => 'name']);

                if (count($queue_detail_data) != count($items)) {
                    $items += API::DiscoveryRule()->get([
                        'output' => ['hostid', 'name'],
                        'selectHosts' => ['name'],
                        'itemids' => array_diff(array_keys($queue_detail_data), array_keys($items)),
                        'preservekeys' => true
                    ]);
                }

                $hosts = API::Host()->get([
                    'output' => ['proxyid'],
                    'hostids' => array_unique(array_column($items, 'hostid')),
                    'preservekeys' => true
                ]);

                $proxyids = array_flip(array_column($hosts, 'proxyid'));
                unset($proxyids[0]);

                $proxies = $proxyids
                    ? API::Proxy()->get([
                        'output' => ['proxyid', 'name'],
                        'proxyids' => array_keys($proxyids),
                        'preservekeys' => true
                    ])
                    : [];
                
                $data['zabbix_queue_detail']['items'] = $items;
                $data['zabbix_queue_detail']['hosts'] = $hosts;
                $data['zabbix_queue_detail']['proxies'] = $proxies;
                $data['zabbix_queue_detail']['queue_detail_data'] = $queue_detail_data;
            }

            $trigger_ids = API::Trigger()->get([
                'output' => ['description', 'priority'],
                'filter' => [
                    'value' => 1
                ],
                'sortfield' => 'priority',
                'sortorder' => 'DESC',
                'limit' => 10
            ]);

            $data['zabbix_problem_info'] = [];
            foreach ($trigger_ids as $trigger_id) {
                $data['trigger_tmp'] = [];
                $trigger_tmp = API::Host()->get([
                    'output' => ['name'],
                    'triggerids' => $trigger_id['triggerid']
                ]);
                $data['trigger_tmp']['name'] = $trigger_tmp[0]['name'];
                $data['trigger_tmp']['description'] = $trigger_id['description'];
                if ($trigger_id['priority'] == 0) {
                    $data['trigger_tmp']['priority'] = 'not classified';
                }
                elseif ($trigger_id['priority'] == 1) {
                    $data['trigger_tmp']['priority'] = 'information';
                }
                elseif ($trigger_id['priority'] == 2) {
                    $data['trigger_tmp']['priority'] = 'warning';
                }
                elseif ($trigger_id['priority'] == 3) {
                    $data['trigger_tmp']['priority'] = 'average';
                }
                elseif ($trigger_id['priority'] == 4) {
                    $data['trigger_tmp']['priority'] = 'high';
                }
                elseif ($trigger_id['priority'] == 5) {
                    $data['trigger_tmp']['priority'] = 'disaster';
                }
                array_push($data['zabbix_problem_info'], $data['trigger_tmp']);
            }

            $data['zabbix_server_performance'] = [];
            $zbx_server_perf = [
                'server_name' => '',
                'cpu_util' => 0,
                'cpu_iowait' => 0,
                'cpu_load' => 0,
                'mem_util' => 0,
                'swap_util' => 0
            ];

            foreach ($zabbix_server_ids[0] as $zabbix_server_id) {
                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.hostname'
                    ]
                ]);
                $zabbix_server_hostname = API::History()->get([
                    'output' => ['value', 'clock'],
                    'itemids' => $zabbix_server_itemid[0],
                    'history' => 1,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                $zbx_server_perf['server_name'] = $zabbix_server_hostname[0]['value'];

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.cpu.util'
                    ]
                ]);
                if ($this->getInput('inspection_cycle') == 0) {
                    $zabbix_server_cpu_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-01-01'),
                        'time_till' => strtotime(date("Y") . '-03-31')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 1) {
                    $zabbix_server_cpu_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-04-01'),
                        'time_till' => strtotime(date("Y") . '-06-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 2) {
                    $zabbix_server_cpu_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-07-01'),
                        'time_till' => strtotime(date("Y") . '-09-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 3) {
                    $zabbix_server_cpu_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-10-01'),
                        'time_till' => strtotime(date("Y") . '-12-31')
                    ]);
                }
                $zbx_server_perf['cpu_util'] = round(max($zabbix_server_cpu_util)['value_max'], 2);
                if ($zbx_server_perf['cpu_util'] > 90) {
                    $server_cpu_util = $zbx_server_perf['cpu_util'];
                }

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.cpu.util[,iowait]'
                    ]
                ]);
                if ($this->getInput('inspection_cycle') == 0) {
                    $zabbix_server_cpu_iowait = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-01-01'),
                        'time_till' => strtotime(date("Y") . '-03-31')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 1) {
                    $zabbix_server_cpu_iowait = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-04-01'),
                        'time_till' => strtotime(date("Y") . '-06-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 2) {
                    $zabbix_server_cpu_iowait = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-07-01'),
                        'time_till' => strtotime(date("Y") . '-09-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 3) {
                    $zabbix_server_cpu_iowait = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-10-01'),
                        'time_till' => strtotime(date("Y") . '-12-31')
                    ]);
                }
                $zbx_server_perf['cpu_iowait'] = round(max($zabbix_server_cpu_iowait)['value_max'], 2);
                if ($zbx_server_perf['cpu_iowait'] > 90) {
                    $server_cpu_iowait = $zbx_server_perf['cpu_iowait'];
                }

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.cpu.load[all,avg1]'
                    ]
                ]);
                if ($this->getInput('inspection_cycle') == 0) {
                    $zabbix_server_cpu_load = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-01-01'),
                        'time_till' => strtotime(date("Y") . '-03-31')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 1) {
                    $zabbix_server_cpu_load = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-04-01'),
                        'time_till' => strtotime(date("Y") . '-06-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 2) {
                    $zabbix_server_cpu_load = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-07-01'),
                        'time_till' => strtotime(date("Y") . '-09-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 3) {
                    $zabbix_server_cpu_load = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-10-01'),
                        'time_till' => strtotime(date("Y") . '-12-31')
                    ]);
                }
                $zbx_server_perf['cpu_load'] = round(max($zabbix_server_cpu_load)['value_max'], 2);

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'vm.memory.utilization'
                    ]
                ]);
                if ($this->getInput('inspection_cycle') == 0) {
                    $zabbix_server_mem_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-01-01'),
                        'time_till' => strtotime(date("Y") . '-03-31')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 1) {
                    $zabbix_server_mem_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-04-01'),
                        'time_till' => strtotime(date("Y") . '-06-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 2) {
                    $zabbix_server_mem_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-07-01'),
                        'time_till' => strtotime(date("Y") . '-09-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 3) {
                    $zabbix_server_mem_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-10-01'),
                        'time_till' => strtotime(date("Y") . '-12-31')
                    ]);
                }
                $zbx_server_perf['mem_util'] = round(max($zabbix_server_mem_util)['value_max'], 2);
                if ($zbx_server_perf['mem_util'] > 90) {
                    $server_mem_util = $zbx_server_perf['mem_util'];
                }

                $zabbix_server_itemid = API::Item()->get([
                    'output' => ['itemid'],
                    'hostids' => $zabbix_server_id,
                    'search' => [
                        'key_' => 'system.swap.size[,pfree]'
                    ]
                ]);
                if ($this->getInput('inspection_cycle') == 0) {
                    $zabbix_server_swap_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-01-01'),
                        'time_till' => strtotime(date("Y") . '-03-31')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 1) {
                    $zabbix_server_swap_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-04-01'),
                        'time_till' => strtotime(date("Y") . '-06-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 2) {
                    $zabbix_server_swap_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-07-01'),
                        'time_till' => strtotime(date("Y") . '-09-30')
                    ]);
                }
                elseif ($this->getInput('inspection_cycle') == 3) {
                    $zabbix_server_swap_util = API::Trend()->get([
                        'output' => ['value_max'],
                        'itemids' => $zabbix_server_itemid[0],
                        'time_from' => strtotime(date("Y") . '-10-01'),
                        'time_till' => strtotime(date("Y") . '-12-31')
                    ]);
                }
                $zbx_server_perf['swap_util'] = round(100 - max($zabbix_server_swap_util)['value_max'], 2);
                if ($zbx_server_perf['swap_util'] > 90) {
                    $server_swap_util = $zbx_server_perf['swap_util'];
                }

                array_push($data['zabbix_server_performance'], $zbx_server_perf);
            };
            if ($server_cpu_util > 90) {
                $result_analysis['description'] = 'The CPU utilization of Zabbix server is too high.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = $server_cpu_util;
                $result_analysis['threshold'] = '< 90';
                $result_analysis['analysis'] = 'Excessive CPU usage can cause system lag, slow response, and even Zabbix service crashes.';
                $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Increase hardware resources.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($server_cpu_iowait > 90) {
                $result_analysis['description'] = 'The CPU IOwait utilization of Zabbix server is too high.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = $server_cpu_iowait;
                $result_analysis['threshold'] = '< 90';
                $result_analysis['analysis'] = 'Excessive CPU usage can cause system lag, slow response, and even Zabbix service crashes.';
                $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Optimize disk performance, such as replace a better performing SSD hard drive.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($server_mem_util > 90) {
                $result_analysis['description'] = 'The memory utilization of Zabbix server is too high.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = $server_mem_util;
                $result_analysis['threshold'] = '< 90';
                $result_analysis['analysis'] = 'Excessive memory usage can cause system lag, slow response, and even Zabbix service crashes.';
                $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Increase hardware memory capacity.';
                array_push($data['result_analysis'], $result_analysis);
            }

            if ($server_swap_util > 90) {
                $result_analysis['description'] = 'The SWAP utilization of Zabbix server is too high.';
                $result_analysis['type'] = 'system';
                $result_analysis['value'] = $server_swap_util;
                $result_analysis['threshold'] = '< 90';
                $result_analysis['analysis'] = 'Excessive SWAP usage can cause system lag, slow response, and even Zabbix service crashes.';
                $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Adjust the swappiness parameter; 4. Increase hardware memory capacity.';
                array_push($data['result_analysis'], $result_analysis);
            }

            $data['zabbix_server_cache'] = [];
            $zbx_server_cache = [
                'rcache_buffer_pused' => 0,
                'wcache_index_pused' => 0,
                'wcache_history_pused' => 0,
                'tcache_pmisses' => 0,
                'tcache_pitems' => 0,
                'wcache_trend_pused' => 0,
                'vcache_buffer_pused' => 0,
                'vcache_cache_hits' => 0,
                'vcache_cache_misses' => 0,
                'vmware_buffer_pused' => 0
            ];

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'rcache.buffer.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_rcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_rcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_rcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_rcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_rcache_buffer_pused != []) {
                $zbx_server_cache['rcache_buffer_pused'] = round(max($zabbix_server_rcache_buffer_pused)['value_max'], 2);

                if ($zbx_server_cache['rcache_buffer_pused'] > 75) {
                    $result_analysis['description'] = 'The configuration cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['rcache_buffer_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of configuration cache can result in the inability to properly store host, item, and trigger data.';
                    $result_analysis['suggestion'] = 'Add the CacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'wcache.index.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_wcache_index_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_wcache_index_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_wcache_index_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_wcache_index_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_wcache_index_pused != []) {
                $zbx_server_cache['wcache_index_pused'] = round(max($zabbix_server_wcache_index_pused)['value_max'], 2);

                if ($zbx_server_cache['wcache_index_pused'] > 75) {
                    $result_analysis['description'] = 'The history index cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['wcache_index_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of history index cache can result in the inability to properly index history cache.';
                    $result_analysis['suggestion'] = 'Add the HistoryIndexCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'wcache.history.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_wcache_history_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_wcache_history_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_wcache_history_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_wcache_history_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_wcache_history_pused != []) {
                $zbx_server_cache['wcache_history_pused'] = round(max($zabbix_server_wcache_history_pused)['value_max'], 2);

                if ($zbx_server_cache['wcache_history_pused'] > 75) {
                    $result_analysis['description'] = 'The history write cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['wcache_history_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of history write cache can result in the inability to properly store history cache.';
                    $result_analysis['suggestion'] = 'Add the HistoryCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'tcache.pmisses'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_tcache_pmisses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_tcache_pmisses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_tcache_pmisses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_tcache_pmisses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_tcache_pmisses != []) {
                $zbx_server_cache['tcache_pmisses'] = round(max($zabbix_server_tcache_pmisses)['value_max'], 2);

                if ($zbx_server_cache['tcache_pmisses'] > 30) {
                    $result_analysis['description'] = 'The trend function cache miss utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['tcache_pmisses'];
                    $result_analysis['threshold'] = '< 30';
                    $result_analysis['analysis'] = 'Excessive usage of trend function cache miss can result in the inability to properly cache calculated trend function data.';
                    $result_analysis['suggestion'] = 'Add the TrendFunctionCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'tcache.pitems'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_tcache_pitems = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_tcache_pitems = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_tcache_pitems = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_tcache_pitems = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_tcache_pitems != []) {
                $zbx_server_cache['tcache_pitems'] = round(max($zabbix_server_tcache_pitems)['value_max'], 2);

                if ($zbx_server_cache['tcache_pitems'] > 75) {
                    $result_analysis['description'] = 'The trend function cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['tcache_pitems'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of trend function cache can result in the inability to properly cache calculated trend function data.';
                    $result_analysis['suggestion'] = 'Add the TrendFunctionCacheSize parameter in zabbix_server.conf. A low percentage most likely means that the cache size can be reduced.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'wcache.trend.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_wcache_trend_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_wcache_trend_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_wcache_trend_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_wcache_trend_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_wcache_trend_pused != []) {
                $zbx_server_cache['wcache_trend_pused'] = round(max($zabbix_server_wcache_trend_pused)['value_max'], 2);

                if ($zbx_server_cache['wcache_trend_pused'] > 75) {
                    $result_analysis['description'] = 'The trend write cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['wcache_trend_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of trend write cache can result in the inability to properly store trends data.';
                    $result_analysis['suggestion'] = 'Add the TrendCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'vcache.buffer.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_vcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_vcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_vcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_vcache_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_vcache_buffer_pused != []) {
                $zbx_server_cache['vcache_buffer_pused'] = round(max($zabbix_server_vcache_buffer_pused)['value_max'], 2);

                if ($zbx_server_cache['vcache_buffer_pused'] > 75) {
                    $result_analysis['description'] = 'The value cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['vcache_buffer_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of value cache can result in the inability to properly cache item history data requests.';
                    $result_analysis['suggestion'] = 'Add the ValueCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'vcache.cache.hits'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_vcache_cache_hits = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_vcache_cache_hits = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_vcache_cache_hits = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_vcache_cache_hits = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_vcache_cache_hits != []) {
                $zbx_server_cache['vcache_cache_hits'] = round(max($zabbix_server_vcache_cache_hits)['value_max'], 2);
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'vcache.cache.misses'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_vcache_cache_misses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_vcache_cache_misses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_vcache_cache_misses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_vcache_cache_misses = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_vcache_cache_misses != []) {
                $zbx_server_cache['vcache_cache_misses'] = round(max($zabbix_server_vcache_cache_misses)['value_max'], 2);
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'vmware.buffer.pused'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_vmware_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_vmware_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_vmware_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_vmware_buffer_pused = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }
            
            if ($zabbix_server_vmware_buffer_pused != []) {
                $zbx_server_cache['vmware_buffer_pused'] = round(max($zabbix_server_vmware_buffer_pused)['value_max'], 2);

                if ($zbx_server_cache['vmware_buffer_pused'] > 75) {
                    $result_analysis['description'] = 'The vmware cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_server_cache['vmware_buffer_pused'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of vmware cache can result in the inability to properly store vmware data.';
                    $result_analysis['suggestion'] = 'Add the VMwareCacheSize parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $data['zabbix_server_cache'] = $zbx_server_cache;

            $data['zabbix_server_internal_processes'] = [];
            $zbx_server_internal_processes = [
                'process_alerter_avg_busy' => 0,
                'process_alert_manager_avg_busy' => 0,
                'process_alert_syncer_avg_busy' => 0,
                'process_availability_manager_avg_busy' => 0,
                'process_configuration_syncer_avg_busy' => 0,
                'process_configuration_syncer_worker_avg_busy' => 0,
                'process_connector_manager_avg_busy' => 0,
                'process_connector_worker_avg_busy' => 0,
                'process_discovery_manager_avg_busy' => 0,
                'process_discovery_worker_avg_busy' => 0,
                'process_escalator_avg_busy' => 0,
                'process_history_poller_avg_busy' => 0,
                'process_history_syncer_avg_busy' => 0,
                'process_housekeeper_avg_busy' => 0,
                'process_ipmi_manager_avg_busy' => 0,
                'process_lld_manager_avg_busy' => 0,
                'process_lld_worker_avg_busy' => 0,
                'process_preprocessing_manager_avg_busy' => 0,
                'process_preprocessing_worker_avg_busy' => 0,
                'process_proxy_group_manager_avg_busy' => 0,
                'process_report_manager_avg_busy' => 0,
                'process_report_writer_avg_busy' => 0,
                'process_self-monitoring_avg_busy' => 0,
                'process_service_manager_avg_busy' => 0,
                'process_task_manager_avg_busy' => 0,
                'process_timer_avg_busy' => 0,
                'process_trigger_housekeeper_avg_busy' => 0
            ];

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.alerter.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_alerter_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_alerter_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_alerter_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_alerter_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_alerter_avg_busy != []) {
                $zbx_server_internal_processes['process_alerter_avg_busy'] = round(max($zabbix_server_process_alerter_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_alerter_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of alerter internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_alerter_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of alerter internal processes can result in the inability to properly process alert issuse.';
                    $result_analysis['suggestion'] = 'Add the StartAlerters parameter in zabbix_server.conf, or optimize and reduce the number of alarm events.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.alert_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_alert_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_alert_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_alert_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_alert_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_alert_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_alert_manager_avg_busy'] = round(max($zabbix_server_process_alert_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_alert_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of alert manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_alert_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of alert manager internal processes can result in the inability to properly process alert issuse.';
                    $result_analysis['suggestion'] = 'Add the StartAlerters parameter in zabbix_server.conf, or optimize and reduce the number of alarm events.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.alert_syncer.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_alert_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_alert_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_alert_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_alert_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_alert_syncer_avg_busy != []) {
                $zbx_server_internal_processes['process_alert_syncer_avg_busy'] = round(max($zabbix_server_process_alert_syncer_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_alert_syncer_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of alert syncer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_alert_syncer_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of alert syncer internal processes can result in the inability to properly process alert issuse.';
                    $result_analysis['suggestion'] = 'Add the StartAlerters parameter in zabbix_server.conf, or optimize and reduce the number of alarm events.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.availability_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_availability_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_availability_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_availability_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_availability_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_availability_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_availability_manager_avg_busy'] = round(max($zabbix_server_process_availability_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_availability_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of availability manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_availability_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of availability manager internal processes can result in the inability to properly process alert issuse.';
                    $result_analysis['suggestion'] = 'Add the StartAlerters parameter in zabbix_server.conf, or optimize and reduce the number of alarm events.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.configuration_syncer.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_configuration_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_configuration_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_configuration_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_configuration_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_configuration_syncer_avg_busy != []) {
                $zbx_server_internal_processes['process_configuration_syncer_avg_busy'] = round(max($zabbix_server_process_configuration_syncer_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_configuration_syncer_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of configuration syncer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_configuration_syncer_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of configuration syncer internal processes will respond to the synchronization of configuration information between the server and proxy.';
                    $result_analysis['suggestion'] = '1. Add the ProxyConfigFrequency and ProxyDataFrequency parameters in zabbix_server.conf, The parameters is used only for proxies in the passive mode; 2. Increase the number of proxy and allocate the number of hosts and items monitored by proxy reasonably; 3. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.configuration_syncer_worker.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_configuration_syncer_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_configuration_syncer_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_configuration_syncer_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_configuration_syncer_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_configuration_syncer_worker_avg_busy != []) {
                $zbx_server_internal_processes['process_configuration_syncer_worker_avg_busy'] = round(max($zabbix_server_process_configuration_syncer_worker_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_configuration_syncer_worker_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of configuration syncer worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_configuration_syncer_worker_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of configuration syncer worker internal processes will respond to the synchronization of configuration information between the server and proxy.';
                    $result_analysis['suggestion'] = '1. Add the ProxyConfigFrequency parameter and ProxyDataFrequency in zabbix_server.conf, The parameters is used only for proxies in the passive mode; 2. Increase the number of proxy and allocate the number of hosts and items monitored by proxy reasonably; 3. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.connector_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_connector_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_connector_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_connector_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_connector_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_connector_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_connector_manager_avg_busy'] = round(max($zabbix_server_process_connector_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_connector_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of connector manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_connector_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of connector manager internal processes will respond to the synchronization of data between the server and connector.';
                    $result_analysis['suggestion'] = '1. Add the StartConnectors parameter in zabbix_server.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.connector_worker.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_connector_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_connector_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_connector_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_connector_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_connector_worker_avg_busy != []) {
                $zbx_server_internal_processes['process_connector_worker_avg_busy'] = round(max($zabbix_server_process_connector_worker_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_connector_worker_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of connector worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_connector_worker_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of connector worker internal processes will respond to the synchronization of data between the server and connector.';
                    $result_analysis['suggestion'] = '1. Add the StartConnectors parameter in zabbix_server.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.discovery_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_discovery_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_discovery_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_discovery_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_discovery_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_discovery_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_discovery_manager_avg_busy'] = round(max($zabbix_server_process_discovery_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_discovery_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of discovery manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_discovery_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of discovery manager internal processes can affect the effectiveness of discovery.';
                    $result_analysis['suggestion'] = '1. Add the StartDiscoverers parameter in zabbix_server.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.discovery_worker.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_discovery_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_discovery_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_discovery_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_discovery_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_discovery_worker_avg_busy != []) {
                $zbx_server_internal_processes['process_discovery_worker_avg_busy'] = round(max($zabbix_server_process_discovery_worker_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_discovery_worker_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of discovery worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_discovery_worker_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of discovery worker internal processes can affect the effectiveness of discovery.';
                    $result_analysis['suggestion'] = '1. Add the StartDiscoverers parameter in zabbix_server.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.escalator.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_escalator_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_escalator_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_escalator_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_escalator_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_escalator_avg_busy != []) {
                $zbx_server_internal_processes['process_escalator_avg_busy'] = round(max($zabbix_server_process_escalator_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_escalator_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of escalator internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_escalator_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of escalator internal processes can affect the timely handling and notification of event issues.';
                    $result_analysis['suggestion'] = '1. Add the StartEscalators parameter in zabbix_server.conf; 2. Optimize and reduce the number of events.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.history_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_history_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_history_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_history_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_history_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_history_poller_avg_busy != []) {
                $zbx_server_internal_processes['process_history_poller_avg_busy'] = round(max($zabbix_server_process_history_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_history_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of history poller internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_history_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of history poller internal processes can affect the processing speed and effectiveness of calculated checks.';
                    $result_analysis['suggestion'] = '1. Add the StartHistoryPollers parameter in zabbix_server.conf; 2. Optimize the calculated checks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.history_syncer.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_history_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_history_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_history_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_history_syncer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_history_syncer_avg_busy != []) {
                $zbx_server_internal_processes['process_history_syncer_avg_busy'] = round(max($zabbix_server_process_history_syncer_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_history_syncer_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of history syncer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_history_syncer_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of history syncer internal processes can affect the timely storage and processing of monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the HistoryCacheSize parameter in zabbix_server.conf; 2. Optimize the performance of databases and networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.housekeeper.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_housekeeper_avg_busy != []) {
                $zbx_server_internal_processes['process_housekeeper_avg_busy'] = round(max($zabbix_server_process_housekeeper_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_housekeeper_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of housekeeper internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_housekeeper_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of housekeeper internal processes can affect the efficiency of timely cleaning of invalid historical data.';
                    $result_analysis['suggestion'] = '1. Add the HousekeepingFrequency parameter in zabbix_server.conf, But it may exacerbate this issue; 2. Add MaxHousekeeperDelete parameter in zabbix_server.conf, the specific value of this parameter needs to be adjusted according to the specific situation, and there is no unified standard.; 3. Optimize by partitioning tables or reducing the total amount of data in the database.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.ipmi_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_ipmi_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_ipmi_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_ipmi_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_ipmi_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_ipmi_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_ipmi_manager_avg_busy'] = round(max($zabbix_server_process_ipmi_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_ipmi_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of ipmi manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_ipmi_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ipmi manager internal processes can affect the normal acquisition of IPMI monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartIPMIPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.lld_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_lld_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_lld_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_lld_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_lld_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_lld_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_lld_manager_avg_busy'] = round(max($zabbix_server_process_lld_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_lld_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of lld manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_lld_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of lld manager internal processes can affect the effectiveness of LLD.';
                    $result_analysis['suggestion'] = '1. Add the StartLLDProcessors parameter in zabbix_server.conf; 2. Optimize the number of monitoring items for LLD; 3. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.lld_worker.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_lld_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_lld_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_lld_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_lld_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_lld_worker_avg_busy != []) {
                $zbx_server_internal_processes['process_lld_worker_avg_busy'] = round(max($zabbix_server_process_lld_worker_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_lld_worker_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of lld worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_lld_worker_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of lld worker internal processes can affect the effectiveness of LLD.';
                    $result_analysis['suggestion'] = '1. Add the StartLLDProcessors parameter in zabbix_server.conf; 2. Optimize the number of monitoring items for LLD; 3. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.preprocessing_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_preprocessing_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_preprocessing_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_preprocessing_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_preprocessing_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_preprocessing_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_preprocessing_manager_avg_busy'] = round(max($zabbix_server_process_preprocessing_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_preprocessing_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of preprocessing manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_preprocessing_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of preprocessing manager internal processes can affect the effectiveness of preprocessing monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPreprocessors parameter in zabbix_server.conf; 2. Optimize preprocessing strategies, such as minimizing processing levels as much as possible.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.preprocessing_worker.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_preprocessing_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_preprocessing_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_preprocessing_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_preprocessing_worker_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_preprocessing_worker_avg_busy != []) {
                $zbx_server_internal_processes['process_preprocessing_worker_avg_busy'] = round(max($zabbix_server_process_preprocessing_worker_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_preprocessing_worker_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of preprocessing worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_preprocessing_worker_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of preprocessing worker internal processes can affect the effectiveness of preprocessing monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPreprocessors parameter in zabbix_server.conf; 2. Optimize preprocessing strategies, such as minimizing processing levels as much as possible.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.proxy_group_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_proxy_group_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_proxy_group_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_proxy_group_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_proxy_group_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_proxy_group_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_proxy_group_manager_avg_busy'] = round(max($zabbix_server_process_proxy_group_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_proxy_group_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of proxy group manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_proxy_group_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of proxy group manager internal processes can affect the normal management of proxy group and the timely detection and switching of faulty proxy.';
                    $result_analysis['suggestion'] = '1. Check if there are too many proxies managed in the proxy group; 2. Check if there are frequent proxy switches occurring.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.report_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_report_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_report_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_report_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_report_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_report_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_report_manager_avg_busy'] = round(max($zabbix_server_process_report_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_report_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of report manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_report_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of report manager internal processes can affect the normal generation of reports.';
                    $result_analysis['suggestion'] = '1. Add the StartReportWriters parameter in zabbix_server.conf; 2. Check if there are too many reports being generated simultaneously.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.report_writer.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_report_writer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_report_writer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_report_writer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_report_writer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_report_writer_avg_busy != []) {
                $zbx_server_internal_processes['process_report_writer_avg_busy'] = round(max($zabbix_server_process_report_writer_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_report_writer_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of report writer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_report_writer_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of report writer internal processes can affect the normal generation of reports.';
                    $result_analysis['suggestion'] = '1. Add the StartReportWriters parameter in zabbix_server.conf; 2. Check if there are too many reports being generated simultaneously.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.self-monitoring.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_self_monitoring_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_self_monitoring_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_self_monitoring_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_self_monitoring_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_self_monitoring_avg_busy != []) {
                $zbx_server_internal_processes['process_self-monitoring_avg_busy'] = round(max($zabbix_server_process_self_monitoring_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_self-monitoring_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of self-monitoring internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_self-monitoring_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of self-monitoring internal processes can affect the monitoring of the Zabbix application\'s own metrics.';
                    $result_analysis['suggestion'] = '1. Optimize the hardware and system configuration of the host where the Zabbix server is located; 2. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.service_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_service_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_service_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_service_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_service_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_service_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_service_manager_avg_busy'] = round(max($zabbix_server_process_service_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_service_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of service manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_service_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of service manager internal processes can affect the data collection, analysis, and presentation of the service.';
                    $result_analysis['suggestion'] = 'Add the ServiceManagerSyncFrequency parameter in zabbix_server.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.task_manager.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_task_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_task_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_task_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_task_manager_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_task_manager_avg_busy != []) {
                $zbx_server_internal_processes['process_task_manager_avg_busy'] = round(max($zabbix_server_process_task_manager_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_task_manager_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of task manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_task_manager_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of task manager internal processes can affect the normal scheduling and execution of various tasks within Zabbix.';
                    $result_analysis['suggestion'] = '1. Check for services or tasks with abnormal status on the web UI; 2. Optimize the hardware and system configuration of the host where the Zabbix server is located; 3. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.timer.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_timer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_timer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_timer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_timer_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_timer_avg_busy != []) {
                $zbx_server_internal_processes['process_timer_avg_busy'] = round(max($zabbix_server_process_timer_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_timer_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of timer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_timer_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of timer internal processes can affect the normal scheduling and execution of various tasks within Zabbix.';
                    $result_analysis['suggestion'] = '1. Check for services or tasks with abnormal status on the web UI; 2. Optimize the hardware and system configuration of the host where the Zabbix server is located; 3. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.trigger_housekeeper.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_trigger_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_trigger_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_trigger_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_trigger_housekeeper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_trigger_housekeeper_avg_busy != []) {
                $zbx_server_internal_processes['process_trigger_housekeeper_avg_busy'] = round(max($zabbix_server_process_trigger_housekeeper_avg_busy)['value_max'], 2);

                if ($zbx_server_internal_processes['process_trigger_housekeeper_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of trigger housekeeper internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_server_internal_processes['process_trigger_housekeeper_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of trigger housekeeper internal processes can affect the efficiency of timely cleaning of invalid trigger data.';
                    $result_analysis['suggestion'] = '1. Add the HousekeepingFrequency parameter in zabbix_server.conf, But it may exacerbate this issue; 2. Add MaxHousekeeperDelete parameter in zabbix_server.conf, the specific value of this parameter needs to be adjusted according to the specific situation, and there is no unified standard.; 3. Optimize by partitioning tables or reducing the total amount of data in the database.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            array_push($data['zabbix_server_internal_processes'], $zbx_server_internal_processes);

            $data['zabbix_server_collector_processes'] = [];
            $zbx_server_collector_processes = [
                'process_agent_poller_avg_busy' => 0,
                'process_browser_poller_avg_busy' => 0,
                'process_http_agent_poller_avg_busy' => 0,
                'process_http_poller_avg_busy' => 0,
                'process_icmp_pinger_avg_busy' => 0,
                'process_internal_poller_avg_busy' => 0,
                'process_ipmi_poller_avg_busy' => 0,
                'process_java_poller_avg_busy' => 0,
                'process_odbc_poller_avg_busy' => 0,
                'process_poller_avg_busy' => 0,
                'process_proxy_poller_avg_busy' => 0,
                'process_snmp_poller_avg_busy' => 0,
                'process_snmp_trapper_avg_busy' => 0,
                'process_trapper_avg_busy' => 0,
                'process_unreachable_poller_avg_busy' => 0,
                'process_vmware_collector_avg_busy' => 0
            ];

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.agent_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_agent_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_agent_poller_avg_busy'] = round(max($zabbix_server_process_agent_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_agent_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of agent poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_agent_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of agent poller collector processes can affect the normal collection of agent data.';
                    $result_analysis['suggestion'] = '1. Add the StartAgentPollers parameter in zabbix_server.conf; 2. Optimize the number of items monitored by the agent.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.browser_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_browser_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_browser_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_browser_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_browser_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_browser_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_browser_poller_avg_busy'] = round(max($zabbix_server_process_browser_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_browser_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of browser poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_browser_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of browser poller collector processes can affect the normal collection of browser data.';
                    $result_analysis['suggestion'] = '1. Add the StartBrowserPollers parameter in zabbix_server.conf; 2. Optimize the number of monitored items for browser.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.http_agent_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_http_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_http_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_http_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_http_agent_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_http_agent_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_http_agent_poller_avg_busy'] = round(max($zabbix_server_process_http_agent_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_http_agent_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of http agent poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_http_agent_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of http agent poller collector processes can affect the normal collection of http agent data.';
                    $result_analysis['suggestion'] = '1. Optimize the number of monitored items for http agent; 2. Optimize http\'s monitoring strategies.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.http_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_http_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_http_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_http_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_http_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_http_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_http_poller_avg_busy'] = round(max($zabbix_server_process_http_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_http_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of http poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_http_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of http poller collector processes can affect the normal collection of http data.';
                    $result_analysis['suggestion'] = '1. Optimize the number of monitored items for http; 2. Optimize http\'s monitoring strategies.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.icmp_pinger.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_icmp_pinger_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_icmp_pinger_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_icmp_pinger_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_icmp_pinger_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_icmp_pinger_avg_busy != []) {
                $zbx_server_collector_processes['process_icmp_pinger_avg_busy'] = round(max($zabbix_server_process_icmp_pinger_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_icmp_pinger_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of icmp pinger collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_icmp_pinger_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of icmp pinger collector processes can affect the normal collection of icmp ping data.';
                    $result_analysis['suggestion'] = '1.Add the Timeout parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.internal_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_internal_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_internal_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_internal_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_internal_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_internal_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_internal_poller_avg_busy'] = round(max($zabbix_server_process_internal_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_internal_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of internal poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_internal_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of internal poller collector processes can affect the monitoring of the "zabbix internal" metrics.';
                    $result_analysis['suggestion'] = '1. Optimize the hardware and system configuration of the host where the Zabbix server is located; 2. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.ipmi_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_ipmi_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_ipmi_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_ipmi_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_ipmi_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_ipmi_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_ipmi_poller_avg_busy'] = round(max($zabbix_server_process_ipmi_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_ipmi_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of ipmi poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_ipmi_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ipmi poller collector processes can affect the normal acquisition of IPMI monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartIPMIPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.java_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_java_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_java_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_java_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_java_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_java_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_java_poller_avg_busy'] = round(max($zabbix_server_process_java_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_java_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of java poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_java_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of java poller collector processes can affect the normal acquisition of java monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartJavaPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.odbc_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_odbc_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_odbc_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_odbc_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_odbc_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_odbc_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_odbc_poller_avg_busy'] = round(max($zabbix_server_process_odbc_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_odbc_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of ODBC poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_odbc_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ODBC poller collector processes can affect the normal acquisition of ODBC data.';
                    $result_analysis['suggestion'] = '1. Add the StartODBCPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_poller_avg_busy'] = round(max($zabbix_server_process_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of poller collector processes can affect the normal acquisition of monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.proxy_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_proxy_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_proxy_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_proxy_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_proxy_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_proxy_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_proxy_poller_avg_busy'] = round(max($zabbix_server_process_proxy_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_proxy_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of proxy poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_proxy_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of proxy poller collector processes can affect the normal acquisition of proxy data.';
                    $result_analysis['suggestion'] = '1. Add the StartProxyPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.snmp_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_snmp_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_snmp_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_snmp_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_snmp_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_snmp_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_snmp_poller_avg_busy'] = round(max($zabbix_server_process_snmp_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_snmp_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of SNMP poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_snmp_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of SNMP poller collector processes can affect the normal acquisition of SNMP data.';
                    $result_analysis['suggestion'] = '1. Add the StartSNMPPollers parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.snmp_trapper.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_snmp_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_snmp_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_snmp_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_snmp_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_snmp_trapper_avg_busy != []) {
                $zbx_server_collector_processes['process_snmp_trapper_avg_busy'] = round(max($zabbix_server_process_snmp_trapper_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_snmp_trapper_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of SNMP trapper collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_snmp_trapper_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of SNMP trapper collector processes can affect the normal acquisition of SNMP trapper data.';
                    $result_analysis['suggestion'] = '1. Add the StartSNMPTrapper parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.trapper.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_trapper_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_trapper_avg_busy != []) {
                $zbx_server_collector_processes['process_trapper_avg_busy'] = round(max($zabbix_server_process_trapper_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_trapper_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of trapper collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_trapper_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of trapper collector processes can affect the normal acquisition of SNMP trapper data.';
                    $result_analysis['suggestion'] = '1. Add the StartTrappers parameter in zabbix_server.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.unreachable_poller.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_unreachable_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_unreachable_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_unreachable_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_unreachable_poller_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_unreachable_poller_avg_busy != []) {
                $zbx_server_collector_processes['process_unreachable_poller_avg_busy'] = round(max($zabbix_server_process_unreachable_poller_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_unreachable_poller_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of unreachable poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_unreachable_poller_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of unreachable poller collector processes can affect the normal processing of unreachable data.';
                    $result_analysis['suggestion'] = '1. Add the StartPollersUnreachable parameter in zabbix_server.conf; 2. Optimize the performance of networks; 3. Check and resolve unreachable hosts or items.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            $zabbix_server_itemid = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $zabbix_server_hostid[0]['hostid'],
                'search' => [
                    'key_' => 'process.vmware_collector.avg.busy'
                ]
            ]);
            if ($this->getInput('inspection_cycle') == 0) {
                $zabbix_server_process_vmware_collector_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-01-01'),
                    'time_till' => strtotime(date("Y") . '-03-31')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 1) {
                $zabbix_server_process_vmware_collector_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-04-01'),
                    'time_till' => strtotime(date("Y") . '-06-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 2) {
                $zabbix_server_process_vmware_collector_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-07-01'),
                    'time_till' => strtotime(date("Y") . '-09-30')
                ]);
            }
            elseif ($this->getInput('inspection_cycle') == 3) {
                $zabbix_server_process_vmware_collector_avg_busy = API::Trend()->get([
                    'output' => ['value_max'],
                    'itemids' => $zabbix_server_itemid[0],
                    'time_from' => strtotime(date("Y") . '-10-01'),
                    'time_till' => strtotime(date("Y") . '-12-31')
                ]);
            }

            if ($zabbix_server_process_vmware_collector_avg_busy != []) {
                $zbx_server_collector_processes['process_vmware_collector_avg_busy'] = round(max($zabbix_server_process_vmware_collector_avg_busy)['value_max'], 2);

                if ($zbx_server_collector_processes['process_vmware_collector_avg_busy'] > 75) {
                    $result_analysis['description'] = 'The utilization of vmware collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_server_collector_processes['process_vmware_collector_avg_busy'];
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of vmware collector processes can affect the normal processing of VMware monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartVMwareCollectors, VMwareFrequency, VMwarePerfFrequency, VMwareCacheSize, VMwareTimeout parameters in zabbix_server.conf; 2. Optimize the performance of networks; 3. Optimize the number of monitoring items for VMware; 4. Optimize the API interface performance of VMware itself.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }

            array_push($data['zabbix_server_collector_processes'], $zbx_server_collector_processes);

            if ($this->hasInput('zabbix_proxy_ids', [])) {
                $data['zabbix_proxy_performance'] = [];
                $zbx_proxy_perf = [
                    'proxy_name' => '',
                    'cpu_util' => 0,
                    'cpu_iowait' => 0,
                    'cpu_load' => 0,
                    'mem_util' => 0,
                    'swap_util' => 0
                ];

                foreach ($zabbix_proxy_ids[0] as $zabbix_proxy_id) {
                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.hostname'
                        ]
                    ]);
                    $zabbix_proxy_hostname = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 1,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zbx_proxy_perf['proxy_name'] = $zabbix_proxy_hostname[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.cpu.util'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_cpu_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_cpu_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_cpu_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_cpu_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zbx_proxy_perf['cpu_util'] = round(max($zabbix_proxy_cpu_util)['value_max'], 2);
                    if ($zbx_proxy_perf['cpu_util'] > 90) {
                        $proxy_cpu_util = $zbx_proxy_perf['cpu_util'];
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.cpu.util[,iowait]'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_cpu_iowait = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_cpu_iowait = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_cpu_iowait = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_cpu_iowait = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zbx_proxy_perf['cpu_iowait'] = round(max($zabbix_proxy_cpu_iowait)['value_max'], 2);
                    if ($zbx_proxy_perf['cpu_iowait'] > 90) {
                        $proxy_cpu_iowait = $zbx_proxy_perf['cpu_iowait'];
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.cpu.load[all,avg1]'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_cpu_load = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_cpu_load = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_cpu_load = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_cpu_load = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zbx_proxy_perf['cpu_load'] = round(max($zabbix_proxy_cpu_load)['value_max'], 2);

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'vm.memory.utilization'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_mem_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_mem_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_mem_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_mem_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zbx_proxy_perf['mem_util'] = round(max($zabbix_proxy_mem_util)['value_max'], 2);
                    if ($zbx_proxy_perf['mem_util'] > 90) {
                        $proxy_mem_util = $zbx_proxy_perf['mem_util'];
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.swap.size[,pfree]'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_swap_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_server_swap_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_swap_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_swap_util = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }
                    $zbx_proxy_perf['swap_util'] = round(100 - max($zabbix_proxy_swap_util)['value_max'], 2);
                    if ($zbx_proxy_perf['swap_util'] > 90) {
                        $proxy_swap_util = $zbx_proxy_perf['swap_util'];
                    }

                    array_push($data['zabbix_proxy_performance'], $zbx_proxy_perf);
                };

                if ($proxy_cpu_util > 90) {
                    $result_analysis['description'] = 'The CPU utilization of Zabbix proxy is too high.';
                    $result_analysis['type'] = 'system';
                    $result_analysis['value'] = $proxy_cpu_util;
                    $result_analysis['threshold'] = '< 90';
                    $result_analysis['analysis'] = 'Excessive CPU usage can cause system lag, slow response, and even Zabbix service crashes.';
                    $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Increase hardware resources.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($proxy_cpu_iowait > 90) {
                    $result_analysis['description'] = 'The CPU IOwait utilization of Zabbix proxy is too high.';
                    $result_analysis['type'] = 'system';
                    $result_analysis['value'] = $proxy_cpu_iowait;
                    $result_analysis['threshold'] = '< 90';
                    $result_analysis['analysis'] = 'Excessive CPU usage can cause system lag, slow response, and even Zabbix service crashes.';
                    $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Optimize disk performance, such as replace a better performing SSD hard drive.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($proxy_mem_util > 90) {
                    $result_analysis['description'] = 'The memory utilization of Zabbix proxy is too high.';
                    $result_analysis['type'] = 'system';
                    $result_analysis['value'] = $proxy_mem_util;
                    $result_analysis['threshold'] = '< 90';
                    $result_analysis['analysis'] = 'Excessive memory usage can cause system lag, slow response, and even Zabbix service crashes.';
                    $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Increase hardware memory capacity.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($proxy_swap_util > 90) {
                    $result_analysis['description'] = 'The SWAP utilization of Zabbix proxy is too high.';
                    $result_analysis['type'] = 'system';
                    $result_analysis['value'] = $proxy_swap_util;
                    $result_analysis['threshold'] = '< 90';
                    $result_analysis['analysis'] = 'Excessive SWAP usage can cause system lag, slow response, and even Zabbix service crashes.';
                    $result_analysis['suggestion'] = '1. Check for viruses and malicious software; 2. Optimize Zabbix monitoring items; 3. Adjust the swappiness parameter; 4. Increase hardware memory capacity.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                $data['zabbix_proxy_cache'] = [];
                $zbx_proxy_cache = [
                    'name' => '',
                    'rcache_buffer_pused' => 0,
                    'wcache_index_pused' => 0,
                    'wcache_history_pused' => 0,
                    'vmware_buffer_pused' => 0
                ];

                $data['zabbix_proxy_internal_process'] = [];
                $zbx_proxy_internal_process = [
                    'name' => '',
                    'process_availability_manager_avg_busy' => 0,
                    'process_configuration_syncer_avg_busy' => 0,
                    'process_data_sender_avg_busy' => 0,
                    'process_discovery_manager_avg_busy' => 0,
                    'process_discovery_worker_avg_busy' => 0,
                    'process_history_syncer_avg_busy' => 0,
                    'process_housekeeper_avg_busy' => 0,
                    'process_ipmi_manager_avg_busy' => 0,
                    'process_preprocessing_manager_avg_busy' => 0,
                    'process_preprocessing_worker_avg_busy' => 0,
                    'process_self_monitoring_avg_busy' => 0,
                    'process_task_manager_avg_busy' => 0
                ];

                $data['zabbix_proxy_collector_process'] = [];
                $zbx_proxy_collector_process = [
                    'name' => '',
                    'process_agent_poller_avg_busy' => 0,
                    'process_browser_poller_avg_busy' => 0,
                    'process_http_agent_poller_avg_busy' => 0,
                    'process_http_poller_avg_busy' => 0,
                    'process_icmp_pinger_avg_busy' => 0,
                    'process_internal_poller_avg_busy' => 0,
                    'process_ipmi_poller_avg_busy' => 0,
                    'process_java_poller_avg_busy' => 0,
                    'process_odbc_poller_avg_busy' => 0,
                    'process_poller_avg_busy' => 0,
                    'process_snmp_poller_avg_busy' => 0,
                    'process_snmp_trapper_avg_busy' => 0,
                    'process_trapper_avg_busy' => 0,
                    'process_unreachable_poller_avg_busy' => 0,
                    'process_vmware_collector_avg_busy' => 0
                ];

                foreach ($zabbix_proxy_ids[0] as $zabbix_proxy_id) {
                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'system.hostname'
                        ]
                    ]);
                    $zabbix_proxy_name = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $zabbix_proxy_itemid[0],
                        'history' => 1,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    $zbx_proxy_cache['name'] = $zabbix_proxy_name[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'rcache.buffer.pused'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_rcache_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_rcache_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_rcache_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_rcache_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_rcache_buffer_pused != []) {
                        $zbx_proxy_cache['rcache_buffer_pused'] = round(max($zabbix_proxy_rcache_buffer_pused)['value_max'], 2);

                        if ($zbx_proxy_cache['rcache_buffer_pused'] > 75) {
                            $zbx_proxy_cache_rcache_buffer_pused = $zbx_proxy_cache['rcache_buffer_pused'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'wcache.index.pused'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_wcache_index_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_wcache_index_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_wcache_index_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_wcache_index_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_wcache_index_pused != []) {
                        $zbx_proxy_cache['wcache_index_pused'] = round(max($zabbix_proxy_wcache_index_pused)['value_max'], 2);

                        if ($zbx_proxy_cache['wcache_index_pused'] > 75) {
                            $zbx_proxy_cache_wcache_index_pused = $zbx_proxy_cache['wcache_index_pused'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'wcache.history.pused'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_wcache_history_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_wcache_history_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_wcache_history_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_wcache_history_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_wcache_history_pused != []) {
                        $zbx_proxy_cache['wcache_history_pused'] = round(max($zabbix_proxy_wcache_history_pused)['value_max'], 2);

                        if ($zbx_proxy_cache['wcache_history_pused'] > 75) {
                            $zbx_proxy_cache_wcache_history_pused = $zbx_proxy_cache['wcache_history_pused'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'vmware.buffer.pused'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_vmware_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_vmware_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_vmware_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_vmware_buffer_pused = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_vmware_buffer_pused != []) {
                        $zbx_proxy_cache['vmware_buffer_pused'] = round(max($zabbix_proxy_vmware_buffer_pused)['value_max'], 2);

                        if ($zbx_proxy_cache['vmware_buffer_pused'] > 75) {
                            $zbx_proxy_cache_vmware_buffer_pused = $zbx_proxy_cache['vmware_buffer_pused'];
                        }
                    }

                    array_push($data['zabbix_proxy_cache'], $zbx_proxy_cache);

                    $zbx_proxy_internal_process['name'] = $zabbix_proxy_name[0]['value'];

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.availability_manager.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_availability_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_availability_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_availability_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_availability_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_availability_manager_avg_busy != []) {
                        $zbx_proxy_internal_process['process_availability_manager_avg_busy'] = round(max($zabbix_proxy_process_availability_manager_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_availability_manager_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_availability_manager_avg_busy = $zbx_proxy_internal_process['process_availability_manager_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.configuration_syncer.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_configuration_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_configuration_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_configuration_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_configuration_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_configuration_syncer_avg_busy != []) {
                        $zbx_proxy_internal_process['process_configuration_syncer_avg_busy'] = round(max($zabbix_proxy_process_configuration_syncer_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_configuration_syncer_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_configuration_syncer_avg_busy = $zbx_proxy_internal_process['process_configuration_syncer_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.data_sender.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_data_sender_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_data_sender_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_data_sender_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_data_sender_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_data_sender_avg_busy != []) {
                        $zbx_proxy_internal_process['process_data_sender_avg_busy'] = round(max($zabbix_proxy_process_data_sender_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_data_sender_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_data_sender_avg_busy = $zbx_proxy_internal_process['process_data_sender_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.discovery_manager.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_discovery_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_discovery_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_discovery_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_discovery_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_discovery_manager_avg_busy != []) {
                        $zbx_proxy_internal_process['process_discovery_manager_avg_busy'] = round(max($zabbix_proxy_process_discovery_manager_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_discovery_manager_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_discovery_manager_avg_busy = $zbx_proxy_internal_process['process_discovery_manager_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.discovery_worker.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_discovery_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_discovery_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_discovery_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_discovery_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_discovery_worker_avg_busy != []) {
                        $zbx_proxy_internal_process['process_discovery_worker_avg_busy'] = round(max($zabbix_proxy_process_discovery_worker_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_discovery_worker_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_discovery_worker_avg_busy = $zbx_proxy_internal_process['process_discovery_worker_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.history_syncer.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_history_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_history_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_history_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_history_syncer_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_history_syncer_avg_busy != []) {
                        $zbx_proxy_internal_process['process_history_syncer_avg_busy'] = round(max($zabbix_proxy_process_history_syncer_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_history_syncer_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_history_syncer_avg_busy = $zbx_proxy_internal_process['process_history_syncer_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.housekeeper.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_housekeeper_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_housekeeper_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_housekeeper_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_housekeeper_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_housekeeper_avg_busy != []) {
                        $zbx_proxy_internal_process['process_housekeeper_avg_busy'] = round(max($zabbix_proxy_process_housekeeper_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_housekeeper_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_housekeeper_avg_busy = $zbx_proxy_internal_process['process_housekeeper_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.ipmi_manager.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_ipmi_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_ipmi_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_ipmi_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_ipmi_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_ipmi_manager_avg_busy != []) {
                        $zbx_proxy_internal_process['process_ipmi_manager_avg_busy'] = round(max($zabbix_proxy_process_ipmi_manager_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_ipmi_manager_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_ipmi_manager_avg_busy = $zbx_proxy_internal_process['process_ipmi_manager_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.preprocessing_manager.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_preprocessing_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_preprocessing_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_preprocessing_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_preprocessing_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_preprocessing_manager_avg_busy != []) {
                        $zbx_proxy_internal_process['process_preprocessing_manager_avg_busy'] = round(max($zabbix_proxy_process_preprocessing_manager_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_preprocessing_manager_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_preprocessing_manager_avg_busy = $zbx_proxy_internal_process['process_preprocessing_manager_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.preprocessing_worker.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_preprocessing_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_preprocessing_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_preprocessing_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_preprocessing_worker_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_preprocessing_worker_avg_busy != []) {
                        $zbx_proxy_internal_process['process_preprocessing_worker_avg_busy'] = round(max($zabbix_proxy_process_preprocessing_worker_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_preprocessing_worker_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_preprocessing_worker_avg_busy = $zbx_proxy_internal_process['process_preprocessing_worker_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.self-monitoring.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_self_monitoring_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_self_monitoring_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_self_monitoring_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_self_monitoring_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_self_monitoring_avg_busy != []) {
                        $zbx_proxy_internal_process['process_self_monitoring_avg_busy'] = round(max($zabbix_proxy_process_self_monitoring_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_self_monitoring_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_self_monitoring_avg_busy = $zbx_proxy_internal_process['process_self_monitoring_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.task_manager.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_task_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_task_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_task_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_task_manager_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_task_manager_avg_busy != []) {
                        $zbx_proxy_internal_process['process_task_manager_avg_busy'] = round(max($zabbix_proxy_process_task_manager_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_internal_process['process_task_manager_avg_busy'] > 75) {
                            $zbx_proxy_internal_process_task_manager_avg_busy = $zbx_proxy_internal_process['process_task_manager_avg_busy'];
                        }
                    }

                    array_push($data['zabbix_proxy_internal_process'], $zbx_proxy_internal_process);

                    $zbx_proxy_collector_process['name'] = $zabbix_proxy_name[0]['value'];
                    
                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.agent_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_agent_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_agent_poller_avg_busy'] = round(max($zabbix_proxy_process_agent_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_agent_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_agent_poller_avg_busy = $zbx_proxy_collector_process['process_agent_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.browser_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_browser_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_browser_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_browser_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_browser_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_browser_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_browser_poller_avg_busy'] = round(max($zabbix_proxy_process_browser_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_browser_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_browser_poller_avg_busy = $zbx_proxy_collector_process['process_browser_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.http_agent_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_http_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_http_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_http_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_http_agent_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_http_agent_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_http_agent_poller_avg_busy'] = round(max($zabbix_proxy_process_http_agent_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_http_agent_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_http_agent_poller_avg_busy = $zbx_proxy_collector_process['process_http_agent_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.http_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_http_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_http_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_http_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_http_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_http_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_http_poller_avg_busy'] = round(max($zabbix_proxy_process_http_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_http_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_http_poller_avg_busy = $zbx_proxy_collector_process['process_http_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.icmp_pinger.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_icmp_pinger_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_icmp_pinger_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_icmp_pinger_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_icmp_pinger_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_icmp_pinger_avg_busy != []) {
                        $zbx_proxy_collector_process['process_icmp_pinger_avg_busy'] = round(max($zabbix_proxy_process_icmp_pinger_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_icmp_pinger_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_icmp_pinger_avg_busy = $zbx_proxy_collector_process['process_icmp_pinger_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.internal_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_internal_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_internal_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_internal_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_internal_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_internal_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_internal_poller_avg_busy'] = round(max($zabbix_proxy_process_internal_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_internal_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_internal_poller_avg_busy = $zbx_proxy_collector_process['process_internal_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.ipmi_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_ipmi_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_ipmi_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_ipmi_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_ipmi_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_ipmi_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_ipmi_poller_avg_busy'] = round(max($zabbix_proxy_process_ipmi_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_ipmi_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_ipmi_poller_avg_busy = $zbx_proxy_collector_process['process_ipmi_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.java_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_java_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_java_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_java_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_java_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_java_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_java_poller_avg_busy'] = round(max($zabbix_proxy_process_java_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_java_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_java_poller_avg_busy = zbx_proxy_collector_process['process_java_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.odbc_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_odbc_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_odbc_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_odbc_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_odbc_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_odbc_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_odbc_poller_avg_busy'] = round(max($zabbix_proxy_process_odbc_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_odbc_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_odbc_poller_avg_busy = $zbx_proxy_collector_process['process_odbc_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_poller_avg_busy'] = round(max($zabbix_proxy_process_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_poller_avg_busy = $zbx_proxy_collector_process['process_poller_avg_busy'];
                        }
                    }

                    $zabbix_proxy_itemid = API::Item()->get([
                        'output' => ['itemid'],
                        'hostids' => $zabbix_proxy_id,
                        'search' => [
                            'key_' => 'process.snmp_poller.avg.busy'
                        ]
                    ]);
                    if ($this->getInput('inspection_cycle') == 0) {
                        $zabbix_proxy_process_snmp_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-01-01'),
                            'time_till' => strtotime(date("Y") . '-03-31')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 1) {
                        $zabbix_proxy_process_snmp_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-04-01'),
                            'time_till' => strtotime(date("Y") . '-06-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 2) {
                        $zabbix_proxy_process_snmp_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-07-01'),
                            'time_till' => strtotime(date("Y") . '-09-30')
                        ]);
                    }
                    elseif ($this->getInput('inspection_cycle') == 3) {
                        $zabbix_proxy_process_snmp_poller_avg_busy = API::Trend()->get([
                            'output' => ['value_max'],
                            'itemids' => $zabbix_proxy_itemid[0],
                            'time_from' => strtotime(date("Y") . '-10-01'),
                            'time_till' => strtotime(date("Y") . '-12-31')
                        ]);
                    }

                    if ($zabbix_proxy_process_snmp_poller_avg_busy != []) {
                        $zbx_proxy_collector_process['process_snmp_poller_avg_busy'] = round(max($zabbix_proxy_process_snmp_poller_avg_busy)['value_max'], 2);

                        if ($zbx_proxy_collector_process['process_snmp_poller_avg_busy'] > 75) {
                            $zbx_proxy_collector_process_snmp_poller_avg_busy = $zbx_proxy_collector_process['process_snmp_poller_avg_busy'];
                        }
                    }

                    array_push($data['zabbix_proxy_collector_process'], $zbx_proxy_collector_process);
                }

                if ($zbx_proxy_cache_rcache_buffer_pused > 75) {
                    $result_analysis['description'] = 'The configuration cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_proxy_cache_rcache_buffer_pused;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of configuration cache can result in the inability to properly store host, item, and trigger data.';
                    $result_analysis['suggestion'] = 'Add the CacheSize parameter in zabbix_proxy.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_cache_wcache_index_pused > 75) {
                    $result_analysis['description'] = 'The history index cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_proxy_cache_wcache_index_pused;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of history index cache can result in the inability to properly index history cache.';
                    $result_analysis['suggestion'] = 'Add the HistoryIndexCacheSize parameter in zabbix_proxy.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_cache_wcache_history_pused > 75) {
                    $result_analysis['description'] = 'The history write cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_proxy_cache_wcache_history_pused;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of history write cache can result in the inability to properly store history cache.';
                    $result_analysis['suggestion'] = 'Add the HistoryCacheSize parameter in zabbix_proxy.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_cache_vmware_buffer_pused > 75) {
                    $result_analysis['description'] = 'The vmware cache utilization is too high.';
                    $result_analysis['type'] = 'cache';
                    $result_analysis['value'] = $zbx_proxy_cache_vmware_buffer_pused;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive usage of vmware cache can result in the inability to properly store vmware data.';
                    $result_analysis['suggestion'] = 'Add the VMwareCacheSize parameter in zabbix_proxy.conf.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_availability_manager_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of availability manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_availability_manager_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of availability manager internal processes can result in the inability to properly process alert issuse.';
                    $result_analysis['suggestion'] = 'Add the StartAlerters parameter in zabbix_proxy.conf, or optimize and reduce the number of alarm events.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_configuration_syncer_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of configuration syncer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_configuration_syncer_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of configuration syncer internal processes will respond to the synchronization of configuration information between the server and proxy.';
                    $result_analysis['suggestion'] = '1. Add the ProxyConfigFrequency and ProxyDataFrequency parameters in zabbix_proxy.conf, The parameters is used only for proxies in the passive mode; 2. Increase the number of proxy and allocate the number of hosts and items monitored by proxy reasonably; 3. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_data_sender_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of data sender internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_data_sender_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of data sender internal processes will affect the normal transmission of proxy data to the server.';
                    $result_analysis['suggestion'] = '1. Add the DataSenderFrequency and ProxyDataFrequency parameters in zabbix_proxy.conf; 2. Optimize and reduce the number of hosts and items monitored by proxy; 3. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_discovery_manager_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of discovery manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_discovery_manager_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of discovery manager internal processes can affect the effectiveness of discovery.';
                    $result_analysis['suggestion'] = '1. Add the StartDiscoverers parameter in zabbix_proxy.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_discovery_worker_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of discovery worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_discovery_worker_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of discovery worker internal processes can affect the effectiveness of discovery.';
                    $result_analysis['suggestion'] = '1. Add the StartDiscoverers parameter in zabbix_proxy.conf; 2. Optimize the communication quality of the network.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_history_syncer_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of history syncer internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_history_syncer_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of history syncer internal processes can affect the timely storage and processing of monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the HistoryCacheSize parameter in zabbix_proxy.conf; 2. Optimize the performance of databases and networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_housekeeper_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of housekeeper internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_housekeeper_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of housekeeper internal processes can affect the efficiency of timely cleaning of invalid historical data.';
                    $result_analysis['suggestion'] = '1. Add the HousekeepingFrequency parameter in zabbix_proxy.conf, But it may exacerbate this issue; 2. Add MaxHousekeeperDelete parameter in zabbix_proxy.conf, the specific value of this parameter needs to be adjusted according to the specific situation, and there is no unified standard.; 3. Optimize by partitioning tables or reducing the total amount of data in the database.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_ipmi_manager_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of ipmi manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_ipmi_manager_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ipmi manager internal processes can affect the normal acquisition of IPMI monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartIPMIPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_preprocessing_manager_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of preprocessing manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_preprocessing_manager_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of preprocessing manager internal processes can affect the effectiveness of preprocessing monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPreprocessors parameter in zabbix_proxy.conf; 2. Optimize preprocessing strategies, such as minimizing processing levels as much as possible.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_preprocessing_worker_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of preprocessing worker internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_preprocessing_worker_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of preprocessing worker internal processes can affect the effectiveness of preprocessing monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPreprocessors parameter in zabbix_proxy.conf; 2. Optimize preprocessing strategies, such as minimizing processing levels as much as possible.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_self_monitoring_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of self-monitoring internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_self_monitoring_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of self-monitoring internal processes can affect the monitoring of the Zabbix application\'s own metrics.';
                    $result_analysis['suggestion'] = '1. Optimize the hardware and system configuration of the host where the Zabbix proxy is located; 2. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_internal_process_task_manager_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of task manager internal processes is too high.';
                    $result_analysis['type'] = 'internal';
                    $result_analysis['value'] = $zbx_proxy_internal_process_task_manager_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of task manager internal processes can affect the normal scheduling and execution of various tasks within Zabbix.';
                    $result_analysis['suggestion'] = '1. Check for services or tasks with abnormal status on the web UI; 2. Optimize the hardware and system configuration of the host where the Zabbix proxy is located; 3. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_agent_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of agent poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_agent_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of agent poller collector processes can affect the normal collection of agent data.';
                    $result_analysis['suggestion'] = '1. Add the StartAgentPollers parameter in zabbix_proxy.conf; 2. Optimize the number of items monitored by the agent.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_browser_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of browser poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_browser_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of browser poller collector processes can affect the normal collection of browser data.';
                    $result_analysis['suggestion'] = '1. Add the StartBrowserPollers parameter in zabbix_proxy.conf; 2. Optimize the number of monitored items for browser.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_http_agent_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of http agent poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_http_agent_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of http agent poller collector processes can affect the normal collection of http agent data.';
                    $result_analysis['suggestion'] = '1. Optimize the number of monitored items for http agent; 2. Optimize http\'s monitoring strategies.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_http_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of http poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_http_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of http poller collector processes can affect the normal collection of http data.';
                    $result_analysis['suggestion'] = '1. Optimize the number of monitored items for http; 2. Optimize http\'s monitoring strategies.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_icmp_pinger_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of icmp pinger collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_icmp_pinger_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of icmp pinger collector processes can affect the normal collection of icmp ping data.';
                    $result_analysis['suggestion'] = '1.Add the Timeout parameter in zabbix_proxy.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_internal_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of internal poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_internal_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of internal poller collector processes can affect the monitoring of the "zabbix internal" metrics.';
                    $result_analysis['suggestion'] = '1. Optimize the hardware and system configuration of the host where the Zabbix server is located; 2. Check if there are any abnormal error messages in the Zabbix logs.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_ipmi_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of ipmi poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_ipmi_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ipmi poller collector processes can affect the normal acquisition of IPMI monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartIPMIPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_java_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of java poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_java_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of java poller collector processes can affect the normal acquisition of java monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartJavaPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_odbc_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of ODBC poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_odbc_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of ODBC poller collector processes can affect the normal acquisition of ODBC data.';
                    $result_analysis['suggestion'] = '1. Add the StartODBCPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks; 3. Switch to other data acquisition methods.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of poller collector processes can affect the normal acquisition of monitoring data.';
                    $result_analysis['suggestion'] = '1. Add the StartPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }

                if ($zbx_proxy_collector_process_snmp_poller_avg_busy > 75) {
                    $result_analysis['description'] = 'The utilization of SNMP poller collector processes is too high.';
                    $result_analysis['type'] = 'collector';
                    $result_analysis['value'] = $zbx_proxy_collector_process_snmp_poller_avg_busy;
                    $result_analysis['threshold'] = '< 75';
                    $result_analysis['analysis'] = 'Excessive utilization of SNMP poller collector processes can affect the normal acquisition of SNMP data.';
                    $result_analysis['suggestion'] = '1. Add the StartSNMPPollers parameter in zabbix_proxy.conf; 2. Optimize the performance of networks.';
                    array_push($data['result_analysis'], $result_analysis);
                }
            }
        }

        $response = new CControllerResponseData($data);
        $this->setResponse($response);
    }
}