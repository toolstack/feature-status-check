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

    // First get the transient without updating it so we can use it to compare against later.
    $old_pcs_transient = psc_get_plugin_status_transient( $plugins, true );

    // Now perform the update.
    $new_pcs_transient = psc_get_plugin_status_transient( $plugins );

    // Make sure we had some status to start with, if we don't there's no point trying to determine the differences, so just return now.
    if( ! is_array( $old_pcs_transient ) || ! array_key_exists( 'data', $old_pcs_transient ) || count( $old_pcs_transient['data'] ) == 0 ) {
        return;
    }

    // Let's see if we added any plugins.
    $new_plugins = false;

    foreach( $plugins as $name => $plugin ) {
        if( ! array_key_exists( $name, $old_pcs_transient['data'] ) ) {
            $new_plugins[] = $name;
        }
    }

    // Let's see if any plugins have a new status.
    $status_changed = false;

    foreach( $plugins as $name => $plugin ) {
        if( array_key_exists( $name, $old_pcs_transient['data'] ) && $old_pcs_transient['data'][$name]['status'] != $new_pcs_transient['data'][$name]['status'] ) {
            $status_changed[$name] = $new_pcs_transient['data'][$name]['status'];
        }
    }

    if( is_array( $new_plugins ) || is_array( $status_changed ) ) {
        // Get the blogs's name
        $blogname = get_bloginfo('name');

        // Get the admin's e-mail address.
        $blogemail = get_bloginfo('admin_email');

        // Create a subject line.
        $subject = __( 'Plugin Status Check - Some plugins have a changed status...' );

        // Set the headers to include a pretty from line.
        $headers[] = "From: $blogname <$blogemail>";

        // Construct the message body.
        $message  = __( 'Some plugins have a changed status, see below for details.' . "\r\n" );
        $message .= "\r\n";

        // Include new plugins we found during this update.
        if( is_array( $new_plugins ) ) {
            $message .= __( 'New plugins:' ) . "\r\n";
            foreach( $new_plugins as $name ) {
                $message .= "\t* " . $plugins[$name]['Name'] . ' (' . pcs_pretty_status( $new_pcs_transient['data'][$name]['status'] ) . ')' . "\r\n";
            }
        }

        $message .= "\r\n";

        // Include plugins with a changed status.
        if( is_array( $status_changed ) ) {
            $message .= __( 'Plugins with new status:' ) . "\r\n";
            foreach( $status_changed as $name => $new_status ) {
                $message .= "\t* " . $plugins[$name]['Name'] . ' (' . pcs_pretty_status( $new_status ) . ')' . "\r\n";
            }
        }

        $message .= "\r\n";
        $message .= "\r\n";
        $message .= "----------\r\n";
        $message .= "$blogname\r\n";

        wp_mail( $blogemail, $subject, $message, $headers );
    }
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

// Make the status look human readable.
function pcs_pretty_status( $status ) {
    // Make the status more friendly.
    switch( $status ) {
        case 'up_to_date':
            $friendly_status = __( 'Up to Date' );
            break;
        case 'out_of_date':
            $friendly_status = __( 'Out of Date' );
            break;
        case 'untested':
            $friendly_status = __( 'Un-Tested' );
            break;
        case 'not_found':
            $friendly_status = __( 'Not Found' );
            break;
        case 'closed':
            $friendly_status = __( 'Closed' );
            break;
        case 'temp_closed':
            $friendly_status = __( 'Temporarily Closed' );
            break;
        default:
            $friendly_status = ucwords( str_replace('_', ' ', $psc_wp_org_plugins_status[$name]['status'] ) );
    }

    return $friendly_status;
}