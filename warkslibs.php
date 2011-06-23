<?php
/*
Plugin Name: Warwickshire Libraries
Plugin URI: https://github.com/ostephens/savelibs
Description: WordPress plugin to add items from Warwickshire libraries to a blogpost
Version: 0.1
Author: Owen Stephens
License: GPL2

*/

/*

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

/* To Do and Notes
1. Really the page created by the shortcode should only be visible to logged in users with appropriate permissions - perhaps should be in Admin area?
*/


/////////// set up activation and deactivation stuff
register_activation_hook(__FILE__,'warkslibs_install');

function warkslibs_install() {
	// do stuff when installed
	global $wp_version;
	if (version_compare($wp_version, "3", "<")) {
		deactivate_plugins(basename(__FILE__)); // deactivate plugin
		wp_die("This plugin requires WordPress Version 3 or higher.");
		// ???relies on curl so check for it 
		// if going to use simple web need to check for it - complicated?
	} else {
		// What here?
	}
}


register_deactivation_hook(__FILE__,'warkslibs_uninstall');

function warkslibs_uninstall() {
	// do stuff
}


/////////// set up option storing stuff
// create array of options
// Want this to be repeatable values of barcode/pin so can support for multiple accounts
// For now assume just one account
// Note that this didn't work for Mia...

$warkslibs_options_arr=array(
	"warkslibs_user_barcode"=>'',
	"warkslibs_user_pin"=>'',
	);

// store them
update_option('warkslibs_plugin_options',$warkslibs_options_arr); 

// get them
$warkslibs_options_arr = get_option('warkslibs_plugin_options');

// use them. 
$warkslibs_user_barcode = $warkslibs_options_arr["warkslibs_user_barcode"];
$warkslibs_user_pin = $warkslibs_options_arr["warkslibs_user_pin"];
// end option array setup

// required in WP 3 but not earlier?
add_action('admin_menu', 'warkslibs_plugin_menu');

/////////// set up stuff for admin options pages
// add submenu item to existing WP menu
function warkslibs_plugin_menu() {
	add_options_page('Warwickshire Libraries Settings Page', 'Warwickshire Libraries Settings', 'manage_options', __FILE__, 'warkslibs_settings_page');
}

// call register settings function before admin pages rendered
add_action('admin_init', 'warkslibs_register_settings');

function warkslibs_register_settings() {
	// register settings - array, not individual
	register_setting('warkslibs-settings-group', 'warkslibs_settings_values');
}

// write out the plugin options form. Form field name must match option name.
// add other options here as necessary e.g. new API URLs or updated defaults
function warkslibs_settings_page() {
  
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	?>
		<div>
		<h2><?php _e('Warwickshire Libraries plugin options', 'warkslibs-plugin') ?></h2>
		<form method="post" action="options.php">
		<?php settings_fields('warkslibs-settings-group'); ?>

  		<?php _e('Barcode (from your Warwickshire Library card)','warkslibs-plugin') ?> 
  
  		<?php warkslibs_user_barcode(); ?><br />

  		<?php _e('PIN (usually your date of birth)','warkslibs-plugin') ?> 
  
  		<?php warkslibs_user_pin(); ?><br />

  		<p class="submit"><input type="submit" class="button-primary" value=<?php _e('Save changes', 'warkslibs-plugin') ?> /></p>
  		</form>
  		</div>

<?php
}

// get options from array and display as fields

function warkslibs_user_barcode() {
	// load options array
	$warkslibs_options = get_option('warkslibs_settings_values');
  
	$warkslibs_user_barcode = $warkslibs_options['warkslibs_user_barcode'];
  
	// display form field
	echo '<input type="text" name="warkslibs_settings_values[warkslibs_user_barcode]" 
		value="'.esc_attr($warkslibs_user_barcode).'" />';
}

function warkslibs_user_pin() {
	// load options array
	$warkslibs_options = get_option('warkslibs_settings_values');
  
	$warkslibs_user_pin = $warkslibs_options['warkslibs_user_pin'];
  
	// display form field
	echo '<input type="text" name="warkslibs_settings_values[warkslibs_user_pin]" 
		value="'.esc_attr($warkslibs_user_pin).'" />';
}


/*
 * set up shortcode Sample: [warkslibs]
 */
function warkslibsShortCode($atts, $content=null) {
  
	if(@is_file(ABSPATH.'/wp-content/plugins/warkslibs/warkslibs_functions.php')) {
		include_once(ABSPATH.'/wp-content/plugins/warkslibs/warkslibs_functions.php'); 
	}
	// Want to have Search Options and Loan History and Current Loans
	// Focus on Loan History/Current Loans first
	//
  	// $search_terms = stripslashes($_POST['search_term']); // the free-text search field
  	// $search_title = stripslashes($_POST['search_title']); // the free-text search field
	$loans = $_POST['loans'];
	$blogem = $_POST['blogem'];


	if ($loans == "history") {
    	// process - deal with search, display results and import into db
		echo '<p>Searching now...</p>';  
		warkslibsGetLoanHistory();
	} elseif ($loans == "current") {
		echo '<p>Searching now ..</p>';
		warkslibsGetCurrentLoans();
	} elseif ($blogem == "yes"){
		// This is single post creation - want to enable creation of multiple posts - e.g. 'select all' from loan history and post
		// This implies an array rather than a string
		foreach($_POST['libitem'] as $item)  {
			/*
			if ($item[choose] = 1) {
				echo "\n";
				print_r($item);
				echo "\n".$item[title]."\n";
				$libitem['title'] = $item[title];
				$libitem['auth'] = $item[auth];
				$libitem['image'] = $item[image];
				$libitem['isbn'] = $item[isbn];
				$libitem['id'] = $item[id];
				$libitem['price'] = 0;
			}
	    	$warkslibs_new_post_IDs = createwarkslibsPost($libitem);
			*/
			if ($item[choose]){
				$libitem['title'] = $item[title];
				$libitem['auth'] = $item[auth];
				$libitem['image'] = $item[image];
				$libitem['isbn'] = $item[isbn];
				$libitem['id'] = $item[id];
				$libitem['price'] = 0;
				$warkslibs_new_post_IDs = createwarkslibsPost($libitem);
			}
	    //	echo '<h3>Added '.$object_url.'</h3>';
		//	echo '<a href="../wp-admin/post.php?post='.$warkslibs_new_post_ID.'&action=edit">Go Edit</a>';			
		}
  	} else {
    // display search box and instructions
    warkslibsPrintSearchForm();
  }
}

// Add the shortcode
add_shortcode('warkslibs', 'warkslibsShortCode');

?>