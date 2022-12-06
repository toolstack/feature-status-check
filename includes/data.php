<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Load the transient from cache and add update it if required.
function psc_get_plugin_status_transient( $plugins, $no_update = false ) {
	$psc_wp_org_plugins_status = array();
	$psc_wp_org_plugins_date = 0;

	$psc_transient = get_transient( 'psc_wp_org_plugins_status' );

	if( is_array( $psc_transient ) && array_key_exists( 'data', $psc_transient ) ) {
		$psc_wp_org_plugins_status = $psc_transient['data'];
		$psc_wp_org_plugins_date = $psc_transient['timestamp'];
	}

	// If we didn't find a transient in the database, setup a default one now.
	if( $psc_transient === false ) {
		$psc_transient = array( 'timestamp' => $psc_wp_org_plugins_date, 'data' => $psc_wp_org_plugins_status );
	}

	// If we've been told not to do any updates, just return now.
	if( $no_update == true ) {
		return array( 'timestamp' => $psc_wp_org_plugins_date, 'data' => $psc_wp_org_plugins_status );
	}

	// Force cache update, for testing only.
	// $psc_wp_org_plugins_status = false;

	// Check to see if the transient has expired, or is older than we want.
	// We've set the expiry time on the transient to two days so the cron job
	// has time to update it in the background.
	if( ! is_array( $psc_wp_org_plugins_status ) || time() - $psc_wp_org_plugins_date > 86400 ) {
		$psc_wp_org_plugins_status = array();

		foreach( $plugins as $name => $plugin ) {
			// Get the slug for the plugin.
			$slug = pcs_get_plugin_slug( $name );

			$psc_wp_org_plugins_status[$name] = psc_get_plugin_status( $slug, $plugin['Version'] );
		}

		// Set and store the new transient.
		$psc_transient = array( 'timestamp' => time(), 'data' => $psc_wp_org_plugins_status );
		set_transient( 'psc_wp_org_plugins_status', $psc_transient, 172800 );
	}

	// Check to see if we've added a plugins since the last transient save.
	$new_plugins = false;
	foreach( $plugins as $name => $plugin ) {
		if( ! array_key_exists( $name, $psc_wp_org_plugins_status ) ) {
			$psc_wp_org_plugins_status[$name] = psc_get_plugin_status( $slug, $plugin['Version'] );
			$new_plugins = true;
		}
	}

	if( $new_plugins ) {
		// Figure out when the transient was set to expire.
		$new_expiry_time = 172800 - ( time() - $psc_wp_org_plugins_date );

		// Reset the transient variable to the new data.
		$psc_transient = array( 'timestamp' => $psc_wp_org_plugins_date, 'data' => $psc_wp_org_plugins_status );

		// Store the transient for future use with the new plugins.
		set_transient( 'psc_wp_org_plugins_status', $psc_transient, $new_expiry_time );
	}

	return $psc_transient;
}

