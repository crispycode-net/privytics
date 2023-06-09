<?php

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Set the global $wpdb variable to access the WordPress database
global $wpdb;

// Replace 'your_table_name' with the actual name of the table you want to delete
$table_name_action = $wpdb->prefix . 'privytics_action';
$table_name_session = $wpdb->prefix . 'privytics_session';
$table_name_action_processed = $wpdb->prefix . 'privytics_action_processed';
$table_name_session_processed = $wpdb->prefix . 'privytics_session_processed';
$table_name_settings = $wpdb->prefix . 'privytics_settings';

// Prepare the SQL query to drop the table
$sql = "DROP TABLE IF EXISTS `$table_name_action`;";
$wpdb->query($sql);
$sql = "DROP TABLE IF EXISTS `$table_name_session`;";
$wpdb->query($sql);
$sql = "DROP TABLE IF EXISTS `$table_name_action_processed`;";
$wpdb->query($sql);
$sql = "DROP TABLE IF EXISTS `$table_name_session_processed`;";
$wpdb->query($sql);
$sql = "DROP TABLE IF EXISTS `$table_name_settings`;";
$wpdb->query($sql);