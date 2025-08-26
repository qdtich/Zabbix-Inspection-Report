for 7.0

Installation steps

1. Add two keys to the agent configuration file of Zabbix server for obtain configuration and log information. Create a new conf file directly in the agent configuration file or in the agent configuration directory, and add 2 monitoring keys.<br>
`UserParameter=zabbix.server.conf,grep -Ev "^$|^#" /etc/zabbix/zabbix_server.conf`<br>
`UserParameter=zabbix.server.log,grep -Ei "exit|stop|fail" /var/log/zabbix/zabbix_server.log | tail -n 20`

2. Add two keys to the agent configuration file of Zabbix proxy for obtain configuration and log information. Create a new conf file directly in the agent configuration file or in the agent configuration directory, and add 2 monitoring keys.<br>
`UserParameter=zabbix.proxy.conf,grep -Ev "^$|^#" /etc/zabbix/zabbix_proxy.conf`<br>
`UserParameter=zabbix.proxy.log,grep -Ei "exit|stop|fail" /var/log/zabbix/zabbix_proxy.log | tail -n 20`

3. Add two monitoring items to the server and proxy monitoring on the frontend UI of Zabbix. Please refer to the screenshot for specific configurations.
<img width="1034" height="714" alt="image" src="https://github.com/user-attachments/assets/8b0c2aa6-d1bd-4e88-928c-c24b31242f53" />
<img width="1037" height="722" alt="image" src="https://github.com/user-attachments/assets/982a199d-338d-4472-bbcb-3f2e46151ba4" />
<img width="1042" height="719" alt="image" src="https://github.com/user-attachments/assets/70e1ad0f-42da-4980-a203-aa03ee47988f" />
<img width="1042" height="727" alt="image" src="https://github.com/user-attachments/assets/6c07c513-8ba9-4435-ac5e-e10383539f54" />

4. Upload the compressed file to the ui/module subdirectory in the directory where the Zabbix frontend UI files are located. If installed using apt or yum, the default path is/usr/share/zbbix/modules.

5. Unzip the compressed file, and ensure that the directory name and location are correct.

6. Log in to the Zabbix frontend UI as an administrator, go to Administration->General->Modules, and click the "Scan directory" button in the upper right corner of the page. After scanning the 'Inspection report', click the button on the right to enable it.
<img width="1700" height="88" alt="image" src="https://github.com/user-attachments/assets/426e823e-95a3-4609-af98-d95782e6c7bf" />

7. On the "Reports" page, you can see the newly added "Inspection report" menu, click to enter. Select the server, proxy, database to be inspected, and click the 'Generate' button to generate report.
<img width="1056" height="335" alt="image" src="https://github.com/user-attachments/assets/830ba88d-65a8-4538-9445-749dfcc506a8" />

8. Enjoy it.
<img width="1718" height="912" alt="image" src="https://github.com/user-attachments/assets/787194a5-d465-40a6-8bb1-d759dfc5944b" />
<img width="1709" height="906" alt="image" src="https://github.com/user-attachments/assets/d49e5558-4f37-4959-993d-23f197956f7b" />
<img width="1715" height="880" alt="image" src="https://github.com/user-attachments/assets/1cd899f0-4fdf-4ef2-9005-972004077d49" />
<img width="1708" height="901" alt="image" src="https://github.com/user-attachments/assets/076642f1-a0b7-4ca2-b993-427da0f1bd9a" />
