<?php
/*
Copyright (c) 2022 by Greg Ross

This software is released under the GPL v2.0, see license.txt for details
*/

function pcs_options_page() {
	// Get the options.
	$options = get_option( 'plugin_status_check' );

	// Get the next cronjob schedule.
	$next_cron_job = wp_next_scheduled ( 'psc_daily_event' );

	// Grab the plugin list.
	$plugins = get_plugins();

	// Retrieve the data without updating it to see if we have some.
	$psc_transient = psc_get_plugin_status_transient( $plugins, true );

	// Set defaults if we don't have any.
	if( ! is_array( $options ) ) { $options = array( 'email-enabled' => true ); update_option( 'plugin_status_check', $options ); }

	// Check to see if the settings have been submitted.
	if( array_key_exists( 'pcs_update_options', $_POST ) ) {
		// By default, enabled the e-mail.
		$options['email-enabled'] = true;

		// Check to see if the user disabled it.
		if( ! array_key_exists( 'email-enabled', $_POST ) ) { $options['email-enabled'] = false; }

		// Store the options.
		update_option( 'plugin_status_check', $options );
	}

	// Check to see if the wp cron reset button has been pressed.
	if( array_key_exists( 'pcs_reset_cron', $_POST ) ) {
		// If there is an existing cron job, unscheduled it now.
		if( $next_cron_job ) {
			wp_unschedule_event( $next_cron_job, 'psc_daily_event' );
	    }

	    // Now create a new cron job in the future by 60 seconds.
	    wp_schedule_event( time() + 60, 'daily', 'psc_daily_event' );

		// Reset when the next cron job is going to be for the page update.
		$next_cron_job = wp_next_scheduled ( 'psc_daily_event' );
	}

	// Check to see if the manual data update button has been pressed.
	if( array_key_exists( 'pcs_update_data', $_POST ) ) {
		// Delete the existing transient if it exists.
		delete_transient( 'psc_wp_org_plugins_status' );

		// Do a full update.
		$psc_transient = psc_get_plugin_status_transient( $plugins );
	}

	if( $options['email-enabled'] == true ) {
		$checked = ' checked';
	}

	// Build the tabbed interface.
	echo '<script type="text/javascript">jQuery(document).ready(function() { jQuery("#tabs").tabs(); jQuery("#tabs").tabs("option", "active", 0 ); } );</script>' . PHP_EOL;

	echo '<div class="wrap">' . PHP_EOL;
	echo '<h1>' .  __( 'Plugin Status Check' ) . '</h1>' . PHP_EOL;
	echo '<br>' . PHP_EOL;

	echo '<form method="post" action="options-general.php?page=plugin-status-check/plugin-status-check.php">' . PHP_EOL;

	echo '<div id="tabs">';
	echo '<ul>';
	echo '<li><a href="#fragment-0"><span>' .  __( 'Settings' ) . '</span></a></li>' . PHP_EOL;
	echo '<li><a href="#fragment-1"><span>' .  __( 'About' ) . '</span></a></li>' . PHP_EOL;
	echo '</ul>' . PHP_EOL;

	echo '<div id="fragment-0">' . PHP_EOL;
	echo '<h2>' . __( 'E-Mail' ) . '</h2>' . PHP_EOL;
	echo '<span>';
	echo __( 'Daily admin status e-mail enabled' ) . ': ';
	echo '<input type="checkbox" name="email-enabled"' . $checked . '>';
	echo '</span>' . PHP_EOL;

	// Make a friendly date/time for the last data update.
	if( $next_cron_job == 0 ) {
		$date_string = __( 'Never' );
	} else {
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $next_cron_job );
	}

	echo '<div class="submit">' . PHP_EOL;
	echo '<input type="submit" class="button-primary" name="pcs_update_options" value="' . __( 'Update Options' ) . '" />' . PHP_EOL;
	echo '</div>' . PHP_EOL;

	echo '<hr>' . PHP_EOL;
	echo '<h2>' . __( 'Cron' ) . '</h2>' . PHP_EOL;

	// Let the user know when the data was last updated.
	echo '<span>' . __( 'Next cron schedule' ) . ': ' . $date_string . '</span>' . PHP_EOL;

	echo '<div class="submit">' . PHP_EOL;
	echo '<input type="submit" class="button-primary" name="pcs_reset_cron" value="' . __( 'Reset Cron Job' ) . '" />' . PHP_EOL;
	echo '<br>' . PHP_EOL;
	echo __( '(will reset the cron task to be current time +1 minute)' ) . PHP_EOL;
	echo '</div>' . PHP_EOL;

	echo '<hr>' . PHP_EOL;
	echo '<h2>' . __( 'Data' ) . '</h2>' . PHP_EOL;


	// Make a friendly date/time for the last data update.
	if( $psc_transient['timestamp'] == 0 ) {
		// Note, $date_string is still set to the next cron job at this time, so we can use it here without resetting it.
		echo '<span>' . __( 'Data has not yet been retrieved from wordpress.org, please manually update the data or wait for it to become available on' ) . ': ' . $date_string . '</span>';

	} else {
		// Make the date string to be the last update time.
		$date_string = wp_date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $psc_transient['timestamp'] );

		// Let the user know when the data was last updated.
		echo '<span>' . __( 'Data last updated' ) . ': ' . $date_string . '</span>' . PHP_EOL;
	}

	echo '<div class="submit">' . PHP_EOL;
	echo '<input type="submit" class="button-primary" name="pcs_update_data" value="' . __( 'Manual Data Update' ) . '" />' . PHP_EOL;
	echo '<br>' . PHP_EOL;
	echo '(updating the data may take a long time if you have a significant number of plugins, be patient and wait for the page to complete its loading)' . PHP_EOL;
	echo '</div>' . PHP_EOL;

	echo '<hr>' . PHP_EOL;

	echo '</div>' . PHP_EOL;

	echo '<div id="fragment-1">' . PHP_EOL;