// Create an individual plugins status array.
function psc_get_plugin_status( $slug, $version ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

	// Make sure we use an english variant of the wordpress plugin directory.
	$wp_url = 'https://en-ca.wordpress.org/plugins/';

	// List of fields to exclude from the WP api query.
	$included_fields = array(
								'short_description' => false,
								'description' => false,
								'sections' => false,
								'requires' => false,
								'requires_php' => false,
								'rating' => false,
								'ratings' => false,
								'downloaded' => false,
								'downloadlink' => false,
								'added' => false,
								'tags' => false,
								'compatibility' => false,
								'homepage' => false,
								'donate_link' => false,
								'reviews' => false,
								'banners' => false,
								'icons' => false,
								'active_installs' => false,
								'group' => false,
								'contributors' => false,
								'screenshots' => false,
								'versions' => false,
								'support_threads_resolved' => false,
								'support_threads' => false,
								'num_ratings' => false,
								'author_profile' => false,
								'author' => false,
							);

	$wp_plugin_info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => $included_fields ) );

	if( ! is_wp_error( $wp_plugin_info ) ) {
		// Setup some default values.
		$result = array( 'status' => 'up_to_date', 'latest_version' => 'unknown', 'last_updated' => 'recently', 'tested_up_to' => '6.1.1' );

		// Get the last update date.
		$result['last_updated'] = $wp_plugin_info->last_updated;

		// Get the last version of WP that was tested.
		$result['tested_up_to'] = $wp_plugin_info->tested;

		// Get the lastest version for the plugin.
		$result['latest_version'] = $wp_plugin_info->version;

		if( $version != $result['latest_version'] ) {
			$result['status'] = "out_of_date";
		}

		$elapsed_time = time() - strtotime( $result['last_updated'] );
		$elapsed_days = round( $elapsed_time / 86400, 0, PHP_ROUND_HALF_DOWN );
		$elapsed_months = round( $elapsed_time / ( 86400 * 30 ), 0, PHP_ROUND_HALF_DOWN );
		$elapsed_years = round( $elapsed_time / ( 86400 * 30 * 12 ), 0, PHP_ROUND_HALF_DOWN );

		// Check to see if the plugin hasn't been tested recently.
		if( $elapsed_years > 2 )  {
			$result['status'] = "untested";
		}

		// Make the last_updated more human friendly.
		if( $elapsed_years > 0 ) {
			$result['last_updated'] = $elapsed_years . ' year';
			if( $elapsed_years > 1 ) { $result['last_updated'] .= 's'; }
		} else if( $elapsed_months > 1 ) {
			$result['last_updated'] = $elapsed_months . ' months';
		} else {
			if( $elapsed_days > 0 ) {
				$result['last_updated'] = $elapsed_days . ' days';
			} else if( $elapsed_days == 1 ) {
				$result['last_updated'] = $elapsed_days . ' day';
			} else {
				$result['last_updated'] = 'today';
			}
		}

		// Check to see if the plugin has been closed.
		if( str_contains( $wp_org_page, 'plugin has been closed' ) ) {
			$result['status'] = "closed";
		}

		// Check to see if the plugin has been closed temporarily.
		if( str_contains( $wp_org_page, 'closure is temporary' ) ) {
			$result['status'] = "temp_closed";
		}
	} else{
		// Since the WP api didn't return anything, check the website, as closed projects don't return API info.
		$wp_org_page = file_get_contents( $wp_url . $slug );

		if( $wp_org_page === false || str_contains( $wp_org_page, 'Nothing Found' ) || str_contains( $wp_org_page, 'Showing results for' ) ) {
			$result = array( 'status' => 'not_found', 'latest_version' => 'unknown', 'last_updated' => 'never', 'tested_up_to' => 'n/a' );
		} else {

			// Check to see if the plugin hasn't been tested recently.
			if( str_contains( $wp_org_page, 'hasn&#146;t been tested with the latest 3 major releases' ) )  {
				$result['status'] = "untested";
			}

			// Get the last update date.
			$split = preg_split('/Last updated: <strong><span>/', $wp_org_page );
			$split = preg_split('/<\/span>/', $split[1] );
			if( $split !== false ) { $result['last_updated'] = $split[0]; }

			// Get the last version of WP that was tested.
			$split = preg_split('/Tested up to: <strong>/', $wp_org_page );
			$split = preg_split('/<\/strong>/', $split[1] );
			if( $split !== false ) { $result['tested_up_to'] = $split[0]; }

			// Get the lastest version for the plugin.
			$split = preg_split('/Version: <strong>/', $wp_org_page );
			$split = preg_split('/<\/strong>/', $split[1] );
			if( $split !== false ) { $result['latest_version'] = $split[0]; }

			if( $version != $result['latest_version'] ) {
				$result['status'] = "out_of_date";
			}

			// Check to see if the plugin has been closed.
			if( str_contains( $wp_org_page, 'plugin has been closed' ) ) {
				$result['status'] = "closed";
			}

			// Check to see if the plugin has been closed temporarily.
			if( str_contains( $wp_org_page, 'closure is temporary' ) ) {
				$result['status'] = "temp_closed";
			}
		}
	}

	return $result;
}
