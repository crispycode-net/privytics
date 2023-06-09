<?php

class PrivyticsLogger {
    private $logDir;

    public function __construct() {

        $upload_dir = wp_upload_dir();
        $logs_dir = $upload_dir['basedir'] . '/privytics-logs';
        
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        $this->logDir = $logs_dir;
        $this->cleanOldLogs();
    }

    private function cleanOldLogs() {
        $files = glob($this->logDir . '/*.log');
        $now   = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTimestamp = filemtime($file);
                $age = $now - $fileTimestamp;

                // If the file is older than 7 days (604800 seconds), delete it.
                if ($age > 604800) {
                    unlink($file);
                }
            }
        }
    }

    private function log($severity, $message) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $logFile = $this->logDir . "/{$date}.log";
        $logMessage = "[{$time}] [{$severity}] {$message}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public function error($message) {
        $this->log('ERROR', $message);
    }

    public function warning($message) {
        $this->log('WARNING', $message);
    }

    public function info($message) {
        $this->log('INFO', $message);
    }
}
