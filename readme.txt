=== One Click Plugin Updater ===
Contributors: whiteshadow
Tags: plugin, notification, upload, files, installation, admin, update, upgrade, update notification, automation
Requires at least: 2.3
Tested up to: 2.9
Stable tag: 2.4.14

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

== Changelog ==

= 2.4.13 =
* Fix (probably) a rare issue where the update would fail with "Invalid argument supplied to foreach()..."

= 2.4.12 =
* The correct menu icons now show up in the Ozh's Admin Menu. Previously the updater plugin would have the default icon due to a (still unresolved) bug in Ozh's plugin.
* Hiding the update count blurb thingy works again.

= 2.4.11 =
* *There are no release notes for this version*

= 2.4.10 =
* Maybe fix a situation where redundant update notifications would still show up after an update.

= 2.4.9 =
* Fix some possible post-upgrade issues.

= 2.4.8 =
* Partial compatibility with WP 2.8
* Finally fixed mixed update highlights. Now they work 100% correctly at least in WP 2.8.
* Fixed a mysterious issue where some update notifications wouldn't show up.
* Fixed the bug where updates would show for deleted plugins.

= 2.4.7 =
* Fix a conflict with Ozh's "Better Plugin Page"
* Fix highlight bars not displaying for active plugins (finally)

= 2.4.6 =
* (Hopefully) Fix problems with internal settings not being initialized properly.

= 2.4.5 =
* Initialize 'update\_plugins\_enabled' properly.
* Note : This plugin is largely obsolete. The update is provided for the benefit of people who still use it for one reason or another.

= 2.4.4 =
* Now only admins will see global plugin update notifications.

= 2.4.3 =
* Another attempt to fix permission issues with temporary files. Gah.
* Grr.

= 2.4.2 =
* Fix : Fatal error when using "Upgrade All" to update this plugin and another active plugin(s).
* New : Menu icons for Ozh's Admin Dropdown Menu. I used icons from the famous FamFamFam; modified some of them.

= 2.4.1 =
* Use the linux command "unzip" when PclZip doesn't recognize the .zip file. Previously unzip was used only when *PclZip wasn't available*.

= 2.4 =
* New : Disable update notifications for inactive plugins.
* New : Hide update count pop-up/blurb thing in the dashboard menu.
(hopefully) Fix : Some cases of "can't create temporary file" errors.
* Fix : Now the plugin will only load in the admin backend, not on every page load. A tiny performance improvement.
* Internal : Added a few more comments and cleaned up some orphaned code (some still left).

= 2.3 =
* New : You can turn off plugin highlights.
* Internal : "Permanent notices" and related functions. To be used later.
* Minor fix : make the activation hook work when called from plugins\_loaded

= 2.2.9 =
* Update the after\_plugin\_row hook to use the second parameter ($plugin\_data) introduced in WP 2.6

= 2.2.8 =
* *There are no release notes for this version*

= 2.2.7 =
* Fix some DOM expression stuff for both 2.5 and 2.6 compatibility.

= 2.2.6 =
* Fix: stristr() syntax error in main file.
* Fix: "Delete" links not appearing for themes in 2.6.

= 2.2.5 =
* Minor modifications for tentative WP 2.6 compatibility (WorksForMe TM)

= 2.2.4 =
* Silly PHP. Making realpath() inconsistent. Phe. 
* Removed some references to DIRECTORY\_SEPARATOR.

= 2.2.3 =
* Fix the "Delete" link not appearing on non-English blogs.

= 2.2.2 =
* *There are no release notes for this version*

= 2.2.1 =
* Fix the miniguide option.
* Fixed two silly bugs.
* Fixed jQuery not properly added to the plugins/themes pages.
* Removed A forgotten debug output statement.

= 2.2 =
* *** Major update ***
* New FireFox extension. Not backwards compatible anymore.
* Can delete themes.
* Some bugs fixed.
* Improved security.
* Global update notifications now *really* "global", i.e. updated on any page, not just the Plugins tab.
* Stuff I've already forgotten about.
* Fix a typo in readme.txt
* Fix handleOneClick(). damn!

= 2.1.2 =
* WP 2.5.1 compatibility note. Probably nothing more.

= 2.1.1 =
* Hopefully fix: "Update available" messages still showing after a plugin is upgraded.
* Fix a possible bug with update notices disappearing.

= 2.1 =
* New: Global plugin update notices.
* New: Ability to delete plugins (experimental).

= 2.0.9 =
* (Hopefully) Fix: PclZip platform independence bug.

= 2.0.8 =
* *There are no release notes for this version*

= 2.0.7 =
* *There are no release notes for this version*

= 2.0.6 =
* Fix: The wrong plugin getting activated when upgrading many plugins of which only one is active.
* Fix: Wrong plugin getting activated (see prev. msg.).
* Fix: Update notice remains for deleted plugins.
* Fix: Not checking for enabled notifications when a plugin is deleted.

= 2.0.5 =
* Temporary workaround for the is\_dir() bug.

= 2.0.4 =
* Minor bugfixes + a hidden feature

= 2.0.3 =
* Fix: file\_put\_contents missing in PHP 4

= 2.0.2 =
* Fix typo
* Fix: trying to create a directory with no name.
* Fix: WP 2.3.x has no "upgrade now" link.
* Added: More debug messages.
* Damn you WP, foiled again!

= 2.0.1 =
* *There are no release notes for this version*

= 2.0 =
* Lots of new features and bugs!
* Hackety-hack!
* Fixed tags in readme.txt and such.

= 1.1.7 =
* Fix problems caused by wordpress.org structural change.

= 1.1.6 =
* Alternate temporary file handling - use the plugin's directory.

= 1.1.5 =
* PclZip error reporting fix + debug mode

= 1.1.3 =
* *There are no release notes for this version*

= 1.1.2 =
* Bugfix : unzip -fod ...

= 1.1.1 =
* *There are no release notes for this version*

= 1.1 =
* New feature : Mark plugins with update notifications enabled.

= 1.0.5 =
* Now works in Internet Explorer.

= 1.0.4 =
* *There are no release notes for this version*

= 1.0.3 =
* *There are no release notes for this version*

= 1.0.2 =
* *There are no release notes for this version*

= 1.0.1 =
* *There are no release notes for this version*

= 1.0 =
* *There are no release notes for this version*

