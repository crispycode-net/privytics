<?php
/*
* Plugin Name: Privytics
* Text Domain: privytics
* Description: GDPR Compliant Website Statistics. Privytics displays the paths your visitors take through your website with a beautiful, easy-to-use interface. A Sankey diagram shows you the most common paths and the most popular pages. With Privytics, you can make data-driven decisions without compromising your users' privacy. No personal data will be stored after the data processing is completed.
* Version: 1.0.0
* Plugin URI: http://crispycode.net/
* Author: Alexander Bartz
* Author URI: http://crispycode.net/
* License: MIT
* License URI: https://opensource.org/licenses/MIT
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('PRIVYTICS_VERSION', '1.0.0');
define('PRIVYTICS_DIR', 'privytics');


function track_page_load() {
    try {

        if (is_admin() || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || is_preview())
            return; // If it's the admin area, autosaving, or preview, exit the function.
        
        if (isset($_GET['action'])  && $_GET['action'] === 'edit')
            return;

        if (is_bot())
            return;
            
        if(current_user_can('edit_posts'))
            return;
            
        global $post;
        if (!$post)
            return;
        
        session_start();

        global $wpdb;
        $table_name_session = $wpdb->prefix . 'privytics_session';
        $table_name_action = $wpdb->prefix . 'privytics_action';

        $sessionid = 0;
        $asequence = 1;
        $prevpageid = '';

        if (isset($_SESSION['asequence'])) {
            $asequence = $_SESSION['asequence'];
            $asequence++;
        }
        $_SESSION['asequence'] = $asequence;

        if (isset($_SESSION['prevpageid'])) {
            $prevpageid = $_SESSION['prevpageid'];
        }

        if (isset($_SESSION['sessionid'])) {
            $sessionid = $_SESSION['sessionid'];
        } else {
            // Session not found: Create new db entry
            $data = array(
                'user_os' => get_user_os(),
                'user_browser' => get_user_browser(),
                'user_language' => get_primary_language(),
                'remote_addr' => $_SERVER['REMOTE_ADDR'],
                'referrer' => getReferrer()
            );
            $wpdb->insert($table_name_session, $data);
            $sessionid = $wpdb->insert_id;

            $_SESSION['sessionid'] = $sessionid;
        }


        // Define the data to insert
        $internalId = getUniquePageName($post);     
        $data = array(
            'atype' => 'page_view',
            'sessionid' => $sessionid,
            'pageid' => $internalId,
            'prevpageid' => $prevpageid,
            'asequence' => $asequence
        );
        $wpdb->insert($table_name_action, $data);

        $_SESSION['prevpageid'] = $internalId;
    } catch (Exception $e) {
    }
}

function getUniquePageName($post)
{
    if (!$post) return 'unknown';

    $pageTitle ='unknown';
    if ($post->post_name)
        $pageTitle = $post->post_name;
    else if ($post->post_title)
        $pageTitle = $post->post_title;

    return mb_strimwidth($pageTitle, 0, 50, '');
}

function is_bot() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $bot_patterns = array(
        'bot',
        'crawl',
        'spider',
        'slurp',
        'yahoo',
        'mediapartners-google',
        'ia_archiver',
        'wget',
        'curl',
        'rogerbot',
        'AhrefsBot'
    );

    foreach ($bot_patterns as $bot_pattern) {
        if (stripos($user_agent, $bot_pattern) !== false) {
            return true;
        }
    }

    return false;
}


function get_user_os()
{
    global $is_iphone;

    if ($is_iphone)
        return 'iPhone';

    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $os_platform = "Unknown OS";

    $os_array = [
        '/windows nt 10/i'      => 'Windows 10/11',
        '/windows nt 6.3/i'     => 'Windows 8.1',
        '/windows nt 6.2/i'     => 'Windows 8',
        '/windows nt 6.1/i'     => 'Windows 7',
        '/windows nt 6.0/i'     => 'Windows Vista',
        '/windows nt 5.2/i'     => 'Windows Server 2003/XP x64',
        '/windows nt 5.1/i'     => 'Windows XP',
        '/windows xp/i'         => 'Windows XP',
        '/windows nt 5.0/i'     => 'Windows 2000',
        '/windows me/i'         => 'Windows ME',
        '/win98/i'              => 'Windows 98',
        '/win95/i'              => 'Windows 95',
        '/win16/i'              => 'Windows 3.11',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/mac_powerpc/i'        => 'Mac OS 9',
        '/linux/i'              => 'Linux',
        '/ubuntu/i'             => 'Ubuntu',
        '/iphone/i'             => 'iPhone',
        '/ipod/i'               => 'iPod',
        '/ipad/i'               => 'iPad',
        '/android/i'            => 'Android',
        '/blackberry/i'         => 'BlackBerry',
        '/webos/i'              => 'WebOS',
    ];

    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
            break;
        }
    }

    return $os_platform;
}

function get_user_browser()
{
    global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_edge;

    if ($is_lynx)
        return 'Lynx';
    else if ($is_gecko)
        return 'Gecko';
    else if ($is_opera)
        return 'Opera';
    else if ($is_NS4)
        return 'Netscape';
    else if ($is_safari)
        return 'Safari';
    else if ($is_chrome)
        return 'Chrome';
    else if ($is_edge)
        return 'Edge';
    else if ($is_IE)
        return 'Internet Explorer';

    $user_agent = $_SERVER['HTTP_USER_AGENT'];    

    $browser_array = [
        '/msie/i'      => 'Internet Explorer',
        '/firefox/i'   => 'Firefox',
        '/safari/i'    => 'Safari',
        '/chrome/i'    => 'Chrome',
        '/edge/i'      => 'Edge',
        '/opera/i'     => 'Opera',
        '/netscape/i'  => 'Netscape',
        '/maxthon/i'   => 'Maxthon',
        '/konqueror/i' => 'Konqueror',
        '/mobile/i'    => 'Handheld Browser'
    ];

    $browser = "unknown";
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser = $value;
            break;
        }
    }

    return $browser;
}

function get_primary_language()
{
    $accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';

    // If the HTTP_ACCEPT_LANGUAGE header is empty, return 'unknown'
    if (empty($accept_language)) {
        return 'unknown';
    }

    // Split the string by comma to get individual languages with their quality values
    $languages = explode(',', $accept_language);

    // Extract the primary language from the first item in the array
    $primary_language = substr($languages[0], 0, 2);

    return $primary_language;
}

function getReferrer()
{
    if (isset($_SERVER['HTTP_REFERER'])) {
        return $_SERVER['HTTP_REFERER'];
    } else {
        return '';
    }
}

// Activation hook
function privytics_plugin_install()
{
    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-data-access.php');
    $data = new PrivyticsDataAccess();
    $data->createOrUpdateDatabaseSchema();
}

// Register the activation hook
register_activation_hook(__FILE__, 'privytics_plugin_install');




if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}
add_action('wp', 'track_page_load');



/**
 * Helpers
 */
