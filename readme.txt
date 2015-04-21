=== Exif-Remove-ImageMagick ===
Contributors: RichieB, CupRacer, orangelab
Stable tag: 1.1
Tags: image, images, picture, imagemagick, exif, clean, remove
Requires at least: 2.9
Tested up to: 3.6.1


This plugin removes Exif data from uploaded images automatically.


== Description ==

The plugin affects the image while it is uploaded. Straight
after it is uploaded, all Exif data will be removed from the image.
There is no original image left, nor made a backup.
This plugin requires the ImageMagick php modules.

Based on exif-remove by CupRacer
http://www.mynakedgirlfriend.de/wordpress/exif-remove/

Uses code from imagemagick-enigine by orangelab
http://wordpress.org/extend/plugins/imagemagick-engine/

ATTENTION: This service is intended to save your privacy and 
should not be used for illegal activity like copyright violations. 
You are allowed to use this service for legal activity only, 
this includes that you must have the explicit permission 
from the holder of rights to clean a file with this plugin!
As of now, to prevent abuse, the eventually existing 
copyright information will be kept inside the cleaned image.
To disable this plugin temporarily, just deactivate it within the
Exif-Remove settings menu. Thanks.


== Installation ==

1. Upload the directory 'exif-remove-imagemagick' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make your settings through the 'Settings' menu in WordPress
4. Upload images while writing posts and pages.


== Frequently Asked Questions ==

= How can I check whether this plugin is installed correctly or not? =

This Plugin is working transparently within your WordPress blog.
There should be a settings menu called "EXIF Remove IM" where you are able
to (de)activate the plugin. The upload dialogs (in articles/pages) will
not be modified by this plugin, so you won't see any difference.
You may also check your images with http://www.exif-viewer.de/ .


== Screenshots ==

None.


== Changelog ==

= 1.1 =
* Updated for Wordpress 3.6.1

= 1.0 =
* This is the initial version.

== Upgrade Notice ==

= 1.1 =
None.

= 1.0 =
None.


