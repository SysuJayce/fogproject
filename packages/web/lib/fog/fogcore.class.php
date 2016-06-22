<?php
class FOGCore extends FOGBase {
    public function attemptLogin($username,$password) {
        return self::getClass('User')
            ->validate_pw($username,$password);
    }
    public function stopScheduledTask($task) {
        return self::getClass('ScheduledTask',$task->get('id'))->set('isActive',(int)false)->save();
    }
    public function clearMACLookupTable() {
        $OUITable = self::getClass('OUI','',true);
        $OUITable = $OUITable['databaseTable'];
        return self::$DB->query("TRUNCATE TABLE `$OUITable`");
    }
    public function getMACLookupCount() {
        return self::getClass('OUIManager')->count();
    }
    public function resolveHostname($host) {
        if (filter_var(trim($host),FILTER_VALIDATE_IP)) return trim($host);
        return trim(gethostbyname(trim($host)));
    }
    public function makeTempFilePath() {
        return tempnam(sys_get_temp_dir(),'FOG');
    }
    public function SystemUptime() {
        $data = trim(shell_exec('uptime'));
        $tmp = explode(' load average: ', $data);
        $load = end($tmp);
        $tmp = explode(' up ',$data);
        $tmp = explode(',', end($tmp));
        $uptime = $tmp;
        $uptime = (count($uptime) > 1 ? sprintf('%s, %s',$uptime[0],$uptime[1]) : 'uptime not found');
        return array('uptime'=>$uptime,'load'=>$load);
    }
    public function getBroadcast() {
        $output = array();
        exec("/sbin/ip -4 addr | awk -F'[ /]+' '/global/ {print $6}'|grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs, $retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $3}' | cut -d':' -f2' | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs,$retVal);
        return array_values(array_unique(array_map('trim',(array)$IPs)));
    }
    public function getHWInfo() {
        $data['general'] = '@@general';
        $data['kernel'] = trim(php_uname('r'));
        $data['hostname'] = trim(php_uname('n'));
        $data['uptimeload'] = trim(shell_exec('uptime'));
        $data['cputype'] = trim(shell_exec("cat /proc/cpuinfo | head -n2 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucount'] = trim(shell_exec("grep '^processor' /proc/cpuinfo | tail -n 1 | awk '{print \$3+1}'"));
        $data['cpumodel'] = trim(shell_exec("cat /proc/cpuinfo | head -n5 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpuspeed'] = trim(shell_exec("cat /proc/cpuinfo | head -n8 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucache'] = trim(shell_exec("cat /proc/cpuinfo | head -n9 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['totmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$2 }'")));
        $data['usedmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$3 }'")));
        $data['freemem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$4 }'")));
        $data['filesys'] = '@@fs';
        $hdtotal = 0;
        $hdused = 0;
        array_map(function(&$n) use (&$hdtotal,&$hdused) {
            if (!preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches)) return;
            $hdtotal += (int) $matches[1]*1024;
            $hdused += (int) $matches[2]*1024;
            unset($n);
        },(array)explode("\n",shell_exec('df | grep -vE "^Filesystem|shm"')));
        $data['totalspace'] = $this->formatByteSize($hdtotal);
        $data['usedspace'] = $this->formatByteSize($hdused);
        $data['nic'] = '@@nic';
        array_map(function(&$line) use (&$data) {
            if (!preg_match('#:#',$line)) return;
            list($dev_name,$stats_list) = preg_split('/:/',$line,2);
            $stats = preg_split('/\s+/', trim($stats_list));
            $data[$dev_name] = sprintf('%s$$%s$$%s$$%s$$%s',trim($dev_name),$stats[0],$stats[8],($stats[2]+$stats[10]),($stats[3]+$stats[11]));
            unset($line);
        },(array)explode("\n",shell_exec('cat "/proc/net/dev"')));
        $data['end'] = '@@end';
        return $data;
    }
    public static function setSessionEnv() {
        $_SESSION['HostCount'] = self::getClass('HostManager')->count();
        self::$DB->query("SET SESSION group_concat_max_len=(1024 * {$_SESSION['HostCount']})");
        self::$DB->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".DATABASE_NAME."' AND ENGINE != 'MyISAM'");
        array_map(function(&$table) {
            self::$DB->query(sprintf("ALTER TABLE `%s`.`%s` ENGINE=MyISAM",DATABASE_NAME,$table));
            unset($table);
        },(array)self::$DB->fetch(MYSQLI_NUM,'fetch_all')->get('TABLE_NAME'));
        $_SESSION['PluginsInstalled'] = (array)self::getActivePlugins();
        $_SESSION['FOG_VIEW_DEFAULT_SCREEN'] = self::getSetting('FOG_VIEW_DEFAULT_SCREEN');
        $_SESSION['FOG_FTP_IMAGE_SIZE'] = self::getSetting('FOG_FTP_IMAGE_SIZE');
        if (count(array_filter((array)self::$DB->getColumns('pending','hosts')))) $_SESSION['Pending-Hosts'] = self::getClass('HostManager')->count(array('pending'=>(string)1));
        if (count(array_filter((array)self::$DB->getColumns('pending','moduleStatusByHost')))) $_SESSION['Pending-MACs'] = self::getClass('MACAddressAssociationManager')->count(array('pending'=>(string)1));
        $_SESSION['DataReturn'] = self::getSetting('FOG_DATA_RETURNED');
        $_SESSION['UserCount'] = self::getClass('UserManager')->count();
        $_SESSION['GroupCount'] = self::getClass('GroupManager')->count();
        $_SESSION['ImageCount'] = self::getClass('ImageManager')->count();
        $_SESSION['SnapinCount'] = self::getClass('SnapinManager')->count();
        $_SESSION['PrinterCount'] = self::getClass('PrinterManager')->count();
        $_SESSION['FOGPingActive'] = self::getSetting('FOG_HOST_LOOKUP');
        $_SESSION['memory'] = self::getSetting('FOG_MEMORY_LIMIT');
        $memorySet = preg_replace('#M#','',ini_get('memory_limit'));
        if ((int) $memorySet < $_SESSION['memory']) ini_set('memory_limit',is_numeric($_SESSION['memory']) ? sprintf('%dM',$_SESSION['memory']) : ini_get('memory_limit'));
        $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] = self::getSetting('FOG_FORMAT_FLAG_IN_GUI');
        $_SESSION['FOG_SNAPINDIR'] = self::getSetting('FOG_SNAPINDIR');
        $_SESSION['FOG_REPORT_DIR'] = self::getSetting('FOG_REPORT_DIR');
        $_SESSION['TimeZone'] = (ini_get('date.timezone') ? ini_get('date.timezone') : (self::getSetting('FOG_TZ_INFO') ? self::getSetting('FOG_TZ_INFO') : 'UTC'));
        ini_set('max_input_vars',5000);
        return self::getClass(__CLASS__);
    }
}
