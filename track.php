<?php

/*
    This file is part of Privytics.
    It must be copied to the root directory of your WordPress installation.
    It is used to track page loads and user actions from pages that are not
    part of the WordPress installation.

    This is an example on how to use the track.php API to track page loads and user actions.
    The requestUrl must be changed to the correct path to the track.php file.

    async function trackPageLoad(givenPath) {
        var path = givenPath;
        if (!givenPath) {
            const currentUrl = new URL(window.location.href);
            // Get the path from the URL and remove any leading or trailing '/'
            path = currentUrl.pathname.replace(/^\/|\/$/g, '');
        }

        // Encode the path to include it in the query parameter
        const encodedPath = encodeURIComponent(path);

        // Construct the URL for the request, including the 'internalId' query parameter
        const requestUrl = `../../track.php?internalId=${encodedPath}`;

        // Fetch the data from the URL
        try {
            const response = await fetch(requestUrl);
            const data = await response.text();
        } catch (error) {
            // Handle errors in fetching the data, e.g., log the error message to the console
            console.error(`Error fetching data: ${error.message}`);
        }
    }
 */

require_once('privytics/wp-config.php');

function track_page_load_api() {
    try {
        if (is_admin() || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || is_preview())
            return; // If it's the admin area, autosaving, or preview, exit the function.        

        if (isset($_GET['action'])  && $_GET['action'] === 'edit')
            return;
            
        if(current_user_can('edit_posts'))
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

        // Check GET query parameters for page id
        $internalId = 'unknown';
        if (isset($_GET['internalId'])) {
            $internalId = $_GET['internalId'];
        }

        // Define the data to insert          
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

track_page_load_api();


?>