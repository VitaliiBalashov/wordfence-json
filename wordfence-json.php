<?php

/*
 * ToDo: Fill missed fields
Plugin Name: Wordfence JSON
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Send Wordfence notifications in JSON to listener of your vulnerability management system
Version: 1.0
Author: Vitalii_Balashov
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

global $wordfence_json_version;
$wordfence_json_version = "1.0";
$table_name = 'wp_wfIssues';

function wordfence_json_install() {

	/*
	 * Initial function.
	 * Use for prepare plugin actiavation.
	 */

	global $wpdb;

	// We create table "wfJson" in WP DB to store timestamp of last check

	$wf_json_tablename = $wpdb->prefix. "wfJson";
	if ($wpdb->get_var("SHOW TABLES LIKE '$wf_json_tablename'") != $wf_json_tablename) {
		$sql = "CREATE TABLE " . $wf_json_tablename . " (
	        id mediumint(9) NOT NULL AUTO_INCREMENT,
	        lastCheck bigint(11) DEFAULT '0' NOT NULL,
	        UNIQUE KEY id (id)
		  );";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		// Set first lastcheck timestamp to 1 year ago.
		$wpdb->insert($wf_json_tablename, array('lastCheck'=>time() - (365*24*60*60)));
	}
	//ToDo: add error handling and return something
}

register_activation_hook(__FILE__,'wordfence_json_install');

function send_new_issues($issues, $endpoint) {

	/*
	 * Get array with issues, serialize to JSON and send to endpoint using WP native methods.
	 * Function returns the response of HTTP request.
	 * If you want to add any HTTP headers to request, you should do it here.
	 */

    $args = array (
        'timeout' => 45,
        'blocking' => false,
        'redirection' => 5,
        'httpversion' => '1.0',
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => wp_json_encode($issues),
        'method' => 'POST'
    );
    $response = wp_remote_post($endpoint, $args);

    return $response;
}

function get_issues() {

	/*
	 * Function gets new issues. The core of plugin.
	 */

	global $wpdb;
	// ToDo: rewrite next line to get table name from variable
	$last_check_point = $wpdb->get_var("SELECT lastCheck FROM wp_wfJson" );

	// Fixing current timestamp to next use.
	// It prevents different timestamps in different places.

	$current_time = time();

	// ToDo: rewrite next line to get table name from variable
	$wpdb->update( 'wp_wfJson', array('lastCheck' => $current_time), array('ID'=>1));

	// ToDo: rewrite next line to get tablename from variable
    $all_issues = $wpdb->get_results("SELECT time, lastUpdated, type, severity, shortMsg, LongMsg FROM wp_wfIssues WHERE status = 'new' AND lastUpdated BETWEEN $last_check_point AND $current_time");

    // Prevent sending empty array

    if (!empty($all_issues)) {
    	return $all_issues;
    }
    else {
    	return False;
    }
}

function wordfence_json_check() {

    /*
     * Main function. It runs the process
     */

    // Define the endpoint.
	// The endpoint is the receiver of sending JSONs


    $report_portal = 'http://10.207.33.94/monitoring/channel/32701/'; // debug

    if ($issues = get_issues()) {
	    $response = send_new_issues($issues, $report_portal);
	    return $response;
    }
    // ToDo: should the function return something in case of error?
	// ToDo: we need to understand, how we would like to track this errors

}


// debug purpouses, set to daily get value from user


// ToDo: this filter should be removed when debugging will be end.
// ToDo: Plugin can check the new issues daily or twice per day.
// ToDo: To define it we should know, when WP admin panel using by admins/users usually.


add_filter( 'cron_schedules', 'cron_add_one_min' );
function cron_add_one_min( $schedules ) {
    $schedules['one_min'] = array(
        'interval' => 60,
        'display' => 'every minute'
    );
    return $schedules;
}


// Creating the job for WP-Cron.
// Pay attention, that this job will run only when admin panel is open
// ToDo: change it to daily or twice per day

add_action( 'wordfence_json_job', 'wordfence_json_check' );

if (!wp_next_scheduled('wordfence_json_job')) {
    $scheduled_event = wp_schedule_event(time(), 'one_min', 'wordfence_json_job');
}
