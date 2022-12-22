<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Generate the admin page.
function fsc_display( $no_update = false, $no_title = false ) {
	// Make sure our options have come in as boolean values.
	if( ! is_bool( $no_update ) ) { $no_update = false; }
	if( ! is_bool( $no_title ) ) { $no_title = false; }

	// URL of the wordpress directories.
	$wp_plugin_url = 'https://wordpress.org/plugins/';
	$wp_theme_url = 'https://wordpress.org/themes/';

	// Get the next cronjob schedule.
	$next_cron_job = wp_next_scheduled ( 'fsc_daily_event' );

	// Make sure the cron task is scheduled.
	if( ! $next_cron_job ) {
    	wp_schedule_event( time(), 'daily', 'fsc_daily_event' );
    }

    // Output the title unless we've been told not to.
    if( ! $no_title ) {
		echo '<h1>' . __( 'Feature Status Check', 'feature-status-check' ) . '</h1>' . PHP_EOL;
	}

	// Grab the plugin list.
	$plugins = get_plugins();

	// Grab the theme list.
	$themes = wp_get_themes();

	// Define variables.
	$fsc_wp_org_plugins_status = array();
	$fsc_wp_org_themes_status = array();
	$fsc_wp_org_date = 0;
	$fsc_no_data_message = false;

	// Check to see if the user has requested a manual data update.
	if( array_key_exists( 'fsc_data_update', $_POST ) &&  $_POST['fsc_data_update'] == 'true' ) {
		// Delete the existing transient if it exists.
		delete_transient( 'fsc_wp_org_status' );

		// Do a full update.
		$fsc_transient = fsc_get_status_transient( $plugins, $themes );
	} else {
		// Retrieve the data without updating it to see if we have some.
		$fsc_transient = fsc_get_status_transient( $plugins, $themes, true );

		// If we have *some* data, then update it now unless otherwise told not to.
		if( $fsc_transient['timestamp'] > 0 && $no_update == false ) {
			$fsc_transient = fsc_get_status_transient( $plugins, $themes, $no_update );
		}
	}

	// Ask the user to do a manual update if we have *no* data yet.
	if( $fsc_transient['timestamp'] == 0 ) {
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $next_cron_job );

		$fsc_no_data_message = '<p>' . __( 'Data has not yet been retrieved from wordpress.org, please manually update the data or wait for it to become available on', 'feature-status-check' ) . ': ' . $date_string . '</p>';
	}

	// If we have data, set out variables as appropriate.
	if( is_array( $fsc_transient ) && array_key_exists( 'plugins', $fsc_transient ) ) {
		$fsc_wp_org_plugins_status = $fsc_transient['plugins'];
		$fsc_wp_org_themes_status = $fsc_transient['themes'];
		$fsc_wp_org_date = $fsc_transient['timestamp'];
	}

	// If we don't have a no data message, display the data, otherwise let the use know they can manually update the data.
	if( $fsc_no_data_message === false ) {
		// Get the list of plugins that are currently active.
		$active_plugins = get_option( 'active_plugins' );

		// Get the active theme.
		$active_theme = get_option( 'stylesheet' );

		// Setup an output variable for the table.
		$rows = array();

		// Setup the table, including the sortable flags.
		echo '<table class="fsc_table sortable asc" id="fsc_table">' . PHP_EOL;

		// Output the header row.
		echo '<thead>';
		echo '<th id="fsc_feature_name">' . __( 'Feature Name', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_type">' . __( 'Type', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_version" class="no-sort">' . __( 'Version', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_active" class="fsc_table_cell_center_align">' . __( 'Currently Active', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_author">' . __( 'Plugin Author', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_home_page" class="no-sort">' . __( 'Home Page', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_status" class="fsc_table_cell_center_align">' . __( 'Status', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_last_update" data-sort-col="8" class="fsc_table_cell_center_align">' . __( 'Last Update', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_last_update_days" class="fsc_table_cell_hidden">' . __( 'Last Update Days', 'feature-status-check' ) . '</th>';
		echo '<th id="fsc_feature_tested_up_to" class="fsc_table_cell_center_align">' . __( 'Tested up to', 'feature-status-check' ) . '</th>';
		echo '</thead>' . PHP_EOL;

		// Loop through the installed plugins.
		foreach( $plugins as $name => $plugin ) {
			// Get the plugins slug.
			$slug = fsc_get_plugin_slug( $name );

			// If we don't have data for the plugin, setup so default data.
			if( ! array_key_exists( $name, $fsc_wp_org_plugins_status ) ) {
				$fsc_wp_org_plugins_status[$name] = array( 'status' => 'no_data', 'latest_version' => 'unknown', 'last_updated' => 'never', 'tested_up_to' => 'unknown' );
			}

			// Construct the class to use for the status cell for this row.
			$status_class  = esc_attr( 'fsc_status_' . $fsc_wp_org_plugins_status[$name]['status'] );

			// Construct the class to use for the version cell for this row.
			$version_class = $fsc_wp_org_plugins_status[$name]['status'] == 'out_of_date' ? ' class="fsc_table_cell_center_align fsc_status_out_of_date"' : ' class="fsc_table_cell_center_align"';

			// Make the status more friendly.
			$friendly_status = fsc_pretty_status( $fsc_wp_org_plugins_status[$name]['status'] );

			// Build the plugin homepage link as well as the author link if we have that data, otherwise return just what we have.
			if( $fsc_wp_org_plugins_status[$name]['status'] != 'not_found' && $fsc_wp_org_plugins_status[$name]['status'] != 'no_data' ) {
				$plugin_link   = '<a target="_blank" href="' . esc_attr( $wp_plugin_url . $slug ) . '">' . esc_html( $plugin['Name'] ). '</a>';
				$plugin_author = '<a target="_blank" href="' . esc_attr( 'https://profiles.wordpress.org/' . esc_html( $plugin['Author'] ) ) . '">' . $plugin['Author'] .'</a>';
			} else {
				$plugin_link   = esc_html( $plugin['Name'] );
				$plugin_author = esc_html( $plugin['Author'] );
			}

			// Check to see if this plugin is actually active on the site.
			$plugin_active = in_array( $name, $active_plugins ) ? 'Yes' : '';

			// Create a rowkey to use in lowercase so the ksort later works correctly.
			$row_key = strtolower( $plugin['Name'] );

			// Now output the table row.
			$rows[$row_key] = '<tr>';
			$rows[$row_key] .= '<td>' . $plugin_link . '</td>';
			$rows[$row_key] .= '<td>' . __( 'plugin', 'feature-status-check' ) . '</td>';
			$rows[$row_key] .= '<td' . $version_class . '>' . esc_html( $plugin['Version'] ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align">' . $plugin_active . '</td>';
			$rows[$row_key] .= '<td>' . $plugin_author . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align"><a target="_blank" href="' . esc_attr( $plugin['PluginURI'] ) . '">link</a></td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . $friendly_status . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . esc_html( $fsc_wp_org_plugins_status[$name]['last_updated'] ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_hidden">' . esc_html( $fsc_wp_org_plugins_status[$name]['last_updated_days'] ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . esc_html( fsc_pretty_version( $fsc_wp_org_plugins_status[$name]['tested_up_to'] ) ). '</td>';
			$rows[$row_key] .= '</tr>';

		}

		// Loop through the installed themes.
		foreach( $themes as $name => $theme ) {
			// Get the plugins slug.
			$slug = $name;

			// If we don't have data for the plugin, setup so default data.
			if( ! array_key_exists( $name, $fsc_wp_org_themes_status ) ) {
				$fsc_wp_org_themes_status[$name] = array( 'status' => 'no_data', 'latest_version' => 'unknown', 'last_updated' => 'never', 'tested_up_to' => 'na' );
			}

			// Construct the class to use for the status cell for this row.
			$status_class  = esc_attr( 'fsc_status_' . $fsc_wp_org_themes_status[$name]['status'] );

			// Construct the class to use for the version cell for this row.
			$version_class = $fsc_wp_org_themes_status[$name]['status'] == 'out_of_date' ? ' class="fsc_table_cell_center_align fsc_status_out_of_date"' : ' class="fsc_table_cell_center_align"';

			// Make the status more friendly.
			$friendly_status = fsc_pretty_status( $fsc_wp_org_themes_status[$name]['status'] );

			// Build the plugin homepage link as well as the author link if we have that data, otherwise return just what we have.
			if( $fsc_wp_org_themes_status[$name]['status'] != 'not_found' && $fsc_wp_org_themes_status[$name]['status'] != 'no_data' ) {
				$theme_link   = '<a target="_blank" href="' . esc_attr( $wp_theme_url . $slug ) . '">' . esc_html( $theme->get('Name') ). '</a>';
				$theme_author = '<a target="_blank" href="' . esc_attr( 'https://profiles.wordpress.org/' . esc_html( $theme->get('Author') ) ) . '">' . $theme->get('Author') .'</a>';
			} else {
				$theme_link   = esc_html( $theme->get('Name') );
				$theme_author = esc_html( $theme->get('Author') );
			}

			// Check to see if this plugin is actually active on the site.
			$theme_active = $name == $active_theme ? 'Yes' : '';

			// Create a rowkey to use in lowercase so the ksort later works correctly.
			$row_key = strtolower( $theme->get('Name') );

			// Now output the table row.
			$rows[$row_key] = '<tr>';
			$rows[$row_key] .= '<td>' . $theme_link . '</td>';
			$rows[$row_key] .= '<td>' . __( 'theme', 'feature-status-check' ) . '</td>';
			$rows[$row_key] .= '<td' . $version_class . '>' . esc_html( $theme->get('Version') ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align">' . $theme_active . '</td>';
			$rows[$row_key] .= '<td>' . $theme_author . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align"><a target="_blank" href="' . esc_attr( $theme->get('ThemeURI') ) . '">link</a></td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . $friendly_status . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . esc_html( $fsc_wp_org_themes_status[$name]['last_updated'] ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_hidden">' . esc_html( $fsc_wp_org_themes_status[$name]['last_updated_days'] ) . '</td>';
			$rows[$row_key] .= '<td class="fsc_table_cell_center_align ' . $status_class . '">' . esc_html( fsc_pretty_version( $fsc_wp_org_themes_status[$name]['tested_up_to'] ) ) . '</td>';
			$rows[$row_key] .= '</tr>';
		}

		// Sort the rows by key name.
		ksort( $rows );

		// Output the sorted rows.
		echo implode( PHP_EOL, $rows );

		// Close the table.
		echo '</table>' . PHP_EOL;

		// Make a friendly date/time for the last data update.
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $fsc_wp_org_date );

		// Let the user know when the data was last updated.
		echo '<p>' . __( 'Data last updated', 'feature-status-check' ) . ': ' . $date_string . '</p>' . PHP_EOL;
	} else {
		// Display the no data message.
		echo $fsc_no_data_message . PHP_EOL;
	}

	// Only display the manual update option if updates are enabled.
	if( $no_update == false ) {
		// Create a form to submit a manual data update request.
		echo '<form action="' . esc_url( admin_url( 'plugins.php?page=fsc_admin_menu' ) ) . '" method="post">' . PHP_EOL;
		echo '<input type="hidden" name="fsc_data_update" value="true">' . PHP_EOL;
		submit_button( __( 'Manual Data Update', 'feature-status-check' ) ) . PHP_EOL;
		echo '<span>' . __( '(updating the data may take a long time if you have a significant number of plugins, be patient and wait for the page to complete its loading)', 'feature-status-check' ) . '</span>' . PHP_EOL;
		echo '</form>' . PHP_EOL;
	}
}

