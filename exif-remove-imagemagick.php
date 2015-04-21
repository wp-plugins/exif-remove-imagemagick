<?php
/*
Plugin Name: EXIF-Remove-ImageMagick
Plugin URI: http://www.vdberg.org/~richard/exif-remove-imagemagick.html
Description: Automatically remove exif data after uploading JPG files using ImageMagick
Author: Richard van den Berg
Version: 1.1
Author URI: http://www.vdberg.org/~richard/
Text Domain: exif-remove-imagemagick

Copyright (C) 2011 Richard van den Berg

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


if (!defined('ABSPATH'))
	die('Must not be called directly');


/*
 * Constants
 */
define('ERI_OPTION_VERSION', 1);

/*
 * Global variables
 */

// Plugin options default values -- change on plugin admin page
$eri_options_default = array('enabled' => false
			     , 'mode' => null
			     , 'cli_path' => null
			     , 'version' => ERI_OPTION_VERSION
			     );

// Available modes
$eri_available_modes = array('php' => "Imagick PHP module"
			     , 'cli' => "ImageMagick command-line");

// Current options
$eri_options = null;

// Keep track of attachment file & sizes between different filters
$eri_image_sizes = null;
$eri_image_file = null;

/* init */
add_action('plugins_loaded', 'eri_init');

function eri_init() {
        if (eri_active()) {
		add_action('wp_handle_upload', 'eri_exifremoveupload_clean'); // apply our modifications
	}
	if (is_admin()) {
		add_filter('plugin_action_links', 'eri_filter_plugin_actions', 10, 2 );
		add_action('admin_menu', 'eri_admin_menu' );
	}
}

function eri_exifremoveupload_clean($array) {
	// $array contains file, url, type

	if ($array['type'] == 'image/jpeg' || $array['type'] == 'image/jpg') {
		eri_im_remove_exif($array['file']);
	}

	return $array;
}


/* Are we enabled with valid mode? */
function eri_active() {
	return eri_get_option("enabled") && eri_mode_valid();
}

/* Check if mode is valid */
function eri_mode_valid($mode = null) {
	if (empty($mode))
		$mode = eri_get_option("mode");
	$fn = 'eri_im_' . $mode . '_valid';
	return (!empty($mode) && function_exists($fn) && call_user_func($fn));
}

// Check version of a registered WordPress script
function eri_script_version_compare($handle, $version, $compare = '>=') {
	global $wp_scripts;
	if ( !is_a($wp_scripts, 'WP_Scripts') )
		$wp_scripts = new WP_Scripts();

	$query = $wp_scripts->query($handle, 'registered');
	if (!$query)
		return false;

	return version_compare($query->ver, $version, $compare);
}


/*
 * Plugin option handling
 */

// Setup plugin options
function eri_setup_options() {
	global $eri_options;

	// Already setup?
	if (is_array($eri_options))
		return;

	$eri_options = get_option("eri_options");

	// No stored options yet?
	if (!is_array($eri_options)) {
		global $eri_options_default;
		$eri_options = $eri_options_default;
	}

	// Do we need to upgrade options?
	if (!array_key_exists('version', $eri_options)
	    || $eri_options['version'] < ERI_OPTION_VERSION) {
		
		/*
		 * Future compatability code goes here!
		 */
		
		$eri_options['version'] = ERI_OPTION_VERSION;
		eri_store_options();
	}
}

// Store plugin options
function eri_store_options() {
	global $eri_options;

	eri_setup_options();

	$stored_options = get_option("eri_options");
	
	if ($stored_options === false)
		add_option("eri_options", $eri_options, null, false);
	else
		update_option("eri_options", $eri_options);
}

// Get plugin option
function eri_get_option($option_name, $default = null) {
	eri_setup_options();
	
	global $eri_options, $eri_options_default;

	if (array_key_exists($option_name, $eri_options))
		return $eri_options[$option_name];

	if (!is_null($default))
		return $default;

	if (array_key_exists($option_name, $eri_options_default))
		return $eri_options_default[$option_name];

	return null;
}

// Set plugin option
function eri_set_option($option_name, $option_value, $store = false) {
	eri_setup_options();
	
	global $eri_options;

	$eri_options[$option_name] = $option_value;

	if ($store)
		eri_store_options();
}


function eri_im_remove_exif($file) {
	$mode = eri_get_option("mode");
	$fn = 'eri_im_' . $mode . '_valid';
	if (empty($mode) || !function_exists($fn) || !call_user_func($fn))
		return false;

	$fn = 'eri_im_' . $mode . '_remove_exif';
	return (function_exists($fn) && call_user_func($fn, $file));
}

function eri_im_filename_is_jpg($filename) {
	$info = pathinfo($filename);
	$ext = $info['extension'];
	return (strcasecmp($ext, 'jpg') == 0) || (strcasecmp($ext, 'jpeg') == 0);
}

