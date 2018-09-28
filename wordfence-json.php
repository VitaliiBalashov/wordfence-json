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


$options = array(
	'wf_table_name' => $wpdb->prefix.'wfissues',
	'wfJson_table_name' => $wpdb->prefix.'wfJson',
	'project_name' => 'wordpress',
	'rp_host' => 'https://secautomation.build.pib.dowjones.io',
	'launch' => 'regular_scan',
	//'endpoint' => 'http://10.207.33.94/monitoring/channel/32701/'
	'endpoint' => 'https://secautomation.build.pib.dowjones.io/jiraservice/report'
);

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
        'sslverify' => false,
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => wp_json_encode($issues),
        'method' => 'POST'
    );
    echo "<pre>";
    var_dump($args);
    echo "</pre>";
    $response = wp_remote_post($endpoint, $args);

    return $response;
}

function prepare_json($issues) {
	global $options;
	$wrapp_array = array(
		'prj' => $options['project_name'],
		'host' => $options['rp_host'],
		'launch' => $options['launch'],
		'report' => $issues
	);

	return $wrapp_array;
}

function get_issues() {

	/*
	 * Function gets new issues. The core of plugin.
	 */

	global $wpdb;
	global $options;
	$last_check_point = $wpdb->get_var("SELECT lastCheck FROM $options[wfJson_table_name]");
	var_dump($last_check_point);

	// Fixing current timestamp to next use.
	// It prevents different timestamps in different places.

	$current_time = time();

	//$wpdb->update( $options['wfJson_table_name'], array('lastCheck' => $current_time), array('ID'=>1));

    $all_issues = $wpdb->get_results(
    	"SELECT
 					time,
 					lastUpdated, 
 					type, 
 					severity, 
 					shortMsg, 
 					longMsg 
 				FROM 
 					$options[wf_table_name] 
 				WHERE 
 					status = 'new' 
 					AND 
 					lastUpdated 
 					BETWEEN $last_check_point AND $current_time"
				);

    // Prevent sending empty array

    if (!empty($all_issues)) {
    	$all_issues = prepare_json($all_issues);
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

	global $options;

    if ($issues = get_issues()) {
	    $response = send_new_issues($issues, $options['endpoint']);
	    return $response;
    }
     /* ToDo: should the function return something in case of error?
	 *  we need to understand, how we would like to track this errors
     */

}

wordfence_json_check();

///* debug purpouses, set to daily get value from user
// * ToDo: this filter should be removed when debugging will be end.
// * Plugin can check the new issues daily or twice per day.
// * To define it we should know, when WP admin panel using by admins/users usually.
// */
//
//
//add_filter( 'cron_schedules', 'cron_add_one_min' );
//function cron_add_one_min( $schedules ) {
//    $schedules['one_min'] = array(
//        'interval' => 60,
//        'display' => 'every minute'
//    );
//    return $schedules;
//}
//
//
//// Creating the job for WP-Cron.
//// Pay attention, that this job will run only when admin panel is open
//// ToDo: change it to daily or twice per day
//
//add_action( 'wordfence_json_job', 'wordfence_json_check' );
//
//if (!wp_next_scheduled('wordfence_json_job')) {
//    $scheduled_event = wp_schedule_event(time(), 'one_min', 'wordfence_json_job');
//}
