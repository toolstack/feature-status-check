<?php
/*
Plugin Name: Feature Status Check
Version: 1.1
Plugin URI: http://toolstack.com/feature-status-check
Author: Greg Ross
Author URI: http://toolstack.com
Description: Checks to see if the plugins you have on your site are still supported in the plugin directory.

Compatible with WordPress 3.5+.

Read the accompanying readme.txt file for instructions and documentation.

Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

DEFINE( 'FSC_VERSION', '1.0' );
DEFINE( 'FSC_PLUGIN_FILE', __FILE__ );

require_once( 'includes/system.php' );
require_once( 'includes/data.php' );
require_once( 'includes/admin-page.php' );
require_once( 'includes/settings-page.php' );
require_once( 'includes/site-health.php' );

// Add our menu item to the plugins menu.
add_action( 'admin_menu', 'fsc_admin_menu' );

// Adds the options page to the settings menu.
add_action( 'admin_menu', 'fsc_add_options_page' );

// Load our css and javascript.
add_action( 'admin_enqueue_scripts', 'fsc_css_and_js');

// Setup a cron job to update the plugin info.
add_action( 'fsc_daily_event', 'fsc_daily_event' );
register_activation_hook( FSC_PLUGIN_FILE, 'fsc_activation' );
register_deactivation_hook( FSC_PLUGIN_FILE, 'fsc_daily_event' );

// Add a filter to hook into the site heath check.
add_filter( 'site_status_tests', 'fsc_add_plugin_status_test' );
add_filter( 'site_health_navigation_tabs', 'fsc_add_status_tab' );
add_action( 'site_health_tab_content', 'fsc_add_status_tab_content' );
