<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Runs on activation to add the cron job.
function fsc_activation() {
    // Schedule the cron job to be 60 seconds in the future.
    wp_schedule_event( time() + 60 , 'daily', 'fsc_daily_event' );
}

// Runs on de-activation to remove the cron job.
function fsc_deactivation() {
    $timestamp = wp_next_scheduled( 'fsc_daily_event' );

    wp_unschedule_event( $timestamp, 'fsc_daily_event' );
}

// The daily cron job callback.
function fsc_daily_event() {
    // Grab the installed plugin list, used later to make things look pretty for the end user.
    $plugins = get_plugins();

    // Grab the installed theme list.
    $themes = wp_get_themes();

    // First get the transient without updating it so we can use it to compare against later.
    $old_fsc_transient = fsc_get_status_transient( $plugins, $themes, true );

    // Now perform the update.
    $new_fsc_transient = fsc_get_status_transient( $plugins, $themes );

    // Make sure we had some status to start with, if we don't there's no point trying to determine the differences, so just return now.
    if( ! is_array( $old_fsc_transient ) ||
        ! array_key_exists( 'plugins', $old_fsc_transient ) ||
        count( $old_fsc_transient['plugins'] ) == 0 ) {
        return;
    }

    // Get the options.
    $options = get_option( 'plugin_status_check' );

    // Set defaults if we don't have any.
    if( ! is_array( $options ) ) { $options = array( 'email-enabled' => true ); update_option( 'plugin_status_check', $options ); }

    // Check to see if the status e-mail has been disabled by the admin, if so just return now.
    if( $options['email-enabled'] == false ) { return; }

    // Let's see if we added any plugins.
    $new_plugins = false;

    foreach( $plugins as $name => $plugin ) {
        if( ! array_key_exists( $name, $old_fsc_transient['plugins'] ) ) {
            $new_plugins[] = $name;
        }
    }

    // Let's see if any plugins have a new status.
    $status_changed = false;

    foreach( $plugins as $name => $plugin ) {
        if( array_key_exists( $name, $old_fsc_transient['plugins'] ) && $old_fsc_transient['plugins'][$name]['status'] != $new_fsc_transient['plugins'][$name]['status'] ) {
            $status_changed[$name] = $new_fsc_transient['plugins'][$name]['status'];
        }
    }

    if( is_array( $new_plugins ) || is_array( $status_changed ) ) {
        // Get the blogs's name
        $blogname = get_bloginfo('name');

        // Get the admin's e-mail address.
        $blogemail = get_bloginfo('admin_email');

        // Create a subject line.
        $subject = __( 'Feature Status Check - Some plugins have a changed status...', 'feature-status-check' );

        // Set the headers to include a pretty from line.
        $headers[] = "From: $blogname <$blogemail>";

        // Construct the message body.
        $message  = __( 'Some plugins have a changed status, see below for details.', 'feature-status-check' ) . "\r\n";
        $message .= "\r\n";

        // Include new plugins we found during this update.
        if( is_array( $new_plugins ) ) {
            $message .= __( 'New plugins', 'feature-status-check' ) . ":\r\n";
            foreach( $new_plugins as $name ) {
                $message .= "\t* " . $plugins[$name]['Name'] . ' (' . fsc_pretty_status( $new_fsc_transient['plugins'][$name]['status'] ) . ')' . "\r\n";
            }
        }

        $message .= "\r\n";

        // Include plugins with a changed status.
        if( is_array( $status_changed ) ) {
            $message .= __( 'Plugins with new status', 'feature-status-check' ) . ":\r\n";
            foreach( $status_changed as $name => $new_status ) {
                $message .= "\t* " . $plugins[$name]['Name'] . ' (' . fsc_pretty_status( $new_status ) . ')' . "\r\n";
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
function fsc_admin_menu() {
    add_submenu_page( 'themes.php', 'Feature Status Check', 'Status Check', 'install_themes', 'fsc_admin_appearance_menu', 'fsc_display' );
    add_submenu_page( 'plugins.php', 'Feature Status Check', 'Status Check', 'install_plugins', 'fsc_admin_plugins_menu', 'fsc_display' );
}

function fsc_css_and_js( $hook ) {
    $current_screen = get_current_screen();

    // If we're not on the phc admin screen, don't enqueue anything.
    if( strpos($current_screen->base, 'fsc_admin_plugins_menu') === false &&        // The Plugins->Status Check page.
        strpos($current_screen->base, 'fsc_admin_appearance_menu') === false &&     // The Appearance->Status Check Page.
        strpos($current_screen->base, 'site-health') === false &&                   // The Site Health Page.
        strpos($current_screen->base, 'feature-status-check') === false ) {         // The Feature Status Check Settings page.
        return;
    }

    // Load the jquery tabs ui elements.
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-tabs');

    // Enqueue our css.
    wp_enqueue_style( 'phc-css', plugins_url( 'css/fsc.css', FSC_PLUGIN_FILE ), array(), FSC_VERSION );
    wp_enqueue_style( 'jquery-ui-1.10.4.custom', plugins_url( 'css/jquery-ui-1.10.4.custom.css', FSC_PLUGIN_FILE ), array(), FSC_VERSION );
    wp_enqueue_style( 'jquery-ui-tabs', plugins_url( 'css/jquery-ui-tabs.css', FSC_PLUGIN_FILE ), array(), FSC_VERSION );


    // Load the table sorter from https://github.com/tofsjonas/sortable
    wp_enqueue_style( 'phc-sortable', plugins_url( 'css/sortable-base.min.css', FSC_PLUGIN_FILE ), array(), FSC_VERSION );
    wp_enqueue_script( 'phc-sortable', plugins_url( 'js/sortable.js', FSC_PLUGIN_FILE ), array(), FSC_VERSION );
}

// Gets the slug for a given plugin name.
function fsc_get_plugin_slug( $name ) {
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
function fsc_pretty_status( $status ) {
    // Make the status more friendly.
    switch( $status ) {
        case 'up_to_date':
            $friendly_status = __( 'Up to Date', 'feature-status-check' );
            break;
        case 'out_of_date':
            $friendly_status = __( 'Out of Date', 'feature-status-check' );
            break;
        case 'untested':
            $friendly_status = __( 'Un-Tested', 'feature-status-check' );
            break;
        case 'not_found':
            $friendly_status = __( 'Not Found', 'feature-status-check' );
            break;
        case 'closed':
            $friendly_status = __( 'Closed', 'feature-status-check' );
            break;
        case 'temp_closed':
            $friendly_status = __( 'Temporarily Closed', 'feature-status-check' );
            break;
        default:
            $friendly_status = ucwords( str_replace('_', ' ', $fsc_wp_org_plugins_status[$name]['status'] ) );
    }

    return $friendly_status;
}

// Make the status look human readable.
function fsc_pretty_version( $version ) {
    // Make the status more friendly.
    switch( $version ) {
        case 'na':
            $friendly_version = __( 'N/A', 'feature-status-check' );
            break;
        default:
            $friendly_version = $version;
    }

    return $friendly_version;
}

// Add us to the settings menu.
function fsc_add_options_page() {
    $page = add_options_page( __( 'Feature Status Check', 'feature-status-check' ), __( 'Feature Status Check', 'feature-status-check' ), 'manage_options', FSC_PLUGIN_FILE, 'fsc_options_page' );
}