/*
 * PHP ImageMagick ("Imagick") class handling
 */

// Does class exist?
function eri_im_php_valid() {
	return class_exists('Imagick');
}

// Strip file using PHP Imagick class
function eri_im_php_remove_exif($file) {
	$im = new Imagick($file);
	if (!$im->valid())
		return false;

	try {
		$im->stripImage();
		$im->writeImage($file);
		$im->clear();
		$im->destroy();
	} catch(Exception $e) {
		return false;
	}

	return true;
}

function eri_im_cli_valid() {
	$cmd = eri_im_cli_command();
	return !empty($cmd) && is_executable($cmd);
}

function eri_im_cli_check_executable($fullpath) {
	if (!is_executable($fullpath))
		return false;

	@exec($fullpath . ' --version', $output);

	return count($output) > 0;
}

function eri_im_cli_check_command($path, $executable='mogrify') {
	$path = realpath($path);
	if (!is_dir($path))
		return null;

	$cmd = $path . '/' . $executable;
	if (eri_im_cli_check_executable($cmd))
		return $cmd;

	$cmd = $cmd . '.exe';
	if (eri_im_cli_check_executable($cmd))
		return $cmd;

	return null;
}

function eri_im_cli_find_command($executable='mogrify') {
	$possible_paths = array("/usr/bin", "/usr/local/bin");

	foreach ($possible_paths AS $path) {
		/*
		 * This operation would give a warning if path is restricted by
		 * open_basedir.
		 */
		$path = @realpath($path);
		if (!$path)
			continue;
		if (eri_im_cli_check_command($path, $executable))
			return $path;
	}

	return null;
}

function eri_im_cli_command($executable='mogrify') {
	$path = eri_get_option("cli_path");
	if (!empty($path))
		return eri_im_cli_check_command($path, $executable);

	$path = eri_im_cli_find_command($executable);
	if (empty($path))
		return null;
	eri_set_option("cli_path", $path, true);
	return eri_im_cli_check_command($path, $executable);
}

function eri_im_cli_remove_exif($file) {
	$cmd = eri_im_cli_command();
	if (empty($cmd))
		return false;

	$file = addslashes($file);

	$cmd = "\"$cmd\" -strip '{$file}'";
	exec($cmd);

	return file_exists($file);
}


function eri_ajax_test_im_path() {
	if (!current_user_can('manage_options'))
		die();
	$r = eri_im_cli_check_command($_REQUEST['cli_path']);
	echo empty($r) ? "0" : "1";
	die();
}


function eri_admin_menu() {
	$page = add_options_page('EXIF Remove using ImageMagick', 'EXIF Remove IM', 8, 'exif-remove-imagemagick', 'eri_option_page');
}

function eri_option_admin_images_url() {
	return get_bloginfo('wpurl') . '/wp-admin/images/';
}

function eri_option_status_icon($yes = true) {
	return eri_option_admin_images_url() . ($yes ? 'yes' : 'no') . '.png';
}

function eri_option_display($display = true, $echo = true) {
	if ($display)
		return '';
	$s = ' style="display: none" ';
	if ($echo)
		echo $s;
	return $s;
}

/* Add settings to plugin action links */
function eri_filter_plugin_actions($links, $file) {
	if($file == plugin_basename(__FILE__)) {
		$settings_link = "<a href=\"options-general.php?page=exif-remove-imagemagick\">"
			. __('Settings', 'exif-remove-imagemagick') . '</a>';
		array_unshift( $links, $settings_link ); // before other links
	}

	return $links;
}


