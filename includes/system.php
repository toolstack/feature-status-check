<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Runs on activation to add the cron job.
function psc_activation() {
    wp_schedule_event( time(), 'daily', 'psc_daily_event' );
}

// Runs on de-activation to remove the cron job.
function psc_deactivation() {
	$timestamp = wp_next_scheduled( 'psc_daily_event' );

    wp_unschedule_event( $timestamp, 'psc_daily_event' );
}

// The daily cron job callback.
function psc_daily_event() {
    // Grab the install plugin list, used later to make things look pretty for the end user.
    $plugins = get_plugins();


    psc_get_plugin_status_transient( $plugins );
}

// Add our menu item.
function psc_admin_menu() {
	add_submenu_page( 'plugins.php', 'Plugin Status Check', 'Status Check', 'install_plugins', 'psc_admin_menu', 'psc_display' );
}

function psc_css_and_js( $hook ) {
 	$current_screen = get_current_screen();

 	// If we're not on the phc admin screen, don't enqueue anything.
    if( strpos($current_screen->base, 'psc_admin_menu') === false && strpos($current_screen->base, 'site-health') === false ) {
        return;
    }

	// Enqueue our css.
	wp_enqueue_style( 'phc-css', plugins_url( 'css/psc.css', PHC_PLUGIN_FILE ), array(), PHC_VERSION );

	// Load the table sorter from https://github.com/tofsjonas/sortable
	wp_enqueue_style( 'phc-sortable', plugins_url( 'css/sortable-base.min.css', PHC_PLUGIN_FILE ), array(), PHC_VERSION );
	wp_enqueue_script( 'phc-sortable', plugins_url( 'js/sortable.js', PHC_PLUGIN_FILE ), array(), PHC_VERSION );
}

// Gets the slug for a given plugin name.
function pcs_get_plugin_slug( $name ) {
    // Most plugins will be in the format "plugin_slug/slug.php", some will be in "plugin_slug/random_name.php" (old pluings mostly),
    // and a very few will be in the format of "slug.php" (plugins that are a single file and contained in the "plugins" directly only).
    // There could be edge cases where the directory name isn't the slug, but this is uncommon to say the least.
    // So let's see if the $name has a directory separator in it, if so, assume the first two formats, if not, just grab the basename.
    if( str_contains( $name, DIRECTORY_SEPARATOR ) ) {
        $slug = dirname( $name );
    } else {
        $slug = basename( $name, '.php' );
    }

    return $slug;
}
