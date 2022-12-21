<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Load the transient from cache and add update it if required.
function fsc_get_status_transient( $plugins, $themes, $no_update = false ) {
	$fsc_wp_org_plugins_status = array();
	$fsc_wp_org_themes_status = array();
	$fsc_wp_org_date = 0;

	$fsc_transient = get_transient( 'fsc_wp_org_status' );

	if( is_array( $fsc_transient ) && array_key_exists( 'plugins', $fsc_transient ) ) {
		$fsc_wp_org_plugins_status = $fsc_transient['plugins'];
		$fsc_wp_org_date = $fsc_transient['timestamp'];

		if( array_key_exists( 'themes', $fsc_transient ) ) {
			$fsc_wp_org_themes_status = $fsc_transient['themes'];
		}
	}

	// If we didn't find a transient in the database, setup a default one now.
	if( $fsc_transient === false ) {
		$fsc_transient = array( 'timestamp' => $fsc_wp_org_date, 'plugins' => $fsc_wp_org_plugins_status, 'themes' => $fsc_wp_org_themes_status );
	}

	// If we've been told not to do any updates, just return now.
	if( $no_update == true ) {
		return array( 'timestamp' => $fsc_wp_org_date, 'plugins' => $fsc_wp_org_plugins_status, 'themes' => $fsc_wp_org_themes_status );
	}

	// Check to see if the transient has expired, or is older than we want.
	// We've set the expiry time on the transient to two days so the cron job
	// has time to update it in the background.
	if( ! is_array( $fsc_wp_org_plugins_status ) || ! is_array( $fsc_wp_org_themes_status ) || time() - $fsc_wp_org_date > 86400 ) {
		$fsc_wp_org_plugins_status = array();
		$fsc_wp_org_themes_status = array();

		foreach( $plugins as $name => $plugin ) {
			// Get the slug for the plugin.
			$slug = fsc_get_plugin_slug( $name );

			$fsc_wp_org_plugins_status[$name] = fsc_get_plugin_status( $slug, $plugin['Version'] );
		}

		foreach( $themes as $name => $theme ) {
			// Get the slug for the plugin.
			$slug = $name;

			$fsc_wp_org_themes_status[$name] = fsc_get_theme_status( $name, $theme->get('Version') );
		}

		// Set and store the new transient.
		$fsc_transient = array( 'timestamp' => time(), 'plugins' => $fsc_wp_org_plugins_status, 'themes' => $fsc_wp_org_themes_status );
		set_transient( 'fsc_wp_org_status', $fsc_transient, 172800 );
	}

	// Check to see if we've added a plugins since the last transient save.
	$new_plugins = false;
	foreach( $plugins as $name => $plugin ) {
		if( ! array_key_exists( $name, $fsc_wp_org_plugins_status ) ) {
			// Get the slug for the plugin.
			$slug = fsc_get_plugin_slug( $name );

			$fsc_wp_org_plugins_status[$name] = fsc_get_plugin_status( $slug, $plugin['Version'] );
			$new_plugins = true;
		}
	}

	// Check to see if we've added a plugins since the last transient save.
	$new_themes = false;
	foreach( $themes as $name => $theme ) {
		if( ! array_key_exists( $name, $fsc_wp_org_themes_status ) ) {
			$fsc_wp_org_themes_status[$name] = fsc_get_theme_status( $name, $plugin['Version'] );
			$new_themes = true;
		}
	}

	if( $new_plugins || $new_themes ) {
		// Figure out when the transient was set to expire.
		$new_expiry_time = 172800 - ( time() - $fsc_wp_org_date );

		// Reset the transient variable to the new data.
		$fsc_transient = array( 'timestamp' => $fsc_wp_org_date, 'plugins' => $fsc_wp_org_plugins_status, 'themes' => $fsc_wp_org_themes_status );

		// Store the transient for future use with the new plugins.
		set_transient( 'fsc_wp_org_status', $fsc_transient, $new_expiry_time );
	}

	return $fsc_transient;
}

// Create an individual plugins status array.
function fsc_get_plugin_status( $slug, $version ) {
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
	} else{
		// Since the WP api didn't return anything, check the website, as closed projects don't return API info.
		$result = fsc_parse_wporg_page( $wp_url . $slug );
	}

	return $result;
}

// Create an individual themes status array.
function fsc_get_theme_status( $slug, $version ) {
	require_once( ABSPATH . 'wp-admin/includes/theme.php' );

	// Make sure we use an english variant of the wordpress plugin directory.
	$wp_url = 'https://en-ca.wordpress.org/plugins/';

	// List of fields to exclude from the WP api query.
	$included_fields = array(
								'description' => false,
								'sections' => false,
								'rating' => false,
								'ratings' => false,
								'downloaded' => false,
								'downloadlink' => false,
								'tags' => false,
								'homepage' => false,
								'screenshots' => false,
								'screenshot_count int' => false,
								'screenshot_url' => false,
								'photon_screenshots' => false,
								'template' => false,
								'parent' => false,
								'versions' => false,
								'theme_url' => false,
								'extended_author' => false,
								'author' => false,
								'name' => false,
								'preview_url' => false,
								'reviews_url' => false,
								'is_commercial' => false,
								'external_support_url' => false,
								'is_community' => false,
								'exteernal_repository_url' => false,
								'can_configure_categorization_options' => false,
							);

	$wp_theme_info = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => $included_fields ) );

	if( ! is_wp_error( $wp_theme_info ) ) {
		// Setup some default values.
		$result = array( 'status' => 'up_to_date', 'latest_version' => 'unknown', 'last_updated' => 'recently', 'tested_up_to' => 'na' );

		// Get the last update date.
		$result['last_updated'] = $wp_theme_info->last_updated;

		// Get the last version of WP that was tested.
		$result['tested_up_to'] = 'na';

		// Get the lastest version for the plugin.
		$result['latest_version'] = $wp_theme_info->version;

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
	} else{
		// Since the WP api didn't return anything, check the website, as closed projects don't return API info.
		$result = fsc_parse_wporg_page( $wp_url . $slug );
	}

	return $result;
}

function fsc_parse_wporg_page( $url ) {
	$wp_org_page = file_get_contents( $url );

	$result = array( 'status' => 'unknown', 'latest_version' => 'unknown', 'last_updated' => 'never', 'tested_up_to' => 'na' );

	if( $wp_org_page === false || str_contains( $wp_org_page, 'Nothing Found' ) || str_contains( $wp_org_page, 'Showing results for' ) ) {
		$result['status'] = 'not_found';
	} else {
		// Check to see if the plugin/theme hasn't been tested recently.
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
		if( $split !== false ) { $result['tested_up_to'] = $split[0]; } else { $result['tested_up_to'] = 'na'; }

		// Get the lastest version for the plugin.
		$split = preg_split('/Version: <strong>/', $wp_org_page );
		$split = preg_split('/<\/strong>/', $split[1] );
		if( $split !== false ) { $result['latest_version'] = $split[0]; }

		if( $version != $result['latest_version'] ) {
			$result['status'] = "out_of_date";
		}

		// Check to see if the plugin has been closed.
		if( str_contains( $wp_org_page, 'theme has been closed' ) ) {
			$result['status'] = "closed";
		}

		// Check to see if the plugin has been closed temporarily.
		if( str_contains( $wp_org_page, 'closure is temporary' ) ) {
			$result['status'] = "temp_closed";
		}
	}

	return $result;
}
