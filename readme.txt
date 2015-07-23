=== Plugin Load Filter ===
Contributors: enomoto celtislab
Tags: plugin, dynamic activate, dynamic deactivate, filter, logic
Requires at least: 3.9
Tested up to: 4.2
Stable tag: 2.2.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Dynamically activate the selected plugins for each page. Response will be faster by filtering plugins.

== Description ==

Although have installed a lot of plugins, if you do not want to activate for all of the pages, you will be able to deactivate unnecessary plugins of each individual page.
Through the filter activation of plugins, you can speed up the display response.

Features

 * Support Post Format type
 * Support Custom Post type
 * Support Jetpack Modules filtering

If the case other than blog, such as to provide some service as a Web application, you can also distinguish the plugins for blog and Web applications.


[日本語の説明](http://celtislab.net/wp_plugin_load_filter/ "Documentation in Japanese")

== Installation ==

1. Upload the `plugin-load-filter` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress
3. Set up from `Plugin Load Filter` to be added to the Plugins menu of Admin mode.

Note

 * This plugin is required PHP 5.3 or higher
 * This plugin only single-site.
 * This plugin to automatically activated as must-use plugin installed plf-filter.php file to MU-plugins folder. Depending on the permissions of the folders and files there is a possibility that it is not possible to install the plf-filter.php file.
 * There is also plugins that can not be filtering, such as cache plugins or must-use plugins.

Usage

 * Set as necessary 4 types of filters (Filter Registration)
 
  * Admin Filter : Register the plugins to be used only in admin mode.
  * Desktop/Mobile Filter : Register the plugins to be used only in desktop/moble device. (wp_is_mobile function use)
  * Page Filter : Register the plugins for selecting whether to activate each Page type or Post. Page Filter registration plugins are once blocked, but is activated by `Page filter Activation` setting.

 * Select the plugins from `Page Filter` registration to activate (Page filter Activation)

  * Select the plugins that you want to activate for each Page type or Post Format type or Custom Post type.
  * Can be selected plugins to activate from Post content editing screen

 * Check

  * Please perform sufficient test whether the setting is working as expected.
  * Please also check the operation if you add or remove a plugin.
  * Filter priority : Admin Filter > Desktop Filter > Mobile Filter > Page Filter (Each Page setting > Page type setting) 
 

== Screenshots ==

1. Filter Registration setting.
2. Page Filter Activation setting.
3. Setting of each post

== Changelog ==

= 2.0.0 =
* 2015-04-16  Release
 
= 2.0.1 =
* 2015-04-22  Exclude GET request(with? Parameters) to the home page from the filter. For example, Link to download the Download Manager plugins.

= 2.1.0 =
* 2015-04-30  Change user interface option settings screen.

= 2.2.0 =
* 2015-07-23  Code cleanups (Stop the use of transient API cache of intermediate processing data)
