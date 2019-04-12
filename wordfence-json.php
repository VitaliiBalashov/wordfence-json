<?php

/*
 * ToDo: Fill missed fields
Plugin Name: Wordfence JSON
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Sends Wordfence notifications in JSON to listener of your vulnerability management system
Version: 1.1
Author: Vitalii_Balashov
Author URI: mailto://vitalii.balashov@dowjones.com
License: A "Slug" license name e.g. GPL2
*/

global $wordfence_json_version;
$wordfence_json_version = "1.0";

$recommendations = array (
	'checkHowGetIPs' => null, 
	'checkSpamIP' => null, 
	'commentBadURL' => "Please, pay your attention and verify that commented URL is safe.", 
	'configReadable' => "Make sure that all permissions set to prevent reading config files by unauthorized person.", 
	'coreUnknown' => null, 
	'database' => null, 
	'diskSpace' => "Check the disk space immidiately to prevent service crush.", 
	'wafStatus' => null, 
	'dnsChange' => null, 
	'easyPassword' => "Change user's password to stronger.", 
	'file' => "Review the file content and make a decision: remove or mark as False Positive.", 
	'geoipSupport' => null, 
	'knownfile' => null, 
	'optionBadURL' => null, 
	'postBadTitle' => null, 
	'postBadURL' => null, 
	'publiclyAccessible' => null, 
	'spamvertizeCheck' => null, 
	'suspiciousAdminUsers' => null, 
	'timelimit' => null, 
	'wfPluginAbandoned' => "Please make sure that plugin is secure. Find live alternatives as Plan B recommended.", 
	'wfPluginRemoved' => null, 
	'wfPluginUpgrade' => "Update plugin to the latest version", 
	'wfPluginVulnerable' => "To fix security issue update plugin to he latest version. In case of no security updates from plugin author, contact to product security team.", 
	'wfThemeUpgrade' => "Update Theme to the latest version.", 
	'wfUpgrade' => "Update Wordfence to latest version.", 
	'wpscan_directoryList' => "Disable directory listing by changing webserver configuration.", 
	'wpscan_fullPathDiscl' => "Remove full path from source code."
);

$options = array(
	'wf_table_name' => $wpdb->prefix.'wfissues',
	'wfJson_table_name' => $wpdb->prefix.'wfJson',
	'project_name' => 'wordpress_prod',
	'rp_host' => 'https://portal.dowjones.com',
	'launch' => 'regular_scan',
	'endpoint' => 'https://portal.dowjones.com/jiraservice/report'
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
};

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
	global $recommendations;
	$last_check_point = $wpdb->get_var("SELECT lastCheck FROM $options[wfJson_table_name]");

	// Fixing current timestamp to next use.
	// It prevents different timestamps in different places.

	$current_time = time();

	$wpdb->update( $options['wfJson_table_name'], array('lastCheck' => $current_time), array('ID'=>1));

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
		
	");

	if (!empty($all_issues)) {
		foreach ($all_issues as $issue) {

			if ($recommendations[$issue->type] == null) {
				// Debug purpose:
				$args = array (
					'to' => "vitalii.balashov@dowjones.com",
					'subject' => "WF JSON notification",
					'message' => "Yo, man\n\n\r",
					'message' => "wfIssuetype without description triggered:\n\r",
					'message' => $issue->shortMsg."\n\r",
					'message' => "IssueType: ".$issue->type."\n\r",
					'message' => $issue->longMsg."\n\r"
				);
				mail($args['to'], $args['subject'], $args['message']);
			}
			else {
				$issue->recommendation = $recommendations[$issue->type];
			}
			
			$original_longMsg = $issue->longMsg;
			$issue->longMsg = "!!!MARKDOWN_MODE!!!";
			$issue->longMsg .= "*SUMMARY*\n\r";
			$issue->longMsg .= $original_longMsg."\n\n\r";
			$issue->longMsg .= "*INSTANCES*: \n\r";
			$issue->longMsg .= $_SERVER['HTTP_HOST']."\n\n\r";
			$issue->longMsg .= "*RECOMMENDATION*\n\r";
			$issue->longMsg .= $issue->recommendation; // TODO: ask guys to add the field Recommendation
		}
		$all_issues = prepare_json($all_issues);
		return $all_issues;
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
 
// * Plugin can check the new issues daily or twice per day.
// * To define it we should know, when WP admin panel using by admins/users usually.
// */
//
//
add_filter( 'cron_schedules', 'cron_add_6_hours' );
function cron_add_6_hours( $schedules ) {
    $schedules['6_hours'] = array(
        'interval' => 3600*6,
        'display' => 'every 6 hours'
    );
    return $schedules;
}
//
//
//// Creating the job for WP-Cron.
//// Pay attention, that this job will run only when admin panel is open
//// ToDo: change it to daily or twice per day
//
add_action( 'wordfence_json_job', 'wordfence_json_check' );

if (!wp_next_scheduled('wordfence_json_job')) {
    $scheduled_event = wp_schedule_event(time(), '6_hours', 'wordfence_json_job');
}
