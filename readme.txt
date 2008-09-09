=== One Click Plugin Updater ===
Contributors: whiteshadow
Tags: plugin, notification, upload, files, installation, admin, update, upgrade, update notification, automation
Requires at least: 2.3
Tested up to: 2.6.2
Stable tag: 2.4.3

Provides single-click plugin upgrades in WP 2.3 and up, visually marks plugins that have update notifications enabled, allows to easily install new plugins and themes, lets you control if and when WordPress checks for updates... and so on.

== Description ==

Having grown to far exceed it's original aim - to provide easy plugin updates in WordPress - this plugin now deals with various aspects of plugin (and theme) installation and updating. Note that version 2.0 comes with a lot of new features, and, probably, new bugs. The previous version (see the "Other Versions" link to the right) is quite stable though.

**Feature Overview**

* Single-click plugin upgrades in WP 2.3 and up. The techniques that this plugin uses are slightly different from the built-in plugin upgrade feature in WP 2.5, so it's possible that on some blogs the plugin updater works and the built-in updater doesn't (or *vice versa*).
* Upgrade all plugins with a single click (only in WP 2.5 and up).
* Visually identify plugins that have update notifications enabled. They get a yellow-gold marker in the "Plugin Management" tab.
* Quickly determine if there are any pending updates and how many plugins are active. This plugin displays that information right below the "Plugin Management" headline.
* Configure how often WordPress checks for plugin and core updates, which module is used to upgrade plugins (this plugin or the built-in updater), and other options. See *Plugins -> Upgrade Settings*.
* Easily install new plugins and themes (be sure to read the notes below). The plugin adds two new menus for this - *Plugins -> Install a Plugin* and *Design -> Install a Theme*.
* Delete plugins and themes from the Plugins/Themes tabs.
* Compatible with the [OneClick Firefox Extension](https://addons.mozilla.org/en-US/firefox/addon/5503) (up to version 2.1.2 of the plugin). Later versions use a new, improved FF addon : [One-Click Installef for WP](https://addons.mozilla.org/en-US/firefox/addon/7511)
* Global plugin update notifications.
* You can disable update notifications for inactive plugins.
* You can hide the little update count blurb displayed on the "Plugins" menu.
* Now with extra safety - uses the WordPress nonce mechanism for almost all tasks.


**Important Notes**

Currently this plugin only uses direct file access to update and install plugins/themes, so you'll need to make the "/wp-content/plugins/" and "/wp-content/themes/" folders writable by PHP for this to work. See [Changing File Permissions](http://codex.wordpress.org/Changing_File_Permissions) for a general guide on how to do this. Eventually the plugin should use the new filesystem access classes introduced in WP 2.5.

If something doesn't work, you can enable "Debug mode" in *Plugins -> Upgrade Settings*. This will make the plugin display a detailed execution log when it tries to update or install another plugin.

A note for plugin developers - when performing an upgrade, this plugin will first deactivate the target plugin and call the *deactivate* hook, if any. Then it will download the new version. If everything goes well, the new version will be then activated (*activate* hook will be called, if any). This is different from how the built-in updater works - it doesn't call the deactivation hook.

More info - [One Click Plugin Updater homepage](http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/ "One Click Plugin Updater Homepage")

== Installation ==

**Additional Requirements**

* The CURL library installed or "allow url fopen" enabled in php.ini *or* WP 2.5 and up. 
* The *plugins* directory needs to be writable by the webserver (if you plan to use the upgrade/installer features of this plugin). The exact permission requirements vary by server, though CHMOD 666 should be sufficient.

To install the plugin follow these steps :

1. Download the one-click-plugin-updater.zip file to your local machine.
1. Unzip the file 
1. Upload "one-click-plugin-updater" folder to the "/wp-content/plugins/" directory
1. Activate the plugin through the 'Plugins' menu in WordPress

That's it.