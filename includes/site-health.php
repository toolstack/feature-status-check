<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

// Add our test to the site status screen.
function psc_add_plugin_status_test( $tests ) {
    // Make sure we use an english variant of the wordpress plugin directory.
    $tests['direct']['psc_status'] = array(
        'label' => __( 'Plugin Status Check' ),
        'test'  => 'psc_status_test',
    );
    return $tests;
}

// Run our site status test.
function psc_status_test() {
	// Make sure we use an english variant of the wordpress plugin directory.
	$wp_url = 'https://wordpress.org/plugins/';

    // Grab the install plugin list, used later to make things look pretty for the end user.
    $plugins = get_plugins();

	// Grabbed the cached data, don't update it at this time as it takes too long.
	$psc_transient = psc_get_plugin_status_transient( $plugins, true ) ;

	// Setup the default result.
    $result = array(
        'label'       => __( 'Plugin status check passed' ),
        'status'      => 'good',
        'badge'       => array(
            'label' => __( 'Security' ),
            'color' => 'green',
        ),
        'description' => sprintf(
            '<p>%s</p>',
            __( 'All plugins are still active on wordpress.org.' )
        ),
        'actions'     => '',
        'test'        => 'psc_status_test',
    );

    // If the transient doesn't exist, or is older than 24 hours, mark the test as a recommendation and orange.
    // This shouldn't really happen, but it might if cron is broken.
    if( $psc_transient['timestamp'] < time() - 86400 ) {
        $result['status'] = 'recommended';
        $result['label'] = __( 'Plugin status found no data' );
        $result['badge']['color'] = 'orange';
        $result['description'] = sprintf(
            '<p>%s</p>',
            __( 'No data from wordpress.org could be found, please go to the plugins->Status Check page to load it and then return here.' )
        );
        $result['actions'] .= sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url( admin_url( 'plugins.php?page=psc_admin_menu' ) ),
            __( 'Update data' )
        );

        return $result;
    }

    // Setup some arrays to store the untested/closed/temp_closed plugins that we find.
    $untested 	 = array();
    $closed 	 = array();
    $temp_closed = array();

    // Loop through the transient data one plugin at a time and filter them out by status.
    foreach( $psc_transient['data'] as $name => $plugin ) {
    	switch( $plugin['status'] ) {
    		case 'untested':
	    		$untested[] = $name;

    			break;
    		case 'closed':
	    		$closed[] = $name;

    			break;
    		case 'temp_closed':
	    		$temp_closed[] = $name;

    			break;
    	}
    }

    // Make the plugin names look pretty and make it a link to the wordpress.org plugin webpage.
    foreach( $untested as $index => $name) {
		// Get the slug for the plugin.
		$slug = pcs_get_plugin_slug( $name );

    	$untested[$index] = '<a target="_blank" href="' . esc_attr( $wp_url . $slug ) . '">' . $plugins[$name]['Name'] . '</a>';
    }

    foreach( $closed as $index => $name) {
		// Get the slug for the plugin.
		$slug = pcs_get_plugin_slug( $name );

    	$closed[$index] = '<a target="_blank" href="' . esc_attr( $wp_url . $slug ) . '">' . $plugins[$name]['Name'] . '</a>';
    }

    foreach( $temp_closed as $index => $name) {
		// Get the slug for the plugin.
		$slug = pcs_get_plugin_slug( $name );

    	$temp_closed[$index] = '<a target="_blank" href="' . esc_attr( $wp_url . $slug ) . '">' . $plugins[$name]['Name'] . '</a>';
    }

    // Create an unordered list for each status time.
    $ul_untested  	= count( $untested ) == 0 ? '' : '<h2>' . __( 'Plugins untested in over 3 years' ) . ':</h2><ul><li>' . implode( '</li><li>', $untested ) . '</li></ul>';
    $ul_closed 		= count( $closed ) == 0 ? '' : '<h2 style="color: orange;">' . __( 'Plugins permanently closed on wordpress.org' ) . ':</h2><ul><li>' . implode( '</li><li>', $closed ) . '</li></ul>';
    $ul_temp_closed = count( $temp_closed ) == 0 ? '' : '<h2 style="color: red;">' . __( 'Plugins temporarily closed on wordpress.org' ) . ':</h2><ul><li>' . implode( '</li><li>', $temp_closed ) . '</li></ul>';

    // If we have closed/temp_closed items, mark the test critical and red.
    if( count( $closed ) + count( $temp_closed ) > 0 ) {
        $result['status'] = 'critical';
        $result['label'] = __( 'Plugin status found issues' );
        $result['badge']['color'] = 'red';
        $result['description'] = sprintf(
            '<p>%s <a href="%s">%s</a> %s </p>',
            __( 'Some plugins installed on your site may no longer be supported and/or have security issues, please review the below list and' ),
            esc_url( admin_url( 'plugins.php' ) ),
            __( 'update/disable' ),
            __( 'as required (below links are to the plugin\'s wordpress.org page):' )
        );
        $result['actions'] .= $ul_closed . $ul_temp_closed . $ul_untested;

        return $result;
    }

    // If we have only untested items (we execute an early return above if we have closed items), mark the test recommended and orange.
    if( count( $untested ) > 0 ) {
        $result['status'] = 'recommended';
        $result['label'] = __( 'Plugin status found issues' );
        $result['badge']['color'] = 'orange';
        $result['description'] = sprintf(
            '<p>%s <a href="%s">%s</a> %s </p>',
            __( 'Some plugins installed on your site may no longer be supported, please review the below list and' ),
            esc_url( admin_url( 'plugins.php' ) ),
            __( 'update/disable' ),
            __( 'as required (below links are to the plugin\'s wordpress.org page):' )
        );
        $result['actions'] .= $ul_closed . $ul_temp_closed . $ul_untested;
    }

    return $result;
}

function psc_add_status_tab( $tabs ) {
    // translators: Tab heading for Site Status navigation.
    $tabs['psc_add_heath_tab'] = __( 'Plugin Status' );

    return $tabs;
}

function psc_add_status_tab_content( $tab ) {
    // Do nothing if this is not our tab.
    if ( 'psc_add_heath_tab' !== $tab ) {
        return;
    }

    echo '<div style="padding-left: 2.5em;">';
    psc_display( true, true );
    echo '</div>';
}

