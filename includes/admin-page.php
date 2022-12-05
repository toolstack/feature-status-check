<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Generate the admin page.
function psc_display( $no_update = false, $no_title = false ) {
	// Make sure our options have come in as boolean values.
	if( ! is_bool( $no_update ) ) { $no_update = false; }
	if( ! is_bool( $no_title ) ) { $no_title = false; }

	// Make sure we use an english variant of the wordpress plugin directory.
	$wp_url = 'https://en-ca.wordpress.org/plugins/';

	// Get the next cronjob schedule.
	$next_cron_job = wp_next_scheduled ( 'psc_daily_event' );

	// Make sure the cron task is scheduled.
	if( ! $next_cron_job ) {
    	wp_schedule_event( time(), 'daily', 'psc_daily_event' );
    }

    // Ouput the title unless we've been told not to.
    if( ! $no_title ) {
		echo '<h1>' . __( 'Plugin Status Check' ) . '</h1>' . PHP_EOL;
	}

	// Grab the plugin list.
	$plugins = get_plugins();

	// Define variables.
	$psc_wp_org_plugins_status = array();
	$psc_wp_org_plugins_date = 0;
	$psc_no_data_message = false;

	// Check to see if the user has requested a manual data update.
	if( $_POST['pcs_data_update'] == 'true' ) {
		// Delete the existing transient if it exists.
		delete_transient( 'psc_wp_org_plugins_status' );

		// Do a full update.
		$psc_transient = psc_get_plugin_status_transient( $plugins );
	} else {
		// Retrieve the data without updating it to see if we have some.
		$psc_transient = psc_get_plugin_status_transient( $plugins, true );

		// If we have *some* data, then update it now unless otherwise told not to.
		if( $psc_transient['timestamp'] > 0 && $no_update == false ) {
			$psc_transient = psc_get_plugin_status_transient( $plugins, $no_update );
		}
	}

	// Ask the user to do a manual update if we have *no* data yet.
	if( $psc_transient['timestamp'] == 0 ) {
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $next_cron_job );

		$psc_no_data_message = '<p>' . __( 'Data has not yet been retrieved from wordpress.org, please manually update the data or wait for it to become available on' ) . ': ' . $date_string . '</p>';
	}

	// If we have data, set out variables as appropriate.
	if( is_array( $psc_transient ) && array_key_exists( 'data', $psc_transient ) ) {
		$psc_wp_org_plugins_status = $psc_transient['data'];
		$psc_wp_org_plugins_date = $psc_transient['timestamp'];
	}

	// If we don't have a no data message, display the data, otherwise let the use know they can manually update the data.
	if( $psc_no_data_message === false ) {
		// Get the list of plugins that are currently active.
		$active_plugins = get_option( 'active_plugins' );

		// Setup the table, including the sortable flags.
		echo '<table class="psc_table sortable asc" id="psc_table">' . PHP_EOL;

		// Output the header row.
		echo '<thead>';
		echo '<th>' . __( 'Plugin Name' ) . '</th>';
		echo '<th class="no-sort">' . __( 'Version' ) . '</th>';
		echo '<th class="psc_table_cell_center_align">' . __( 'Currently Active' ) . '</th>';
		echo '<th>' . __( 'Plugin Author' ) . '</th>';
		echo '<th class="no-sort">' . __( 'Home Page' ) . '</th>';
		echo '<th class="psc_table_cell_center_align">' . __( 'Status' ) . '</th>';
		echo '<th class="psc_table_cell_center_align">' . __( 'Last Update' ) . '</th>';
		echo '<th class="psc_table_cell_center_align">' . __( 'Tested up to' ) . '</th>';
		echo '</thead>' . PHP_EOL;

		// Loop through the installed plugins.
		foreach( $plugins as $name => $plugin ) {
			// The slug should be the directory name.
			$slug = dirname( $name );

			// If we don't have data for the plugin, setup so default data.
			if( ! array_key_exists( $name, $psc_wp_org_plugins_status ) ) {
				$psc_wp_org_plugins_status[$name] = array( 'status' => 'no_data', 'latest_version' => 'unknown', 'last_updated' => 'never', 'tested_up_to' => 'unknown' );
			}

			// Construct the class to use for the status cell for this row.
			$status_class  = 'psc_status_' . esc_attr( $psc_wp_org_plugins_status[$name]['status'] );

			// Construct the class to use for the version cell for this row.
			$version_class = $psc_wp_org_plugins_status[$name]['status'] == 'out_of_date' ? ' class="psc_table_cell_center_align psc_status_out_of_date"' : ' class="psc_table_cell_center_align"';

			// Make the status more friendly.
			switch( $psc_wp_org_plugins_status[$name]['status'] ) {
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

			// Build the plugin homepage link as well as the author link if we have that data, otherwise return just what we have.
			if( $psc_wp_org_plugins_status[$name]['status'] != 'not_found' && $psc_wp_org_plugins_status[$name]['status'] != 'no_data' ) {
				$plugin_link   = '<a target="_blank" href="' . esc_attr( $wp_url . $slug ) . '">' . $plugin['Name'] . '</a>';
				$plugin_author = '<a target="_blank" href="' . esc_attr( 'https://profiles.wordpress.org/' . $plugin['Author'] ) . '">' . $plugin['Author'] .'</a>';
			} else {
				$plugin_link   = $plugin['Name'];
				$plugin_author = $plugin['Author'];
			}

			// Check to see if this plugin is actually active on the site.
			$plugin_active = in_array( $name, $active_plugins ) ? 'Yes' : '';

			// Now output the table row.
			echo '<tr>';
			echo '<td>' . $plugin_link . '</td>';
			echo '<td' . $version_class . '>' . $plugin['Version'] . '</td>';
			echo '<td class="psc_table_cell_center_align">' . $plugin_active . '</td>';
			echo '<td>' . $plugin_author . '</td>';
			echo '<td class="psc_table_cell_center_align"><a target="_blank" href="' . esc_attr( $plugin['PluginURI'] ) . '">link</a></td>';
			echo '<td class="psc_table_cell_center_align ' . $status_class . '">' . $friendly_status . '</td>';
			echo '<td class="psc_table_cell_center_align ' . $status_class . '">' . $psc_wp_org_plugins_status[$name]['last_updated'] . '</td>';
			echo '<td class="psc_table_cell_center_align ' . $status_class . '">' . $psc_wp_org_plugins_status[$name]['tested_up_to'] . '</td>';
			echo '</tr>' . PHP_EOL;

		}

		// Close the table.
		echo '</table>' . PHP_EOL;

		// Make a friendly date/time for the last data update.
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $psc_wp_org_plugins_date );

		// Let the user know when the data was last updated.
		echo '<p>' . __( 'Data last updated' ) . ': ' . $date_string . '</p>' . PHP_EOL;
	} else {
		// Display the no data message.
		echo $psc_no_data_message . PHP_EOL;
	}

	// Only display the manual update option if updates are enabled.
	if( $no_update == false ) {
		// Create a form to submit a manual data update request.
		echo '<form action="' . esc_url( admin_url( 'plugins.php?page=psc_admin_menu' ) ) . '" method="post">' . PHP_EOL;
		echo '<input type="hidden" name="pcs_data_update" value="true">' . PHP_EOL;
		submit_button( 'Manual Data Update' ) . PHP_EOL;
		echo '<span>Note: Updating the data may take a long time if you have a significant number of plugins, be paitent and wait for the page to complete its loading.</span>' . PHP_EOL;
		echo '</form>' . PHP_EOL;
	}
}

