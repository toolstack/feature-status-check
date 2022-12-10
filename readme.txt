=== Plugin Status Check ===
Contributors: GregRoss
Plugin URI: http://toolstack.com/plugin-status-check
Author URI: http://toolstack.com
Tags: admin plugins status
Requires at least: 5.2
Tested up to: 6.1.1
Requires PHP: 7.0
Stable tag: v1.0
License: GPLv2

Checks to see if the plugins you have on your site are still supported in the plugin directory.

== Description ==

Plugin status can be a hard thing to manage in your WordPress installation, sometimes plugins get abondoned, or closed for security reasons and you have no way of knowing without visiting the plugin page.

Plugin Status Check gives you a unified dashboard to view the status of all your installed plugins, and highlights those that might have issues.

Plugin Status Check also integrates with the WordPress site Health feature and highlights those plugins with possible issues.

Finally, Plugin Status Check also send out a change report to the site admin during the daily update via e-mail.

This code is released under the GPL v2, see license.txt for details.

== Installation ==

1. Extract the archive file into your plugins directory in the plugin-status-check folder.
2. Activate the plugin in the Plugin options.
3. Done!

== Frequently Asked Questions ==

= Are there any options for this plugin =

No, everything is taken care of automatically.

= The plugin page seems to freeze for a long time, what's going on? =

If you have a large number of plugins installed on your site, each one must be checked against wordpress.org, this can take a significant amount of time.

By default, Plugin Status Check setups a daily cron job to cache this data so the user should never see this, but if there is a problem with cron, the status check page may take a while to load while this data is retrieved.

== Screenshots ==

1. Plugin Status Check screen.
2. WordPress Site Health integration

== Changelog ==

= 1.0 =

* Release date: TBD
* Initial release.

== Upgrade Notice ==

None at this time.

== Roadmap ==

* None at this time!
