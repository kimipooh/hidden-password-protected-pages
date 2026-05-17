<?php
/*
Plugin Name: Hidden Password Protected Pages
Plugin URI: 
Description: The plugin is for hiding the password protected pages (posts) in WordPress.
Version: 1.3.0
Author: Kimiya Kitani
Author URI: https://profiles.wordpress.org/kimipooh/
Text Domain: wp-hidden-password-protected-page
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_hidden_password_protected_page_instance = new wphppp();

class wphppp{
	var $set_op = 'wp-hidden-password-protected-pages_array';	// Save setting name in DB
	var $plugin_name = 'wp-hidden-password-protected-page';
	var $lang_dir = 'lang';	// Language folder name
	var $cookie_time = 'wphppp_protected_cookie_time';
	var $cookie_time_max = 31622400;
	var $disabled_wphppp = 'wphppp_protected_disabled';
	var $settings;
	
	public function __construct(){
		$this->settings = get_option($this->set_op);
		$this->init_settings();
		register_activation_hook(__FILE__, array(&$this, 'installer'));
		// Add Setting to WordPress 'Settings' menu. 
		add_action('admin_menu', array(&$this, 'add_to_settings_menu'));

		// Main 
		if(!isset($this->settings[$this->disabled_wphppp]) || empty($this->settings[$this->disabled_wphppp])){
			add_filter('posts_where', array(&$this, 'my_posts_where'));
			// Hidden password protected pages in previous post and next post.
			add_filter('get_previous_post_where', array(&$this,'remove_password_post_links_adjacent'));
			add_filter('get_next_post_where', array(&$this,'remove_password_post_links_adjacent'));
		}
		// Optional
		add_action('after_setup_theme', array(&$this,'my_after_setup_theme'));
		
		// Hidden password protected pages in archives.
		if(!isset($this->settings[$this->disabled_wphppp]) || empty($this->settings[$this->disabled_wphppp]))
			add_filter( 'getarchives_where' , 'wp_hidden_password_protected_page_posts_archive_where' , 10 , 2 );

		function wp_hidden_password_protected_page_posts_archive_where($where,$r){
			global $wpdb; 
			return $where .= " AND $wpdb->posts.post_password = ''";
		}
	}
	public function remove_password_post_links_adjacent($where){
			return $where . " AND post_password = '' ";
	}
	public function my_posts_where($where){
		global $wpdb;
		if(!is_singular() && !is_admin())
			$where .= " AND $wpdb->posts.post_password = ''";

		return $where;
	}
	public function my_after_setup_theme(){ 
		$settings = get_option($this->set_op);
		if(isset($settings[$this->cookie_time]) && isset( $_COOKIE['wp-postpass_' . COOKIEHASH] )):
			$cookie_time = intval(sanitize_text_field($settings[$this->cookie_time])); // Empty or Error: return 0

			$co = sanitize_text_field( wp_unslash( $_COOKIE['wp-postpass_' . COOKIEHASH] ) );

			if($cookie_time > 0 && $cookie_time <= $this->cookie_time_max):
				setcookie('wp-postpass_' . COOKIEHASH,  $co , time()+$cookie_time, COOKIEPATH);
			elseif($cookie_time == -1):
				setcookie('wp-postpass_' . COOKIEHASH,  $co , 0, COOKIEPATH);
			endif;
		endif;
	}

	public function init_settings(){
		$this->settings['version'] = 125;
		$this->settings['db_version'] = 104;
	}
	
	public function installer(){
		update_option($this->set_op , $this->settings);
	}
	
	function add_to_settings_menu(){
		add_options_page(esc_html__('Hidden Password Protected Pages Settings', 'wp-hidden-password-protected-page'), esc_html__('Hidden Password Protected Pages Settings','wp-hidden-password-protected-page'), 'manage_options', 'wp-hidden-password-protected-page',array(&$this,'admin_settings_page'));
	}
	
	// Processing Setting menu for the plugin.
	function admin_settings_page(){
		$settings = get_option($this->set_op);
		$updated = false;
		$nonce_value = isset($_POST["whppp-form"]) ? sanitize_text_field( wp_unslash( $_POST["whppp-form"] ) ) : '';
		// The user who can manage the WordPress option can only access the Setting menu of this plugin.
		if(!current_user_can('manage_options')) wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-hidden-password-protected-page'));

		if($nonce_value):
			if(wp_verify_nonce($nonce_value, "whppp-nonce-key")):
				// Main
				if(isset($_POST[$this->disabled_wphppp])):
					$settings[$this->disabled_wphppp] =  sanitize_text_field( wp_unslash( $_POST[$this->disabled_wphppp] ) );
				else:
					$settings[$this->disabled_wphppp] = '';
				endif;

				// Optional
				if(isset($_POST[$this->cookie_time])):
					$cookie_time = intval(sanitize_text_field( wp_unslash( $_POST[$this->cookie_time] ) )); // Empty or Error: return 0
					if($cookie_time < -1 || $cookie_time > $this->cookie_time_max)
						$cookie_time = "";
					$settings[$this->cookie_time] =	$cookie_time;	
				else:
					$cookie_time = "";
				endif;
				
				update_option($this->set_op , $settings);
				$updated = true;
			endif;
		endif;
?>
<?php
  $cookie_time = "";
  if(isset($settings[$this->cookie_time])):
	$cookie_time = $settings[$this->cookie_time];
  endif;
  if($updated):
?>
<div class="<?php print esc_attr($this->plugin_name);?>_updated"><p><strong><?php esc_html_e('Updated', 'wp-hidden-password-protected-page'); ?></strong></p></div>
<?php
  endif;
?>
<div id="add_mime_media_admin_menu">
  <h2><?php esc_html_e('Hidden Password Protected Pages Settings', 'wp-hidden-password-protected-page'); ?></h2>
  
  <form method="post" action="">
	<?php // for CSRF (Cross-Site Request Forgery): https://propansystem.net/blog/2018/02/20/post-6279/
		wp_nonce_field("whppp-nonce-key", "whppp-form"); ?>  
     <fieldset style="border:1px solid #777777; width: 750px; padding-left: 6px;">
		<legend><h3><?php esc_html_e('How to use it', 'wp-hidden-password-protected-page'); ?></h3></legend>
		<div style="overflow:noscroll; height: 150px;">

		<p><?php esc_html_e('When the plugin is turned on, the password protected pages will be hidden. The user who knows the access URL continues to be able to access to the pages.', 'wp-hidden-password-protected-page'); ?></p><p><?php esc_html_e('The unlocked password protected page will be locked again after the idle time (Value of Idle time for Password Protected Pages).', 'wp-hidden-password-protected-page'); ?></p>
		</div>
	 </fieldset>
	 <br/><br/>
     <fieldset style="border:1px solid #777777; width: 750px; padding-left: 6px;">
		<legend><h3><?php esc_html_e('Turn off the plugin except Optional Settings.', 'wp-hidden-password-protected-page'); ?></h3></legend>
		<div style="overflow:noscroll; height: 120px;">
		<p>
                <?php $empty_flag = ""; if(!empty($settings[$this->disabled_wphppp])) $empty_flag = 'checked'; ?>
		<input type="checkbox" name="<?php print esc_attr($this->disabled_wphppp);?>" value="disabled" <?php checked($empty_flag, 'checked'); ?>/>
			<?php esc_html_e('Turn off Hidden Password Protected Pages except Optional Settings.', 'wp-hidden-password-protected-page'); ?><br/>
		</p>
</p>
<br/>
		<input type="submit" value="<?php esc_attr_e('Save', 'wp-hidden-password-protected-page');  ?>" />
		</div>
	</fieldset>
	 <br/><br/>
     <fieldset style="border:1px solid #777777; width: 750px; padding-left: 6px;">
		<legend><h3><?php esc_html_e('Optional Settings', 'wp-hidden-password-protected-page'); ?></h3></legend>
		<div style="overflow:noscroll; height: 200px;">

		<table><tr><td><strong>
		<?php esc_html_e('Idle time for Password Protected Pages: ', 'wp-hidden-password-protected-page'); ?> <input name="<?php print esc_attr($this->cookie_time);?>" type="text" value="<?php print esc_attr($cookie_time); ?>" size="15" maxlength="15"/> <?php esc_html_e('sec.', 'wp-hidden-password-protected-page'); ?></strong>
		<br/>
		<ul>
			<li><?php esc_html_e('[Default]: 864,000 sec (10 days).', 'wp-hidden-password-protected-page'); ?> </li>
			<li><?php esc_html_e('[Always Confirm Password]: -1', 'wp-hidden-password-protected-page'); ?></li>
			<li><?php esc_html_e('[Disable/Turn off]: empty, 0, less than -1, or more than 31,622,400 (366 days)', 'wp-hidden-password-protected-page'); ?><br/> * <?php esc_html_e('[Default] setting is used.', 'wp-hidden-password-protected-page'); ?></li>
		</ul>
		<br/>
    	 <input type="submit" value="<?php esc_attr_e('Save', 'wp-hidden-password-protected-page');  ?>" />
  		</form>
		</div>
		 </td></tr>
		</table>
	    </div>
     </fieldset>
<?php
	$args = apply_filters('whppp_get_protected_page_args',array(
		'has_password' 	=> true,
		'numberposts' 	=> -1,
		'orderby'         => 'post_modified',
		'order'			=> 'DESC',
	));
	
	$posts_list = get_posts($args);
	if($posts_list):	
?>
	 <br/><br/>
     <fieldset style="border:1px solid #777777; width: 750px; padding-left: 6px;">
		<legend><h3><?php esc_html_e('List of Password Protected Pages', 'wp-hidden-password-protected-page'); ?></h3></legend>
		<div style="overflow:scroll; height: 200px;">

		<table><tr><td><?php esc_html_e('Last modified', 'wp-hidden-password-protected-page'); ?> 
			(<?php esc_html_e('Author', 'wp-hidden-password-protected-page'); ?>)
			<?php esc_html_e('Title', 'wp-hidden-password-protected-page'); ?><br/>
			<ol>
<?php
			foreach($posts_list as $l_post): 
				if(empty($l_post->post_title)) continue;
				$userinfo = get_userdata($l_post->post_author);
				echo '<li>' . esc_html($l_post->post_modified);
				echo ' (' . esc_html($userinfo->user_login)  . ') ';
				echo '<a href="' . esc_url(get_permalink($l_post->ID)) . '">' . esc_html($l_post->post_title) . '</a>' . "\n";
		endforeach;	
?>
		</ol></td></tr></table>
	    </div>
     </fieldset>
<?php
	endif; // close list of password protected page
?>
<?php 
	} // close admin_settings_page function
} // close class