require plugin_dir_path(__FILE__) . 'includes/helpers.php';


/**
 * The report plugin class
 */
require plugin_dir_path(__FILE__) . 'classes/class-privytics-wp-integration.php';

function run_ct_wp_admin_form()
{

    $plugin = new PrivyticsWPIntegration();
    $plugin->init();
}
run_ct_wp_admin_form();



/**
 * Background functions
 */
function privytics_data_processing_function() {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('privytics_data_processing_function');

    trigger_privytics_async_function();
}

function privytics_cron_schedule( $schedules ) {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('privytics_cron_schedule');

    $schedules['minutely'] = array(
        'interval' => 60,
        'display'  => 'Every Minute',
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'privytics_cron_schedule' );

function privytics_cron_activation() {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('privytics_cron_activation');

    if ( ! wp_next_scheduled( 'privytics_cron_event' ) ) {
        wp_schedule_event( time(), 'minutely', 'privytics_cron_event' );
    }
}
add_action( 'wp', 'privytics_cron_activation' );

function privytics_cron_deactivation() {
    wp_clear_scheduled_hook( 'privytics_cron_event' );
}
register_deactivation_hook( __FILE__, 'privytics_cron_deactivation' );

add_action( 'privytics_cron_event', 'privytics_data_processing_function' );


/**
 * Async backgound processing
 */
function privytics_async_function() {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('privytics_async_function');

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-data-access.php');
    $data = new PrivyticsDataAccess();
    $data->processLiveData();
}
add_action('privytics_async_action', 'privytics_async_function');

function trigger_privytics_async_function() {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('trigger_privytics_async_function');

    $url = home_url('/?privytics_async_trigger=1');
    $args = array(
        'timeout'   => 0.01,
        'blocking'  => false,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
    );

    wp_remote_post($url, $args);
}

function check_privytics_async_trigger() {

    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-logger.php');
    $logger = new PrivyticsLogger();
    $logger->info('check_privytics_async_trigger');

    if (isset($_GET['privytics_async_trigger']) && $_GET['privytics_async_trigger'] == '1') {
        $logger->info('check_privytics_async_trigger SET');
        try {
            do_action('privytics_async_action');
            // log success
            $logger->info('privytics_async_action success');
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
        exit;
    }
}
add_action('init', 'check_privytics_async_trigger');
