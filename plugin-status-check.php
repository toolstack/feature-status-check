<?php
/*
Plugin Name: Plugin Status Check
Version: 1.0
Plugin URI: http://toolstack.com/plugin-status-check
Author: Greg Ross
Author URI: http://toolstack.com
Description: Checks to see if the plugins you have on your site are still supported in the plugin directory.

Compatible with WordPress 3.5+.

Read the accompanying readme.txt file for instructions and documentation.

Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

DEFINE( 'PHC_VERSION', '1.0' );
DEFINE( 'PHC_PLUGIN_FILE', __FILE__ );

require_once( 'includes/admin-page.php' );
require_once( 'includes/data.php' );
require_once( 'includes/site-health.php' );
require_once( 'includes/system.php' );

// Add our menu item to the plugins menu.
add_action( 'admin_menu', 'psc_admin_menu' );

// Load our css and javascript.
add_action( 'admin_enqueue_scripts', 'psc_css_and_js');

// Setup a cron job to update the plugin info.
add_action( 'psc_daily_event', 'psc_daily_event' );
register_activation_hook( PHC_PLUGIN_FILE, 'psc_activation' );
register_deactivation_hook( PHC_PLUGIN_FILE, 'psc_daily_event' );

// Add a filter to hook into the site heath check.
add_filter( 'site_status_tests', 'psc_add_plugin_status_test' );
add_filter( 'site_health_navigation_tabs', 'psc_add_status_tab' );
add_action( 'site_health_tab_content', 'psc_add_status_tab_content' );