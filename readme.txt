=== One Click Plugin Updater ===
Contributors: whiteshadow
Tags: plugins, maintenance, update, upgrade, update notification, automation
Requires at least: 2.3
Tested up to: 2.3.1
Stable tag: 1.0.5

Adds an "update automatically" link to plugin update notifications and lets you update plugins with a single click.

== Description ==

This plugin extends the plugin update notification feature introduced in WordPress 2.3 by adding an "update automatically" link to update notifications. When you click the link, the new version of the corresponding plugin is downloaded and installed automatically.

**How It Works (In Detail)**

To be able to display the new link this plugin will hide the original update notification and display a slightly modified one. Here's what happens when you click the "update automatically" link :

1. If the plugin that needs to be updated is active, it is deactivated.
1. The Plugin Updater retrieves the plugin's page from Wordpress.org and finds the download link.
1. The new version is downloaded and extracted to the wp-content/plugins directory (this directory must be writable by the Updater plugin).
1. If necessary, the updated plugin is re-activated.

All this happens in the background, so if everything works OK you'll end up back at the "Plugins" tab. If there are any errors the updater will display an error message and abort the upgrade.

More info - [One Click Plugin Updater homepage](http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/ "One Click Plugin Updater Homepage")

== Installation ==

**Additional Requirements**

* The CURL library installed or "allow url fopen" enabled in php.ini
* The *plugins* directory needs to be writable by the webserver. The exact permission requirements vary by server, though CHMOD 666 should be sufficient.

To install the plugin follow these steps :

1. Download the one-click-plugin-updater.zip file to your local machine.
1. Unzip the file 
1. Upload "one-click-plugin-updater" folder to the "/wp-content/plugins/" directory
1. Activate the plugin through the 'Plugins' menu in WordPress

That's it.