?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<td scope="row" align="center"><img src="<?php echo plugins_url( 'images/logo-250.png', PSC_PLUGIN_FILE ); ?>"></td>
			</tr>

			<tr valign="top">
				<td scope="row" align="center"><h2><?php echo sprintf( __( 'Plugin Status Check V%s' ), PSC_VERSION ); ?></h2></td>
			</tr>

			<tr valign="top">
				<td scope="row" align="center"><p>by <a href="https://toolstack.com">Greg Ross</a></p></td>
			</tr>

			<tr valign="top">
				<td scope="row" align="center"><hr /></td>
			</tr>

			<tr valign="top">
				<td scope="row" colspan="2"><h2><?php _e( 'Rate and Review at WordPress.org' ); ?></h2></td>
			</tr>

			<tr valign="top">
				<td scope="row" colspan="2"><?php _e( 'Thanks for installing Plugin Status Check, I encourage you to submit a ' );?> <a href="http://wordpress.org/support/view/plugin-reviews/plugin-status-check" target="_blank"><?php _e( 'rating and review' ); ?></a> <?php _e( 'over at WordPress.org.  Your feedback is greatly appreciated!' );?></td>
			</tr>

			<tr valign="top">
				<td scope="row" colspan="2"><h2><?php _e( 'Support' ); ?></h2></td>
			</tr>

			<tr valign="top">
				<td scope="row" colspan="2">
					<p><?php _e( 'Here are a few things to do submitting a support request:' ); ?></p>

					<ul style="list-style-type: disc; list-style-position: inside; padding-left: 25px;">
						<li><?php echo sprintf( __( 'Have you search the %s for a similar issue?' ), '<a href="http://wordpress.org/support/plugin/os-integration" target="_blank">' . __( 'support forum' ) . '</a>');?></li>
						<li><?php _e( 'Have you search the Internet for any error messages you are receiving?' );?></li>
						<li><?php _e( 'Make sure you have access to your PHP error logs.' );?></li>
					</ul>

					<p><?php _e( 'And a few things to double-check:' );?></p>

					<ul style="list-style-type: disc; list-style-position: inside; padding-left: 25px;">
						<li><?php _e( 'Have you double checked the plugin settings?' );?></li>
						<li><?php _e( 'Do you have all the required PHP extensions installed?' );?></li>
						<li><?php _e( 'Are you getting a blank or incomplete page displayed in your browser?  Did you view the source for the page and check for any fatal errors?' );?></li>
						<li><?php _e( 'Have you checked your PHP and web server error logs?' );?></li>
					</ul>

					<p><?php _e( 'Still not having any luck?' );?> <?php echo sprintf( __( 'Then please open a new thread on the %s.' ), '<a href="http://wordpress.org/support/plugin/plugin-status-check" target="_blank">' . __( 'WordPress.org support forum' ) . '</a>');?></p>
				</td>
			</tr>

		</tbody>
	</table>

<?php

	echo '</div>' . PHP_EOL;

	echo '</form>' . PHP_EOL;

	echo '</div>' . PHP_EOL;
}
