=== Plugin Health Check ===
Contributors: GregRoss
Plugin URI: http://toolstack.com/plugin-health-check
Author URI: http://toolstack.com
Tags: admin plugins health
Requires at least: 5.2
Tested up to: 6.1.1
Requires PHP: 7.0
Stable tag: v1.0
License: GPLv2

Checks to see if the plugins you have on your site are still supported in the plugin directory.

== Description ==

Plugin health can be a hard thing to manage in your WordPress installation, sometimes plugins get abondoned, or closed for security reasons and you have no way of knowing without visiting the plugin page.

Plugin Health Check gives you a unified dashboard to view the status of all your installed plugins, and highlights those that might have issues.

Plugin Health Check also integrates with the WordPress site health feature and highlights those plugins with possible issues.

This code is released under the GPL v2, see license.txt for details.

== Installation ==

1. Extract the archive file into your plugins directory in the plugin-health-check folder.
2. Activate the plugin in the Plugin options.
3. Done!

== Frequently Asked Questions ==

= Are there any options for this plugin =

No, everything is taken care of automatically.

= The plugin health page seems to freeze for a long time, what's going on? =

If you have a large number of plugins installed on your site, each one must be checked against wordpress.org, this can take a significant about of time.

By default, Plugin Health Check setups a daily cron job to cache this data so the user should never see this, but if there is a problem with cron, the health check page may take a while to load while this data is retrieved.

== Screenshots ==

1. Plugin Health Check screen.
2. WordPress Site Health integration

== Changelog ==

= 1.0 =

* Release date: TBD
* Initial release.

== Upgrade Notice ==

None at this time.

== Roadmap ==

* None at this time!
