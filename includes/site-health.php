<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Add our test to the site status screen.
function fsc_add_plugin_status_test( $tests ) {
    // Make sure we use an english variant of the wordpress plugin directory.
    $tests['direct']['fsc_status'] = array(
        'label' => __( 'Feature Status Check', 'feature-status-check' ),
        'test'  => 'fsc_status_test',
    );
    return $tests;
}

// Run our site status test.
function fsc_status_test() {
	// The URLs for wordpress directories.
	$wp_plugin_url = 'https://wordpress.org/plugins/';
    $wp_theme_url = 'https://wordpress.org/themes/';

    // Grab the installed plugin list, used later to make things look pretty for the end user.
    $plugins = get_plugins();

    // Grab the install plugin list, used later to make things look pretty for the end user.
    $themes = wp_get_themes();

	// Grabbed the cached data, don't update it at this time as it takes too long.
	$fsc_transient = fsc_get_status_transient( $plugins, $themes, true );

	// Setup the default result.
    $result = array(
        'label'       => __( 'Feature status check passed', 'feature-status-check' ),
        'status'      => 'good',
        'badge'       => array(
            'label' => __( 'Security', 'feature-status-check' ),
            'color' => 'green',
        ),
        'description' => sprintf(
            '<p>%s</p>',
            __( 'All features are still active on wordpress.org.', 'feature-status-check' )
        ),
        'actions'     => '',
        'test'        => 'fsc_status_test',
    );

    // If the transient doesn't exist, or is older than 24 hours, mark the test as a recommendation and orange.
    // This shouldn't really happen, but it might if cron is broken.
    if( $fsc_transient['timestamp'] < time() - 86400 ) {
        $result['status'] = 'recommended';
        $result['label'] = __( 'Feature status found no data', 'feature-status-check' );
        $result['badge']['color'] = 'orange';
        $result['description'] = sprintf(
            '<p>%s</p>',
            __( 'No data from wordpress.org could be found, please go to the settings page to load it and then return here.', 'feature-status-check' )
        );
        $result['actions'] .= sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url( admin_url( 'options-general.php?page=feature-status-check%2Ffeature-status-check.php' ) ),
            __( 'Update data', 'feature-status-check' )
        );

        return $result;
    }

    // Setup some arrays to store the untested/closed/temp_closed plugins that we find.
    $untested 	 = array();
    $closed 	 = array();
    $temp_closed = array();

    // Loop through the transient data one plugin at a time and filter them out by status.
    foreach( $fsc_transient['plugins'] as $name => $plugin ) {
        // Get the slug for the plugin.
        $slug = fsc_get_plugin_slug( $name );
        $nice_name = $plugins[$name]['Name'];

    	switch( $plugin['status'] ) {
    		case 'untested':
	    		$untested[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_plugin_url . $slug ) . '">' . esc_html( $nice_name ) . '</a>';

    			break;
    		case 'closed':
	    		$closed[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_plugin_url . $slug ) . '">' . esc_html( $nice_name ) . '</a>';

    			break;
    		case 'temp_closed':
	    		$temp_closed[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_plugin_url . $slug ) . '">' . esc_html( $nice_name ) . '</a>';

    			break;
    	}
    }

    // Loop through the transient data one theme at a time and filter them out by status.
    foreach( $fsc_transient['themes'] as $name => $theme ) {
        $nice_name = $themes[$name]->get('Name');

        switch( $theme['status'] ) {
            case 'untested':
                $untested[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_theme_url . $name ) . '">' . esc_html( $nice_name ) . '</a>';

                break;
            case 'closed':
                $closed[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_theme_url . $name ) . '">' . esc_html( $nice_name ) . '</a>';

                break;
            case 'temp_closed':
                $temp_closed[$nice_name] = '<a target="_blank" href="' . esc_attr( $wp_theme_url . $name ) . '">' . esc_html( $nice_name ) . '</a>';

                break;
        }
    }

    // Sort the arrays to look nice based upon the key names.
    ksort( $untested );
    ksort( $closed );
    ksort( $temp_closed );

    // Create an unordered list for each status time.
    $ul_untested  	= count( $untested ) == 0 ? '' : '<h2 class="fsc_site_health_untested">' . __( 'Plugins untested in over 3 years', 'feature-status-check' ) . ':</h2><ul><li>' . implode( '</li><li>', $untested ) . '</li></ul>';
    $ul_closed 		= count( $closed ) == 0 ? '' : '<h2 class="fsc_site_health_closed">' . __( 'Plugins permanently closed on wordpress.org', 'feature-status-check' ) . ':</h2><ul><li>' . implode( '</li><li>', $closed ) . '</li></ul>';
    $ul_temp_closed = count( $temp_closed ) == 0 ? '' : '<h2 class="fsc_site_health_temp_closed">' . __( 'Plugins temporarily closed on wordpress.org', 'feature-status-check' ) . ':</h2><ul><li>' . implode( '</li><li>', $temp_closed ) . '</li></ul>';

    // If we have closed/temp_closed items, mark the test critical and red.
    if( count( $closed ) + count( $temp_closed ) > 0 ) {
        $result['status'] = 'critical';
        $result['label'] = __( 'Feature status found issues', 'feature-status-check' );
        $result['badge']['color'] = 'red';
        $result['description'] = sprintf(
            '<p>%s <a href="%s">%s</a> %s </p>',
            __( 'Some featured installed on your site may no longer be supported and/or have security issues, please review the below list and', 'feature-status-check' ),
            esc_url( admin_url( 'plugins.php' ) ),
            __( 'update/disable', 'feature-status-check' ),
            __( 'as required (below links are to the features\' wordpress.org page)', 'feature-status-check' ) . ':'
        );
        $result['actions'] .= $ul_closed . $ul_temp_closed . $ul_untested;

        return $result;
    }

    // If we have only untested items (we execute an early return above if we have closed items), mark the test recommended and orange.
    if( count( $untested ) > 0 ) {
        $result['status'] = 'recommended';
        $result['label'] = __( 'Feature status found issues', 'feature-status-check', 'feature-status-check' );
        $result['badge']['color'] = 'orange';
        $result['description'] = sprintf(
            '<p>%s <a href="%s">%s</a> %s </p>',
            __( 'Some features installed on your site may no longer be supported, please review the below list and', 'feature-status-check', 'feature-status-check' ),
            esc_url( admin_url( 'plugins.php' ) ),
            __( 'update/disable', 'feature-status-check', 'feature-status-check', 'feature-status-check' ),
            __( 'as required (below links are to the features\' wordpress.org page)', 'feature-status-check' ) . ':'
        );
        $result['actions'] .= $ul_closed . $ul_temp_closed . $ul_untested;
    }

    return $result;
}

function fsc_add_status_tab( $tabs ) {
    // translators: Tab heading for Site Status navigation.
    $tabs['fsc_add_heath_tab'] = __( 'Feature Status', 'feature-status-check' );

    return $tabs;
}

function fsc_add_status_tab_content( $tab ) {
    // Do nothing if this is not our tab.
    if ( 'fsc_add_heath_tab' !== $tab ) {
        return;
    }

    echo '<div style="padding-left: 2.5em;">';
    fsc_display( true, true );
    echo '</div>';
}

