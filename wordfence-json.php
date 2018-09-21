<?php

/*
Plugin Name: Wordfence JSON
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Send Wordfence notifications in JSON to listener of your vulnerability management system
Version: 1.0
Author: Vitalii_Balashov
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

$table_name = 'wp_wfissues';

function send_new_issues($issues, $endpoint) {
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
    global $wpdb;
    $all_issues = $wpdb->get_results('SELECT time, lastUpdated, type, severity, shortMsg, LongMsg FROM wp_wfIssues WHERE status = "new"');
    if (!empty($all_issues)) {
    	return $all_issues;
    }
    else {
    	return False;
    }

    // TODO: table name to variable
}

function wordfence_json_check() {

    // Basic config

    $report_portal = 'http://10.207.33.94/monitoring/channel/32701/'; // debug

    if ($issues = get_issues()) {
	    $response = send_new_issues($issues, $report_portal);
	    return $response;
    }
}


// debug purpouses, set to daily get value from user

add_filter( 'cron_schedules', 'cron_add_one_min' );
function cron_add_one_min( $schedules ) {
    $schedules['one_min'] = array(
        'interval' => 60,
        'display' => 'every minute'
    );
    return $schedules;
}

add_action( 'wordfence_json_job', 'wordfence_json_check' );

if (!wp_next_scheduled('wordfence_json_job')) {
    $scheduled_event = wp_schedule_event(time(), 'one_min', 'wordfence_json_job');
}

//var_dump(_get_cron_array());