function eri_option_page() {
	global $eri_available_modes;

	if (!current_user_can('manage_options'))
		wp_die('Sorry, but you do not have permissions to change settings.');

	/* Make sure post was from this page */
	if (count($_POST) > 0)
		check_admin_referer('eri-options');

	/* Should we update settings? */
	if (isset($_POST['update_settings'])) {
		$new_enabled = isset($_POST['enabled']) && !! $_POST['enabled'];
		eri_set_option('enabled', $new_enabled);
		if (isset($_POST['mode']) && array_key_exists($_POST['mode'], $eri_available_modes))
			eri_set_option('mode', $_POST['mode']);
		if (isset($_POST['cli_path']))
			eri_set_option('cli_path', realpath($_POST['cli_path']));

		eri_store_options();
		
		echo '<div id="message" class="updated fade"><p>'
			. __('Settings updated', 'exif-remove-imagemagick')
			. '</p></div>';
	}

	$modes_valid = $eri_available_modes;
	$any_valid = false;
	foreach($modes_valid AS $m => $ignore) {
		$modes_valid[$m] = eri_mode_valid($m);
		if ($modes_valid[$m])
			$any_valid = true;
	}

	$current_mode = eri_get_option('mode');
	if (!$modes_valid[$current_mode])
		$current_mode = null;
	if (is_null($current_mode) && $any_valid) {
		foreach ($modes_valid AS $m => $valid) {
			if ($valid) {
				$current_mode = $m;
				break;
			}
		}
	}

	$enabled = eri_get_option('enabled') && $current_mode;
	
	$cli_path = eri_get_option('cli_path');
	if (is_null($cli_path))
		$cli_path = eri_im_cli_command();
	$cli_path_ok = eri_im_cli_check_command($cli_path);

	if (!$any_valid)
		echo '<div id="warning" class="error"><p>'
			. sprintf(__('No valid ImageMagick mode found! Please check %sFAQ%s for installation instructions.', 'exif-remove-imagemagick'), '<a href="http://wp.orangelab.se/exif-remove-imagemagick/documentation#installation">', '</a>')
			. '</p></div>';
	elseif (!$enabled)
		echo '<div id="warning" class="error"><p>'
			. __('EXIF Remove using ImageMagick is not enabled.', 'exif-remove-imagemagick')
			. '</p></div>';
?>
<div class="wrap">
  <div id="regen-message" class="hidden updated fade"></div>
  <h2><?php _e('EXIF Remove using ImageMagick Settings','exif-remove-imagemagick'); ?></h2>
  <p>This plugin does exactly what it says: it will remove EXIF data from uploaded images (JPG only). Nothing more, nothing less.</p>
  <p>Your file will be modified, there will not be a copy or backup with the original content.</p>
  <p>Uncheck the enable option below if you don't want to remove EXIF information, this way you don't have to deactivate the plugin in case you don't want to clean your images for a while.</p>
  <form action="options-general.php?page=exif-remove-imagemagick" method="post" name="update_options">
    <?php wp_nonce_field('eri-options'); ?>
  <div id="post-body">
    <div id="post-body-content">
      <div id="eri-settings" class="postbox">
	<h3 class="hndle"><span><?php _e('Settings', 'exif-remove-imagemagick'); ?></span></h3>
	<div class="inside">
	  <table class="form-table">
	    <tr>
	      <th scope="row" valign="top"><?php _e('Enable removing of EXIF data','exif-remove-imagemagick'); ?>:</th>
	      <td>
		<input type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? " CHECKED " : ""; echo $any_valid ? '' : ' disabled=disabled '; ?> />
	      </td>
	    </tr>
	    <tr>
	      <th scope="row" valign="top"><?php _e('Image engine','exif-remove-imagemagick'); ?>:</th>
	      <td>
		<select id="eri-select-mode" name="mode">
		  <?php
		      foreach($modes_valid AS $m => $valid) {
			      echo '<option value="' . $m . '"';
			      if ($m == $current_mode)
				      echo ' selected=selected ';
			      echo '>' . $eri_available_modes[$m] . '</option>';
		      }
		  ?>
		</select>
	      </td>
	    </tr>
	    <tr id="eri-row-php">
	      <th scope="row" valign="top"><?php _e('Imagick PHP module','exif-remove-imagemagick'); ?>:</th>
	      <td>
		<img src="<?php echo eri_option_status_icon($modes_valid['php']); ?>" />
		<?php echo $modes_valid['php'] ? __('Imagick PHP module found', 'exif-remove-imagemagick') : __('Imagick PHP module not found', 'exif-remove-imagemagick'); ?>
	      </td>
	    </tr>
	    <tr id="eri-row-cli">
	      <th scope="row" valign="top"><?php _e('ImageMagick path','exif-remove-imagemagick'); ?>:</th>
	      <td>
		<img id="cli_path_yes" class="cli_path_icon" src="<?php echo eri_option_status_icon(true); ?>" alt="" <?php eri_option_display($cli_path_ok); ?> />
		<img id="cli_path_no" class="cli_path_icon" src="<?php echo eri_option_status_icon(false); ?>" alt="<?php _e('Command not found', 'qp-qie'); ?>"  <?php eri_option_display(!$cli_path_ok); ?> />
		<img id="cli_path_progress" src="<?php echo eri_option_admin_images_url(); ?>wpspin_light.gif" alt="<?php _e('Testing command...', 'qp-qie'); ?>"  <?php eri_option_display(false); ?> />
		<input id="cli_path" type="text" name="cli_path" size="<?php echo max(30, strlen($cli_path) + 5); ?>" value="<?php echo $cli_path; ?>" />
	      </td>
	    </tr>
	    <tr>
	      <td>
		<input class="button-primary" type="submit" name="update_settings" value="<?php _e('Save Changes', 'exif-remove-imagemagick'); ?>" />
	      </td>
	    </tr>
	  </table>
	</div>
      </div>
    </div>
  </div>
  </form>
</div>
<?php
}
?>
