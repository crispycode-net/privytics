<?php

class PrivyticsDataAccess
{
    private $db;
    private $table_name_session;
    private $table_name_action;
    private $table_name_settings;
    private $table_name_session_processed;
    private $table_name_action_processed;
    private $table_name_ip2location;
    private $logger;


    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name_settings = $this->db->prefix . 'privytics_settings';
        $this->table_name_session = $this->db->prefix . 'privytics_session';
        $this->table_name_action = $this->db->prefix . 'privytics_action';
        $this->table_name_session_processed = $this->db->prefix . 'privytics_session_processed';
        $this->table_name_action_processed = $this->db->prefix . 'privytics_action_processed';
        $this->table_name_ip2location = $this->db->prefix . 'privytics_ip2location';

        require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
        $this->logger = new PrivyticsLogger();
    }

    public function createOrUpdateDatabaseSchema()
    {
        // log function start
        $this->logger->info("createOrUpdateDatabaseSchema()");

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->db->get_charset_collate();

        // Check if the table_name_settings table exists
        $count = $this->db->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = '$this->table_name_settings'");
        // If not, create it
        if ($count == 0) {

            $this->logger->info("createOrUpdateDatabaseSchema(): table $this->table_name_settings does not exist, creating it");

            // Create settings / metadata table
            $sql = "CREATE TABLE $this->table_name_settings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                setting varchar(100) NOT NULL,
                value_string varchar(100),
                value_numeric DECIMAL(10,2),
                value_datetime DATETIME,
                PRIMARY KEY  (id),
                UNIQUE KEY setting (setting)
            ) $charset_collate;";
            $this->db->query($sql);

            // get error message
            $error = $this->db->last_error;
            // if error, log it
            if ($error != "") {
                $this->logger->error("createOrUpdateDatabaseSchema(): error creating table $this->table_name_settings: $error");
                // log sql statement
                $this->logger->error("createOrUpdateDatabaseSchema(): sql statement: $sql");
            }

            $this->logger->info("createOrUpdateDatabaseSchema(): table $this->table_name_settings created");
        }

        // Check if the table_name_settings table is empty
        $count = $this->db->get_var("SELECT COUNT(*) FROM $this->table_name_settings");
        // If empty, insert initial rows
        if ($count == 0) {

            // Create session table
            $sql = "CREATE TABLE $this->table_name_session (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                stime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_os varchar(30),
                user_browser varchar(20),
                user_language varchar(7),
                remote_addr varchar(45),
                referrer varchar(3000),
                is_processed tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            $this->db->query($sql);

            // Create action table
            $sql = "CREATE TABLE $this->table_name_action (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                atime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atype varchar(15),
                sessionid bigint(20),
                pageid varchar(50),
                prevpageid varchar(50),
                asequence int(11),
                is_processed tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY FK_SESSION (sessionid),
                CONSTRAINT FK_SESSION FOREIGN KEY (sessionid) REFERENCES $this->table_name_session (id) ON DELETE CASCADE ON UPDATE CASCADE                        
            ) $charset_collate;";
            $this->db->query($sql);

            // Create processed session table        
            $sql = "CREATE TABLE $this->table_name_session_processed (
                id bigint(20) NOT NULL,
                stime DATETIME NOT NULL,
                user_os varchar(30),
                user_browser varchar(20),
                user_language varchar(7),
                country_name varchar(100),
                referrer varchar(3000),
                PRIMARY KEY  (id)
            ) $charset_collate;";
            $this->db->query($sql);

            // Create processed action table
            $sql = "CREATE TABLE $this->table_name_action_processed (
                id bigint(20) NOT NULL,
                atime DATETIME NOT NULL,
                atype varchar(15),
                sessionid bigint(20),
                pageid varchar(50),
                prevpageid varchar(50),
                asequence int(11),
                PRIMARY KEY (id),
                KEY FK_SESSION_PROC (sessionid),
                CONSTRAINT FK_SESSION_PROC FOREIGN KEY (sessionid) REFERENCES $this->table_name_session (id) ON DELETE CASCADE ON UPDATE CASCADE                        
            ) $charset_collate;";
            $this->db->query($sql);


            $current_datetime = current_time('mysql');
            $this->db->insert($this->table_name_settings, [
                'setting' => 'ddl_version',
                'value_numeric' => 1,
            ]);
            $this->db->insert($this->table_name_settings, [
                'setting' => 'installation_date',
                'value_datetime' => $current_datetime,
            ]);
        }

        // Check if ddl_version is 1. If so, create the initial indexes
        $ddl_version = $this->getDDLVersion();
        if ($ddl_version == 1) {

            $this->logger->info("createOrUpdateDatabaseSchema(): ddl_version is 1, creating indexes");

            // Create a non unique index on the asequence column
            $sql = "CREATE INDEX IDX_ASEQUENCE ON $this->table_name_action_processed (asequence);";
            $this->db->query($sql);

            // Create a non unique index on the atime column
            $sql = "CREATE INDEX IDX_ATIME ON $this->table_name_action_processed (atime);";
            $this->db->query($sql);

            // Create a non unique index on the user_browser column
            $sql = "CREATE INDEX IDX_USER_BROWSER ON $this->table_name_session_processed (user_browser);";
            $this->db->query($sql);

            // Create a non unique index on the user_os column
            $sql = "CREATE INDEX IDX_USER_OS ON $this->table_name_session_processed (user_os);";
            $this->db->query($sql);

            // Create a non unique index on the user_language column
            $sql = "CREATE INDEX IDX_USER_LANGUAGE ON $this->table_name_session_processed (user_language);";
            $this->db->query($sql);

            // Create a non unique index on the country_name column
            $sql = "CREATE INDEX IDX_COUNTRY_NAME ON $this->table_name_session_processed (country_name);";
            $this->db->query($sql);

            // Update ddl_version to 2
            $this->setDDLVersion(2);
        }

        // Check if ddl_version is 2. If so, create a new table for the ip2location data
        $ddl_version = $this->getDDLVersion();
        if ($ddl_version == 2) {

            $this->logger->info("createOrUpdateDatabaseSchema(): ddl_version is 2, creating ip2location table");

            // Create ip2location table
            $sql = "CREATE TABLE $this->table_name_ip2location (
                ipFrom bigint(10) NOT NULL,
                ipTo bigint(10) NOT NULL,
                countryCode varchar(2) NOT NULL,
                countryName varchar(64) NOT NULL
            ) $charset_collate;";
            $this->db->query($sql);

            $filename  = ABSPATH . 'wp-content/plugins/privytics/includes/IP2LOCATION-LITE-DB1.CSV';
            $columns = ['ipFrom', 'ipTo', 'countryCode', 'countryName'];
            $this->bulkInsertCsvData($filename, $this->table_name_ip2location, $columns);

            // create a non unique index on the ipFrom column
            $sql = "CREATE INDEX IDX_IPFROM ON $this->table_name_ip2location (ipFrom);";
            $this->db->query($sql);
            // create a non unique index on the ipTo column
            $sql = "CREATE INDEX IDX_IPTO ON $this->table_name_ip2location (ipTo);";
            $this->db->query($sql);

            // Update ddl_version to 3
            $this->setDDLVersion(3);
        }

        // Check if ddl_version is 3. If so, insert two numeric values to the settings table: 'do_session_bundeling' (=1) and 'session_bundeling_seconds' (=600)
        $ddl_version = $this->getDDLVersion();
        if ($ddl_version == 3) {

            $this->logger->info("createOrUpdateDatabaseSchema(): ddl_version is 3, inserting two numeric values to the settings table");

            $this->db->insert($this->table_name_settings, [
                'setting' => 'do_session_bundeling',
                'value_numeric' => 1,
            ]);
            $this->db->insert($this->table_name_settings, [
                'setting' => 'session_bundeling_seconds',
                'value_numeric' => 600,
            ]);

            // Update ddl_version to 4
            $this->setDDLVersion(4);
        }
    }

    private function bulkInsertCsvData($csvFilePath, $tableName, $columns, $chunkSize = 1000)
    {

        $file = fopen($csvFilePath, 'r');

        if (!$file) {
            echo "Error opening the CSV file.";
            exit();
        }

        $rowCount = 0;
        $values = [];

        while (($row = fgetcsv($file, 0, ',')) !== false) {
            $escapedRow = array_map(function ($value) {
                return $this->db->_escape($value);
            }, $row);

            $values[] = "('" . implode("', '", $escapedRow) . "')";

            $rowCount++;

            if ($rowCount % $chunkSize == 0) {
                $query = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $values);
                $this->db->query($query);
                $values = [];
            }
        }

        // Insert any remaining rows
        if (!empty($values)) {
            $query = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $values);
            $this->db->query($query);
        }

        fclose($file);
    }

    public function getDDLVersion()
    {
        $sql = "SELECT value_numeric FROM {$this->table_name_settings} WHERE setting = 'ddl_version'";
        $result = $this->db->get_var($sql);
        return $result;
    }

    public function setDDLVersion($version)
    {
        $sql = "UPDATE {$this->table_name_settings} SET value_numeric = %d WHERE setting = 'ddl_version'";
        $sql = $this->db->prepare($sql, $version);
        $result = $this->db->query($sql);
        return $result;
    }

    public function get_settings()
    {
        $sql = "SELECT * FROM {$this->table_name_settings}";
        $results = $this->db->get_results($sql, ARRAY_A);
        return $results;
    }

    public function processLiveData()
    {
        require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
        $logger = new PrivyticsLogger();
        $logger->info('Processing live data');

        $processed_sessions = 0;
        $processed_actions = 0;

        try {
            // query all rows from the table 'privytics_session'
            $logger->info(' - querying sessions');
            $rows = $this->db->get_results("SELECT * FROM $this->table_name_session WHERE is_processed = 0");
            $processed_sessions = count($rows);
            // loop through the rows
            foreach ($rows as $row) {
                // get the IP address
                $ip = $row->remote_addr;
                // get the country name
                $countryName = $this->getCountryName($ip);
                // get the referrer
                $referrer = $this->getCleanedReferrer($row->referrer);

                // insert the row into the table 'table_name_session_processed' with the values from the row in 'privytics_session'
                $this->db->insert(
                    $this->table_name_session_processed,
                    array(
                        'id' => $row->id,
                        'stime' => $row->stime,
                        'user_os' => $row->user_os,
                        'user_browser' => $row->user_browser,
                        'user_language' => $row->user_language,
                        'country_name' => $countryName,
                        'referrer' => $referrer
                    )
                );

                // update the row in 'privytics_session' to set is_processed to 1
                $this->db->update(
                    $this->table_name_session,
                    array(
                        'is_processed' => 1
                    ),
                    array(
                        'id' => $row->id
                    )
                );
            }

            // query all rows from the table 'privytics_action'
            $logger->info(' - querying actions');
            $rows = $this->db->get_results("SELECT * FROM $this->table_name_action WHERE is_processed = 0");
            $processed_actions = count($rows);
            // loop through the rows
            foreach ($rows as $row) {

                // Check if do_session_bundeling is enabled
                $do_session_bundeling = $this->db->get_var("SELECT value_numeric FROM {$this->table_name_settings} WHERE setting = 'do_session_bundeling'");
                if ($do_session_bundeling == 1 && $row->asequence == 1) {
                    // get the session_bundeling_seconds
                    $session_bundeling_seconds = $this->db->get_var("SELECT value_numeric FROM {$this->table_name_settings} WHERE setting = 'session_bundeling_seconds'");
                    
                    // get the session row from the table 'privytics_session' for the sessionid of the row in 'privytics_action'
                    $sql = "SELECT * FROM $this->table_name_session WHERE id = %d";
                    $sql = $this->db->prepare($sql, $row->sessionid);
                    $sessionRow = $this->db->get_row($sql);

                    // Check if there is an older session than $row->sessionid 
                    // with the same remote_addr, user_os, user_browser and user_language within the session_bundeling_seconds time frame
                    $sql = "SELECT * FROM $this->table_name_session WHERE id < %d AND remote_addr = %s AND user_os = %s AND user_browser = %s AND user_language = %s AND stime >= DATE_SUB(%s, INTERVAL %d SECOND)";
                    $sql = $this->db->prepare($sql, $row->sessionid, $sessionRow->remote_addr, $sessionRow->user_os, $sessionRow->user_browser, $sessionRow->user_language, $sessionRow->stime, $session_bundeling_seconds);
                    $startingSessionRow = $this->db->get_row($sql);
                    if ($startingSessionRow) {
                        // get the previous row in the action processed table (same session id biggest id) and use it's pageid as the prevpageid and asequence + 1 as the asequence
                        $sql = "SELECT * FROM $this->table_name_action_processed WHERE sessionid = %d ORDER BY id DESC LIMIT 1";
                        $sql = $this->db->prepare($sql, $startingSessionRow->id);
                        $previousActionRow = $this->db->get_row($sql);
                        if ($previousActionRow) {
                            $row->sessionid = $startingSessionRow->id;
                            $row->prevpageid = $previousActionRow->pageid;
                            $row->asequence = $previousActionRow->asequence + 1;
                        }
                    }
                }

                // insert the row into the table 'table_name_action_processed' with the values from the row in 'privytics_action'
                $this->db->insert(
                    $this->table_name_action_processed,
                    array(
                        'id' => $row->id,
                        'atime' => $row->atime,
                        'atype' => $row->atype,
                        'sessionid' => $row->sessionid,
                        'pageid' => $row->pageid,
                        'prevpageid' => $row->prevpageid,
                        'asequence' => $row->asequence
                    )
                );

                // update the row in 'privytics_action' to set is_processed to 1
                $this->db->update(
                    $this->table_name_action,
                    array(
                        'is_processed' => 1
                    ),
                    array(
                        'id' => $row->id
                    )
                );
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }

        $logger->info('Processed ' . $processed_sessions . ' sessions and ' . $processed_actions . ' actions');
    }

    private function getCountryName($ip)
    {
        // continue if the IP address is empty
        if (empty($ip)) {
            return "-";
        }
        // continue if the IP address is not IP v4
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return "-";
        }
        // convert the IP address to a long integer
        $ipLong = $this->Dot2LongIP($ip);

        // select the row from the table 'privytics_ip2location' where the ipFrom <= $ip and ipTo >= $ip
        $sql = "SELECT * FROM $this->table_name_ip2location WHERE ipFrom <= %d AND ipTo >= %d";
        $sql = $this->db->prepare($sql, $ipLong, $ipLong);
        $ip2locationRow = $this->db->get_row($sql);
        // if the row was found then get the country name        
        if ($ip2locationRow) {
            return $ip2locationRow->countryName;
        }

        return "-";
    }

    function getCleanedReferrer($referrer)
    {
        // Trim leading 'http://' or 'https://' from the referrer
        if (substr($referrer, 0, 7) == 'http://') {
            $referrer = substr($referrer, 7);
        } else if (substr($referrer, 0, 8) == 'https://') {
            $referrer = substr($referrer, 8);
        }

        // Trim leading 'www.' from the referrer
        if (substr($referrer, 0, 4) == 'www.') {
            $referrer = substr($referrer, 4);
        }

        // Trim all trailing '/' from the referrer
        while (substr($referrer, -1) == '/') {
            $referrer = substr($referrer, 0, -1);
        }


        return $referrer;
    }

    function Dot2LongIP($IPaddr)
    {
        if ($IPaddr == "") {
            return 0;
        } else {
            $ips = explode(".", "$IPaddr");
            return ($ips[3] + $ips[2] * 256 + $ips[1] * 256 * 256 + $ips[0] * 256 * 256 * 256);
        }
    }
}
