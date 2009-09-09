<?php
/*
Plugin Name: One Click Plugin Updater
Plugin URI: http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/
Description: Upgrade plugins with a single click, install new plugins or themes from an URL or by uploading a file, see which plugins have update notifications enabled, control how often WordPress checks for updates, and more. 
Version: 2.4.14
Author: Janis Elsts
Author URI: http://w-shadow.com/blog/
*/

/*
Created by Janis Elsts (email : whiteshadow@w-shadow.com) 
It's GPL.
*/

//This plugin only needs to run on the admin end.
//TODO: figure out why this optimization doesn't work on some systems. PHP issue?
//if (is_admin() || defined('MUST_LOAD_OCPU')) {

if (!function_exists('file_put_contents')){
	//a simplified file_put_contents function for PHP 4
	function file_put_contents($n, $d, $flag = false) {
	    $f = @fopen($n, 'wb');
	    if ($f === false) {
	        return 0;
	    } else {
	        if (is_array($d)) $d = implode($d);
	        $bytes_written = fwrite($f, $d);
	        fclose($f);
	        return $bytes_written;
	    }
	}
}

//Make sure some useful constants are defined
if ( ! defined( 'WP_CONTENT_URL' ) )
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
if (!defined('DIRECTORY_SEPARATOR')){
	define('DIRECTORY_SEPARATOR', '/');
}

if (!class_exists('ws_oneclick_pup')) {

class ws_oneclick_pup {
	var $myfile='';
	var $myfolder='';
	var $mybasename='';
	var $debug=false;
	var $debug_log;
	
	var $update_enabled='';
	
	var $options;
	var $options_name='ws_oneclick_options';
	var $defaults;
	var $ff_extension_url = 'https://addons.mozilla.org/en-US/firefox/addon/7511'; 

	function ws_oneclick_pup() {
		$my_file = str_replace('\\', '/',__FILE__);
		$my_file = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', $my_file);

		$this->myfile=$my_file;
		$this->myfolder=basename(dirname(__FILE__));
		$this->mybasename=plugin_basename(__FILE__);
		
		$this->debug_log = array();
		
		$this->defaults = array(
			'updater_module' => 'updater_plugin',
			'enable_plugin_checks' => true,
			'enable_wordpress_checks' => true,
			'anonymize' => false,
			'plugin_check_interval' => 43200,
			'wordpress_check_interval' => 43200,
			'global_notices' => $this->is_wp25(),
			'new_file_permissions' => 0755, 
			'debug' => false,
			'magic_key' => '',
			'confirm_remote_installs' => true,
			'show_miniguide' => false,
			'oneclick_deactivated_notice' => false,
			'mark_plugins_with_notifications' => true,
			'hide_notifications_for_inactive' => false,
			'hide_update_count_blurb' => false,
		);
		$this->options = get_option($this->options_name);
		if(!is_array($this->options)){
			$this->options = $this->defaults;				
		} else {
			$this->options = array_merge($this->defaults, $this->options);
		}
		
		$this->debug = $this->options['debug'];
		
		$this->update_enabled=get_option('update_enabled_plugins');
		if (!is_object($this->update_enabled)){
			$this->update_enabled = new stdClass();
			$this->update_enabled->status = array();
			$this->update_enabled->last_checked = 0;
			update_option('update_enabled_plugins', $this->update_enabled);
		} 
		
		add_action('activate_'.$this->myfile, array(&$this,'activation'));
		add_action('admin_head', array(&$this,'admin_head'));
		add_action('admin_menu', array(&$this,'admin_scripts')); //this seems to be the right hook for that
		add_action('admin_print_scripts', array(&$this,'admin_print_scripts'));
		add_action('admin_footer', array(&$this,'admin_foot'));
		add_action('admin_menu', array(&$this, 'add_admin_menus'));
		
		add_filter('ozh_adminmenu_icon', array(&$this, 'ozh_adminmenu_icon'));
		add_filter('ozh_adminmenu_icon_install_theme', array(&$this, 'ozh_adminmenu_icon'));
		add_filter('ozh_adminmenu_icon_install_plugin', array(&$this, 'ozh_adminmenu_icon'));
		add_filter('ozh_adminmenu_icon_plugin_upgrade_options', array(&$this, 'ozh_adminmenu_icon'));
		
		//This is used for marking plugins with enabled update notifications
		add_action('load-plugins.php', array(&$this,'check_update_notifications'));
			
		if ( ! $this->options['enable_wordpress_checks'])
			remove_action('init', 'wp_version_check');
			
		//Need to use a custom function if the interval is different.
		if (  $this->options['enable_wordpress_checks'] &&
			 ($this->options['wordpress_check_interval'] != 43200)
		){
			remove_action('init', 'wp_version_check');
			add_action('init', array(&$this,'check_wordpress_version'));
		}
		
		if ($this->options['enable_plugin_checks']){
			//See if the custom update checker should be used.
			if ($this->options['global_notices']) {
				//If global notices are enabled, check for updates on every page
				add_action('admin_head', array(&$this,'check_plugin_updates'));
			} elseif( $this->options['anonymize'] || ($this->options['plugin_check_interval'] != 43200) ){
				//Otherwise, check at the usual time
				add_action('load-plugins.php', array(&$this,'check_plugin_updates'));
			}  
			
		}
		
		//Hooks that only work in WP 2.5 and above
		if ( $this->is_wp25() ) {
			add_action('admin_init', array(&$this, 'admin_init'));
			add_action('admin_notices', array(&$this, 'permanent_notices') );
			if ($this->options['global_notices']) {
				add_action( 'admin_notices', array(&$this, 'global_plugin_notices') );
			}
		} else {
			//better than nothing
			add_action('admin_head', array(&$this, 'admin_init'));
		}
		
		/*
		if ($this->options['oneclick_deactivated_notice']){
			add_action( 'admin_notices', array(&$this, 'oneclick_notice') );
		}
		*/
		//*/
		
		//AJAX handling
		add_action('wp_ajax_hide_permanent_notice', array(&$this, 'ajax_hide_permanent_notice') );
	}
	
	/**
	 * dprint ($str, $level)
	 * 
	 * Debugging output function. Also saves everything to a temporary internal log.
	 * $str - whatever you want to output. A <br> and a newline are appended automatically.
	 * $level - priority. 0 - debug, 1 - information, 2 - warning, 3 - error.
	 */
	function dprint($str, $level=0) {
		if ($this->debug) echo $str."<br/>\n" ;
		$this->debug_log[] = array($str, $level);
	}
	
	function activation(){
		//set default options
		if(!is_array($this->options)){
			$this->options = array();				
		};
		$this->options = array_merge($this->defaults, $this->options);
		
		if (empty($this->options['magic_key'])){
			//generate a new "magic" key for installing plugins from the FF extension
			$this->options['magic_key'] = md5(microtime());
		}
		
		update_option($this->options_name, $this->options);
		
		$this->handleOneClick(); //Do something if OneClick is installed
 	}
 	
 	function ajax_hide_permanent_notice(){
 		if (empty($_POST['key'])) return '';
 		$notices = get_option ('permanent_admin_notices');
 		unset($notices[$_POST['key']]);
 		update_option('permanent_admin_notices', $notices);
	}
	
	function add_permanent_notice($text, $key){
 		$notices = get_option ('permanent_admin_notices');
		$notices['key'] = $text;
 		update_option('permanent_admin_notices', $notices);
	}
 	
 	/**
 	 * Special processing if OneClick is installed/active
 	 */
 	function handleOneClick(){
 		if (!function_exists('get_plugins')) return false;
 		$plugins = get_plugins();
		$active  = get_option( 'active_plugins' );
		
		$oneclick = 'oneclick/oneclick.php';
		
		//Check if OneClick is installed
		if (isset($plugins[$oneclick])){
			//Enable the miniguide menu
			$this->options['show_miniguide'] = true;
			
			//Check if OneClick is active
			if (in_array($oneclick, $active)){
				//Deactivate OneClick
				$this->deActivatePlugin($oneclick);
			}
			
			update_option($this->options_name, $this->options);
		}
	}
 	
 	function format_debug_log($min_level=0){
 		$log = '';
 		$classes = array(0=>'ws_debug', 1=>'ws_notice', 2=>'ws_warning', 3=>'ws_error');
		foreach ($this->debug_log as $entry){
			if ($entry[1]<$min_level) continue;
			$log .= "<div class='".$classes[$entry[1]]."'>$entry[0]</div>\n";
		}
		return $log;
	}
	
	function is_wp25(){
		return file_exists( ABSPATH . 'wp-admin/update.php' );
	}
	
	function admin_init(){
		//Hackety-hack! Unfortunately I can't do this earlier (AFAIK).
		//Also, the admin_init hook only exists in WP 2.5
		
		if ($this->options['enable_plugin_checks']){
			if ($this->options['updater_module']=='updater_plugin'){
				remove_action('after_plugin_row', 'wp_plugin_update_row'); //Muahahaha
				if (function_exists('is_ssl')) {
					add_action('after_plugin_row', array(&$this, 'plugin_update_row'), 1, 2);
				} else {
					add_action('after_plugin_row', array(&$this, 'plugin_update_row'));
				}
			}
			
			if( $this->options['anonymize'] || ($this->options['plugin_check_interval'] != 43200) 
			    || $this->options['global_notices'] 
			  )
			{
				//Remove the original hook. My own hook was added in the constructor.
				remove_action('load-plugins.php', 'wp_update_plugins');
			}
			
		} else {
			remove_action('after_plugin_row', 'wp_plugin_update_row');
			remove_action('load-plugins.php', 'wp_update_plugins');
		}
		
		if (!$this->options['enable_wordpress_checks']){
			remove_filter( 'update_footer', 'core_update_footer' );
			remove_action( 'admin_notices', 'update_nag', 3 );
		}
		
	}
	
	function add_admin_menus(){
		add_submenu_page('plugins.php', 'Upgrade Settings', 'Upgrade Settings', 'edit_plugins', 
			'plugin_upgrade_options', array(&$this, 'options_page'));
		if (current_user_can('edit_plugins')) 
			add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2);

		//only privileged users can install plugins
		add_submenu_page('plugins.php', 'Install New', 'Install a Plugin', 'edit_plugins', 
				'install_plugin', array(&$this, 'installer_page'));
		add_submenu_page('themes.php', 'Install New', 'Install a Theme', 'edit_themes', 
				'install_theme', array(&$this, 'installer_page'));
				
		if ($this->options['show_miniguide']){
			add_submenu_page('index.php', 'One Click Plugin Updater Miniguide', 'One Click Updater Miniguide',
				 'edit_plugins', 'one_click_miniguide', array(&$this, 'miniguide_page'));
		}
	}
	
	function ozh_adminmenu_icon($hook){
		$base = WP_PLUGIN_URL.'/'.$this->myfolder.'/images/';
		
		if ($hook == 'install_theme')
			return $base."theme_install.png";
		elseif ($hook == 'install_plugin') 
			return $base."plugin_go.png";
		elseif ($hook == 'plugin_upgrade_options')
			return $base."plugin_upgrade_options.png";
			
		return $hook;
	}
	
	function admin_head(){
		echo "<link rel='stylesheet' href='";
		echo WP_PLUGIN_URL.'/'.$this->myfolder.'/single-click.css';
		echo "' type='text/css' />";
		
		if ($this->options['hide_update_count_blurb']){
			echo "<style type='text/css'>#update-plugins, .plugin-count { display: none ! important; }</style>";
		}
	}
	
  /**
   * ws_oneclick_pup::admin_scripts()
   * Enqueues the required JavaScript libraries
   *
   * @return void
   */
	function admin_scripts(){
   		//The plugin needs JQuery for many of the UI modifications and confirmations
   		wp_enqueue_script('jquery');
	}
	
  /**
   * ws_oneclick_pup::admin_print_scripts()
   * Outputs any JavaScript that needs to go in the head section
   *
   * @return void
   */
	function admin_print_scripts(){
   		//The function for hiding notices
   		?>
<script type="text/javascript">
function hide_permanent_notice(notice_key){
	jQuery.post(
		"<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php", 
		{ action: "hide_permanent_notice", key: notice_key },
		function(data){
		  jQuery('#permanent-notice-'+notice_key).hide();
		}
	);
}
</script>
<?php	 
	}
	
  /**
   * ws_oneclick_pup::plugin_action_links()
   * Handler for the 'plugin_action_links' hook. Adds a "Settings" link to this plugin's entry
   * on the plugin list.
   *
   * @param array $links
   * @param string $file
   * @return array 
   */
	function plugin_action_links($links, $file) {
		if ($file == $this->mybasename)
			$links[] = "<a href='plugins.php?page=plugin_upgrade_options'>" . __('Settings') . "</a>";
		return $links;
	}
	
	function admin_foot(){
		/*
		echo '<pre>';
		//print_r($this->get_update_plugins());
		echo '</pre>';
		//*/
		
		
		
		if (!empty($_GET['page'])) return; //Don't run on plugin subpages
		
		$do_update_url = get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.'/do_update.php';
		
		//Only execute on the plugin list itself.
		if ( (stristr($_SERVER['REQUEST_URI'], 'plugins.php')!==false) ) {
			
			$plugins=get_plugins();
			$update = $this->get_update_plugins();
			$active  = get_option('active_plugins' );
			
			//How many active plugins there are
			$count_active = count($active);
			
			//How many updates are available
			if (is_array($update->response)){
				if ($this->options['hide_notifications_for_inactive']){
					//don't count updates for inactive plugins
					$count_update = 0;
					foreach($update->response as $plugin_file => $data){
						if (in_array($plugin_file, $active)) 
							$count_update++;
					}
					
				} else {
					$count_update = count($update->response);
				}
			} else $count_update = 0;
			
			$plugin_msg = "$count_active active plugins";
			if ($count_update>0){
				$s = ($count_update==1)?'':'s';
				$plugin_msg .= ", <strong>$count_update upgrade$s available</strong>";
			
				if (function_exists('activate_plugin')){
					$link =  $do_update_url.'?action=upgrade_all';
					$link = wp_nonce_url($link, 'upgrade_all');
					$plugin_msg .= " <a href=\'$link\' class=\'button\'>Upgrade All</a>";
				}
			}
		
		?>

<script type="text/javascript">
	$j = jQuery.noConflict();
	
	$j(document).ready(function() {
<?php if ($this->options['mark_plugins_with_notifications']) { ?>
		//Add different CSS dependent on whether a plugin has update notifications enabled.
		var update_enabled_plugins = Array();
<?php 
if (isset($this->update_enabled->status) && (count($this->update_enabled->status)>0)) {
	foreach($this->update_enabled->status as $file => $enabled) {
		echo "\t\t update_enabled_plugins[\"",$plugins[$file]['Name'],"\"] = ",
			($enabled?'true':'false'),";\n";
	}
}
if (function_exists('is_ssl')){
	//WP 2.6
	echo "\t\tplugin_tr_expr = '.plugins tr'";
} else {
	//WP 2.3 - 2.5.1
	echo "\t\tplugin_tr_expr = '#plugins tr'";
} ?>		
		$j(plugin_tr_expr).each(function (x) {
			name_cell = $j(this).find('.name, .plugin-title');
			if (name_cell){
				if (update_enabled_plugins[name_cell.text()]) {
					$j(this).addClass('update-notification-enabled').find('th').addClass('update-notification-enabled');
					$j(this).next('.second').addClass('update-notification-enabled').find('td:first').addClass('update-notification-enabled');
				} else {
					$j(this).addClass('update-notification-disabled').find('th').addClass('update-notification-disabled');
					$j(this).next('.second').addClass('update-notification-disabled').find('td:first').addClass('update-notification-disabled');
				};
			}
		});
	<?php } ?>
		
		//Add a status msg.
		<?php echo "var plugin_msg = '$plugin_msg';"; ?> 
		$j("div.wrap h2:first").after("<p class='plugins-overview'>"+plugin_msg+"</p>");
		
<?php
		if (current_user_can('edit_plugins')){
			//A partially verifiable link to delete a plugin
			$delete_link = $do_update_url.'?action=delete_plugin';
			$delete_link = wp_nonce_url($delete_link, 'delete_plugin');
			$delete_link = html_entity_decode($delete_link); //No, WP, I don't want your damn &#038;'s!
?>
		//Add the "Delete" links to inactive plugins
		$j("tr:not(.active) td.action-links").each(function (x) {
			//construct the specific URL
			url = '<?php echo $delete_link; ?>';
			edit_url = $j(this).find("a:last").attr('href');
			re = /\?file=(.+?)($|&)/i
			matches = re.exec(edit_url);
			if (!matches) return true;
			url = url + '&plugin_file=' + matches[1];
			//add the "Delete" link			
			$j(this).prepend('<a href="javascript:verifyPluginDelete(\''+ url +'\')">Delete</a> | ');
		});
		
<?php 	} 	?>
		
	});	
	
	function verifyPluginDelete(url){
		if (confirm("Do you really want to delete this plugin?\r\nDo this at your own risk!")){
			document.location = url;
		}
		return void(0);
	}
</script>
		<?php
		
		} else if ( (stristr($_SERVER['REQUEST_URI'], 'themes.php')!==false) && current_user_can('edit_themes')) {
			//Only execute on the "Themes" page
			
			//A partially verifiable link to delete a plugin
			$delete_link = $do_update_url.'?action=delete_theme';
			$delete_link = wp_nonce_url($delete_link, 'delete_theme');
			$delete_link = html_entity_decode($delete_link); //No, WP, I don't want your damn &#038;'s!
?>
<style type='text/css'>
.theme-delete {
	float:right;
}

.available-theme {
	position: relative;
}
</style>
<script type="text/javascript">
	$j = jQuery.noConflict();
	
	$j(document).ready(function() {
		//Add the "Delete" links to all themes except the current one
		$j(".available-theme").each(function (x) {
			//construct the specific URL
			h3 = $j(this).find("h3");
			theme_title = h3.text();
			select_url = $j(h3).find("a").attr('href');
			
			re = /[&\?]template=(.+?)($|&)/i
			matches = re.exec(select_url);
			if (!matches) return true;
			theme_folder = matches[1];
			
			//add the "Delete" link			
			$j(this).prepend(' <a href="javascript:verifyThemeDelete(\''+ 
				theme_folder +'\',\''+ escape(theme_title) +'\')" class="theme-delete">Delete</a> ');
			//(theme_title is escape()'d because it might contain characters that are special in JS)
		});
		
	});	
	
	function verifyThemeDelete(theme, title){
		title = unescape(title);
		//construct the specific URL
		url = '<?php echo $delete_link; ?>' + '&theme='+theme; 
		//theme was already escaped, as it was retrieved from an URL on the "Themes" tab
		if (confirm("Do you really want to delete the theme '"+title+"'?\r\nDo this at your own risk!")){
			document.location = url;
		}
		return void(0);
	}
</script>
		<?php
			
		} 
	}
	
	function plugin_update_row( $file, $new_plugin_data=null ) {
		global $plugin_data;
		
		//the second parameter is only set in 2.6 and up
		if ($new_plugin_data) {
			$plugin_data = $new_plugin_data;
		} else {
			$plugins = get_plugins();
			$plugin_data = $plugins[$file];
		}
		
		$current = $this->get_update_plugins();
		if ( !isset( $current->response[ $file ] ) ){
			return false;
		}
		
		//Don't show the notification for inactive plugins (user-configured)
		$active = get_option( 'active_plugins' );
		if ($this->options['hide_notifications_for_inactive'] && !in_array($file, $active)){
			return false;
		}
	
		$r = $current->response[ $file ];
		
		//Workaround. Don't show the update notification if the new version is the same as the old one.
		if ( function_exists('version_compare') ){
			if ( version_compare($plugin_data['Version'], $r->new_version,'=') ){
				return false;
			}
		} else {
			if ( $plugin_data['Version'] == $r->new_version ){
				return false;
			}
		}
		
		$autoupdate_url=get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
		 '/do_update.php?action=update_plugin&plugin_file='.urlencode($file);
		if(!empty($r->package)){
			//Download URL is already known, so use that
			$autoupdate_url .= '&download_url='.urlencode($r->package);
		} else {
			//OBSOLETE. do_update.php will use the plugin URL to autodetect the download URL.
			$autoupdate_url .='&plugin_url='.urlencode($r->url);
		}
		
		//Add nonce verification for security
		$autoupdate_url = wp_nonce_url($autoupdate_url, 'update_plugin-'.$file);
	
		echo "<tr class='plugin-update-tr'><td colspan='5' class='plugin-update'><div class='update-message'>";
		if ( !current_user_can('edit_plugins') ) {
			printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a>.'), 
				$plugin_data['Name'], $r->url, $r->new_version);
		} else if ( empty($r->package) ) {
			printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a> <em>automatic upgrade unavailable for this plugin</em>.'), 
				$plugin_data['Name'], $r->url, $r->new_version);
		} else {
			printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a> or <a href="%4$s">upgrade automatically</a>.'), 
				$plugin_data['Name'], $r->url, $r->new_version, $autoupdate_url );
		}
		echo "</div></td></tr>";
	}
	
	/**
	 * Decrease a version number by a small fraction
	 */
	function version_decrease($version){
		//Spaces? Kill anything that comes after a space.
		$parts = explode(' ', trim($version));
		$ver = $parts[0];
		$numeric = array();
		//Separate by dots
		$parts = explode('.', $ver);
		//Perform the arithmetics
		$new_parts = array();
		$carry = 1;
		while(count($parts)>0){
			$minor = intval(array_pop($parts));
			$minor = $minor - $carry;
			if ($minor < 0){
				$minor = 10 - $carry;
				//$minor =  
			} else {
				$carry = 0;
			}
			array_unshift($new_parts, $minor);
		}
		
		//Add the dots again
		$new_ver = implode('.', $new_parts);
		return $new_ver;	
	}
	
	function get_update_plugins(){
		$plugins = get_plugins();
		
		if ( function_exists('get_transient') ){
			$current = get_transient( 'update_plugins' );
		} else {
			$current = get_option( 'update_plugins' );
		}
		$rez = $current;
		
		//Remove missing plugins
		if ( !isset($current->response) && is_array($current->response) ){
			foreach ( $current->response as $plugin_file => $update_data ) {
				if ( empty( $plugins[$plugin_file] ) ){
					unset( $rez->response[$plugin_file]  );
				}
			}
		}
		
		return $rez;
	}
	
	function set_update_plugins( $data ){
		if ( function_exists('set_transient') ){
			set_transient( 'update_plugins', $data );
		}
		//Just to be sure, set the option as well.
		return update_option( 'update_plugins', $data );
	}
	
	function check_update_notifications(){
		global $wp_version;
		@set_time_limit(300);

		if ( !function_exists('fsockopen') )
			return false;
			
		//Hack : make the plugin compatible with an unknown comment-related plugin
		if ( !function_exists('get_plugins') )
			return false;
		
		$plugins = get_plugins();
		$orig_plugins = $plugins;
		$active  = get_option( 'active_plugins' );
		$current = $this->get_update_plugins();
		
		$plugin_changed = empty($current);
		
		foreach ( $plugins as $file => $p ) {
			$plugins[$file]['Version']='0.0'; //fake zero version
			
			//Use update info provided by WP, if available
			if ( isset( $current->status->response[$file] ) ){
				$this->update_enabled->status[$file] = true;
			//New plugin?
			} else if( !isset($this->update_enabled->status[$file]) ) {
				$this->update_enabled->status[$file] = false; //Yep, assume no notifications
				$plugin_changed = true;
			}
		}
		//Remove information about deleted plugins
		$remaining = $this->update_enabled->status;
		foreach($remaining as $file => $status){
			if (!isset($plugins[$file])){
				unset($this->update_enabled->status[$file]);
				/* Maybe comment this out not to waste time checking for updates every time a plugin is deleted. */
				$plugin_changed = true; 
			}
		}
		
		update_option( 'update_enabled_plugins', $this->update_enabled);
		
		//$plugin_changed=true; //debug - force status update
		if (
			isset( $this->update_enabled->last_checked ) &&
			( ( time() - $this->update_enabled->last_checked ) < $this->options['plugin_check_interval']) &&
			!$plugin_changed
		)
			return false;
			
		$this->update_enabled->last_checked=time();
	
		$to_send->plugins = $plugins;
		$to_send->active = array();
		
		$send = serialize( $to_send );
		
		$request = 'plugins=' . urlencode( $send );
		$http_request  = "POST /plugins/update-check/1.0/ HTTP/1.0\r\n";
		$http_request .= "Host: api.wordpress.org\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		$http_request .= 'User-Agent: WordPress/2.8; http://example.com/' . "\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;
	
		$response = '';
		if( false != ( $fs = @fsockopen( 'api.wordpress.org', 80, $errno, $errstr, 3) ) && is_resource($fs) ) {
			fwrite($fs, $http_request);
	
			while ( !feof($fs) )
				$response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			
			$response = explode("\r\n\r\n", $response, 2);
		}
	
		$response = unserialize( $response[1] );

	
		if ( $response ) {
			foreach($response as $file => $data) {
				$this->update_enabled->status[$file]=true;
			}
		}
		
		update_option( 'update_enabled_plugins', $this->update_enabled);
		
		return true;
	}
	
	/**
	 * check_plugin_updates - check if there are new version of installed plugins.
	 *
	 * This is almost exactly like wp_update_plugins, but can give out less information about
	 * the blog.
	 */
	function check_plugin_updates() {
		global $wp_version;
		
		if ( !function_exists('fsockopen') )
			return false;
		
		//Hack : make compatible with an unknown comment-related plugin.
		if ( !function_exists('get_plugins') )
			return false;
	
		$plugins = get_plugins();
		$active  = get_option( 'active_plugins' );
		$current = $this->get_update_plugins();
	
		$new_option = new stdClass;
		$new_option->last_checked = time();
		$new_option->checked = array();
	
		$plugin_changed = false;
		//Check for plugins that have a different ver. number than the last time.
		foreach ( $plugins as $file => $p ) {
			$new_option->checked[ $file ] = $p['Version'];
			
			if ( !isset( $current->checked[ $file ] ) || strval($current->checked[ $file ]) !== strval($p['Version']) )
				$plugin_changed = true;
		}
		
		//Check for deleted plugins
		if ( isset ( $current->response ) && is_array( $current->response ) ) {
			foreach ( $current->response as $plugin_file => $update_details ) {
				if ( ! isset($plugins[ $plugin_file ]) ) {
					$plugin_changed = true;
					break;
				}
			}
		}
		
		//$plugin_changed = true; //debug
		
		if (
			isset( $current->last_checked ) &&
			$this->options['plugin_check_interval'] > ( time() - $current->last_checked ) &&
			!$plugin_changed
		)
			return false;
			
		$to_send->plugins = $plugins;
		$to_send->active = $active;
		$send = serialize( $to_send );
		$request = 'plugins=' . urlencode( $send );
		
		$http_request  = "POST /plugins/update-check/1.0/ HTTP/1.0\r\n";
		$http_request .= "Host: api.wordpress.org\r\n";
		
		if ($this->options['anonymize']) {
			$http_request .= 'User-Agent: WordPress/2.8; http://example.com/' . "\r\n";
		} else {
			$http_request .= 'User-Agent: WordPress/' . $wp_version . '; ' . get_bloginfo('url') . "\r\n";
		}
		
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		
		$http_request .= "\r\n";
		$http_request .= $request;
	
		$response = '';
		if( false != ( $fs = @fsockopen( 'api.wordpress.org', 80, $errno, $errstr, 3) ) && is_resource($fs) ) {
			fwrite($fs, $http_request);
	
			while ( !feof($fs) )
				$response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);
		}
	
		$response = unserialize( $response[1] );
		
		if ( $response ) {
			$new_option->response = $response;
			//Plugins that have updates, also have notifications enabled (obviously)
			if (is_array($response)){
				foreach($response as $file => $data) {
					$this->update_enabled->status[$file]=true;
				}
				update_option( 'update_enabled_plugins', $this->update_enabled);
			}
		} else {
			$new_option->response = array();
		}
	
		$this->set_update_plugins ( $new_option );
	}
	
	/**
	 * Just like wp_version_check, but with a configurable interval.
	 */
	function check_wordpress_version(){
		if ( !function_exists('fsockopen') || strpos($_SERVER['PHP_SELF'], 'install.php') !== false || defined('WP_INSTALLING') )
			return;

		global $wp_version;
		$php_version = phpversion();
	
		$current = get_option( 'update_core' );
		$locale = get_locale();
	
		if (
			isset( $current->last_checked ) &&
			$this->options['wordpress_check_interval'] > ( time() - $current->last_checked ) &&
			$current->version_checked == $wp_version
		)
			return false;
	
		$new_option = '';
		$new_option->last_checked = time(); // this gets set whether we get a response or not, so if something is down or misconfigured it won't delay the page load for more than 3 seconds, twice a day
		$new_option->version_checked = $wp_version;
	
		$http_request  = "GET /core/version-check/1.1/?version=$wp_version&php=$php_version&locale=$locale HTTP/1.0\r\n";
		$http_request .= "Host: api.wordpress.org\r\n";
		$http_request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
		$http_request .= 'User-Agent: WordPress/' . $wp_version . '; ' . get_bloginfo('url') . "\r\n";
		$http_request .= "\r\n";
	
		$response = '';
		if ( false !== ( $fs = @fsockopen( 'api.wordpress.org', 80, $errno, $errstr, 3 ) ) && is_resource($fs) ) {
			fwrite( $fs, $http_request );
			while ( !feof( $fs ) )
				$response .= fgets( $fs, 1160 ); // One TCP-IP packet
			fclose( $fs );
	
			$response = explode("\r\n\r\n", $response, 2);
			if ( !preg_match( '|HTTP/.*? 200|', $response[0] ) )
				return false;
	
			$body = trim( $response[1] );
			$body = str_replace(array("\r\n", "\r"), "\n", $body);
	
			$returns = explode("\n", $body);
	
			$new_option->response = attribute_escape( $returns[0] );
			if ( isset( $returns[1] ) )
				$new_option->url = clean_url( $returns[1] );
			if ( isset( $returns[2] ) )
				$new_option->current = attribute_escape( $returns[2] );
		}
		update_option( 'update_core', $new_option );
	}
	
  /**
   * ws_oneclick_pup::download_page()
   * Download and return the page/file from the provided URL. Tries three different techniques - cURL, 
   * Snoopy and fopen (in that order). 
   *
   * @param string $url
   * @param integer $timeout
   * @return string or FALSE if the download fails.
   */
	function download_page($url, $timeout=120){
		$this->dprint("Downloading '$url'...", 1);
		
		$parts=parse_url($url);
		if(!$parts) return false;
		
		if(!isset($parts['scheme'])) $url='http://'.$url;
		
		$response = false;
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		
			/* Currently redirection support is not absolutely necessary, so it's OK
			if this line fails due to safemode restrictions */
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			
			$response = curl_exec($ch);
			curl_close($ch);
		} else if (file_exists(ABSPATH . 'wp-includes/class-snoopy.php')){
			require_once( ABSPATH . 'wp-includes/class-snoopy.php' );
			$snoopy = new Snoopy();
			$snoopy->fetch($url);

			if( $snoopy->status == '200' ){
				$response = $snoopy->results;
			}
		} else if (ini_get('allow_url_fopen') && (($rh = fopen($url, 'rb')) !== FALSE)) { 
			$response='';
			while (!feof($rh)) {
			    $response.=fread($rh, 1024);
			}
			fclose($rh);
		} else {
			return false;
		}
		
		return $response;	
	}
	
	function deActivatePlugin($plugin) {
		if(!current_user_can('edit_plugins')) {
			//echo 'Oops, sorry, you are not authorized to deactivate plugins!';
			return false;
		}
		$current = get_option('active_plugins');
		array_splice($current, array_search($plugin, $current), 1 ); // Array-fu!
		do_action('deactivate_' . trim( $plugin ));
		update_option('active_plugins', $current);
		return true;
	}
	
	/**
	 * recursive_mkdir - recursively create a directory
	 *
	 * Note: The function expects $path to be an absolute path.
	 */
	function recursive_mkdir($path, $mode = 0755) {
		//If the directory is inside the WP path we don't need to validate the whole tree.
		//This also lets circumvent an open_basedir restriction problem with is_dir().
		$okABSPATH = preg_replace('/[\\/]/', DIRECTORY_SEPARATOR, ABSPATH);
		$path = preg_replace('/[\\/]/', DIRECTORY_SEPARATOR, $path);
		if (strpos($path, $okABSPATH) === 0){
			$tmppath = $okABSPATH;
			$path = substr($path, strlen($tmppath));
			//Remove the trailing slash from $tmppath (if present)
			if (substr($tmppath, -1) == DIRECTORY_SEPARATOR) {
				$tmppath = substr($tmppath, 0, strlen($tmppath)-1);
			}
		} else {
			$tmppath = '';
		}
		
		$dirs = explode(DIRECTORY_SEPARATOR, $path);
	    foreach ($dirs as $directory){
	    	if(empty($directory)) continue;
	    	
	    	$tmppath = $tmppath . DIRECTORY_SEPARATOR . $directory; 
	    	if ( ! is_dir($tmppath) ){
				$this->dprint("Creating directory '$tmppath'", 1);
				if ( !mkdir($tmppath, $mode) ){
					$this->dprint("Can't create directory '$tmppath'!", 3);
					return new WP_Error('fs_mkdir', "Can't create directory '$tmppath'.");
				}
			}
	    }
	    return true;
	}
	
    /**
     * extractFile() - extract the plugin or theme to the right folder
     * 
     * zipfile - filename of the ZIP archive to extract.
     * type - what does the archive contain? 'plugin', 'theme', 'autodetect' or 'none'.
     * target - the destination directory to unzip to. Optional if $type is given.
     * use_pclzip - whether to use PclZip (default : true).
	 */
    function extractFile($zipfile, $type='autodetect', $target = '', $use_pclzip=true){
    	$this->dprint("Extracting files from $zipfile...");

    	$magic_descriptor = array('type' => $type);
    	
    	//Do some early autodetection
    	if(empty($target)){
			if ($type == 'plugin'){
				$target = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR;
			} else if ($type == 'theme'){
				if (function_exists('get_theme_root')){
					$target = get_theme_root() . DIRECTORY_SEPARATOR;
				} else {
					$target = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR;
				} 
			}
		}

		$this->dprint("So far, the type is set to '$type'.");
		
		if (!$use_pclzip){
			/* last-ditch attempt if PclZip didn't work - use the linux "unzip" executable */
			if (empty($target)){
				$this->dprint("Target directory not specified and can't autodetect in this mode!", 3);
				return new WP_Error('ad_unsupported', "Can't use autodetection without PclZip support.");
			}
	        if (function_exists('exec')) {
		        $this->dprint("Running the unzip command.", 1);
				exec("unzip -uovd $target $zipfile", $ignored, $return_val);
				$rez = $return_val == 0;
				
				//Show the unzip output for debugging purposes 
				$this->dprint("unzip returned value '$return_val'. unzip log : ");
				$unzip_log = explode("\n",$ignored);
				if (is_array($unzip_log)){
					foreach ($unzip_log as $log_line){
						$this->dprint("\t$log_line");
					}
				}
								
				if (!$rez){
					return new WP_Error('zip_unzip_error', "exec('unzip') failed miserably.");
				} else {
					return $magic_descriptor;
				}
		    }
		    return new WP_Error('zip_noexec', "Can't run <em>unzip</em>.");
		}
    	
    	if (!class_exists('PclZip'))
		{
			$this->dprint('Need to load PclZip.');
	    	require_once ('pclzip.lib.php');
		}
	    $archive = new PclZip($zipfile);

	    if (function_exists('gzopen')) {

		    $this->dprint("gzopen() found, will use PclZip.");
		    
			// Try to extract all of the file information in-memory. Note : hopefully unlikely to 
			// overrun memory limits.
			if ( false == ($archive_files = $archive->listContent()) ){
				// Nope.
				$this->dprint("PclZip failed!", 3);
				$this->dprint("PclZip says : '".$archive->errorInfo(true)."'", 3);
				$error_msg = "PclZip Error : '".$archive->errorInfo(true)."'";
				return new WP_Error('zip_unsupported', $error_msg);
			} else {
				//It worked! Woo-hoo!
				$magic_descriptor['file_list'] = $archive_files;
				//Let's see, where do we put the files?
				if(empty($target)){
					//Need to autodetect! Look at some PHP & CSS files for headers.
					$this->dprint("Starting autodetection.", 1);
					foreach($archive_files as $file_info){
						$file_ext = strtolower(substr($file_info['filename'],-4));
						if ( $file_info['folder'] || 
							 ( substr_count($file_info['filename'],'/') > 1 ) ||
							 ( ($file_ext != '.php') && ($file_ext != '.css') ) 
						) continue;
						$file = $archive->extract(PCLZIP_OPT_BY_NAME, $file_info['filename'], 
							PCLZIP_OPT_EXTRACT_AS_STRING);
						
						$file = $file[0];
						$this->dprint("\tChecking $file[filename]");
						$plugin = $this->get_plugin_info($file['content']);
						if (!empty($plugin['plugin name'])) {
							$this->dprint("\tFound a plugin header! The plugin is : ".$plugin['plugin name'], 1);
							$type = 'plugin';
							$magic_descriptor['file_header'] = $plugin;
							$magic_descriptor['type'] = $type;
							$magic_descriptor['plugin_file'] = $file_info['filename'];
							$target = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR;
							break;
						}
						
						$theme = $this->get_theme_info($file['content']);
						if (!empty($theme['theme name'])) {
							$this->dprint("\tFound a theme header! It is '".$theme['theme name']."'", 1);
							$type = 'theme';
							$magic_descriptor['file_header'] = $theme;
							$magic_descriptor['type'] = $type;
							$target = get_theme_root() . DIRECTORY_SEPARATOR;
							break;
						}
						
					}
					
					if (empty($target)){
						$this->dprint("Autodetection failed!", 3);
						return new WP_Error("ad_failed", "Autodetection failed - this doesn't look like a plugin or a theme.");
					}
				}
				//Finally, extract the files! Some of the code shamelessly stolen from WP core (file.php).
				if (substr($target,-1) != DIRECTORY_SEPARATOR) $target .= DIRECTORY_SEPARATOR;
				$to = preg_replace('/[\\/]/', DIRECTORY_SEPARATOR, $target);
				$this->dprint("Starting extraction to folder '$to'.", 1);
				
				//Make sure the target directory exists
				$rez = $this->recursive_mkdir($to, $this->options['new_file_permissions']);
				if (is_wp_error($rez)){
					return $rez;
				}

				foreach ($archive_files as $file) {
					$path = dirname($file['filename']);
					//Create the file's directory if neccessary
					$rez = $this->recursive_mkdir($to . $path, $this->options['new_file_permissions']);
					if (is_wp_error($rez)){
						return $rez;
					}
					
					// We've made sure the folders are there, so let's extract the file now:
					if ( ! $file['folder'] ){
						$this->dprint("Extracting $file[filename]", 1);
						$file_data = $archive->extract(PCLZIP_OPT_BY_NAME, $file['filename'], 
							PCLZIP_OPT_EXTRACT_AS_STRING);
						
						$file_data=$file_data[0];
							
						//get additional info if we didn't earlier	
						if (empty($magic_descriptor['file_header'])){
							if ('plugin' == $type){
								if (strtolower(substr($file['filename'],-4))=='.php'){
									$plugin = $this->get_plugin_info($file_data['content']);
									if (!empty($plugin['plugin name'])) {
										$this->dprint("\tFound a plugin header! The plugin is : ".$plugin['plugin name'], 1);
										$magic_descriptor['file_header'] = $plugin;
										$magic_descriptor['plugin_file'] = $file['filename'];
									}
								}
							} else if ('theme' == $type){
								if (strtolower(substr($file['filename'],-4))=='.css'){
									$theme = $this->get_theme_info($file_data['content']);
									if (!empty($theme['theme name'])) {
										$this->dprint("\tFound a theme header! ".$theme['plugin name'], 1);
										$magic_descriptor['file_header'] = $theme;
									}
								}
							}
						}
						
						//Put the file where it belongs
						if ( isset($file_data['content']) && (strlen($file_data['content'])>0) ) {
							//$this->dprint("File $file[filename] = ".strlen($file_data['content']).' bytes', 0);
							if ( !file_put_contents( $to . $file['filename'], $file_data['content']) ){
								$this->dprint("Can't create file $file[filename] in $to!", 3);
								return new WP_Error('fs_put_contents', "Can't create file '$file[filename]' in '$to'");
							}
						} else {
							//special handling for zero-byte files (file_put_contents wouldn't work)
							$fh = @fopen($to . $file['filename'], 'wb');
							if(!$fh){
								$this->dprint("Can't create a zero-byte file $file[filename] in $to!", 3);
								return new WP_Error('fs_put_contents', 
									"Can't create a zero-byte file '$file[filename]' in '$to'");
							}
							fclose($fh);
						}
						@chmod($to . $file['filename'], $this->options['new_file_permissions']); 
						//^ I think this can be allowed to fail.
					}
				}
				//Extraction succeeded! Yay.
				$this->dprint("Extraction succeeded.", 1);
				return $magic_descriptor;
			}
        } else {
			$this->dprint("gzopen() not available, can't use PclZip.", 2);
			return new WP_Error('zip_pclzip_unusable', "PclZip not supported - no gzopen().");
		}
		$this->dprint("extractFile() : you should never see this message.", 3);        
        return new WP_Error('impossible', "An impossible error!");
	}
	
	function get_plugin_info( $file_contents ) {
		$plugin_data = $file_contents;
		//Lets do it the simple way!
		
		$names = array('plugin name'=>'', 'plugin uri'=>'', 'description'=>'', 'author uri'=> '', 
			'author' => 'Unknown', 'version' => '');
		
		if ( preg_match_all('/('.implode('|', array_keys($names)).'):(.*)$/mi', 
			 	$plugin_data, $matches, PREG_SET_ORDER)	)
		{
			foreach($matches as $match){
				$key = strtolower($match[1]);
				if (empty($names[$key]) && ($key != 'author')){
					$names[strtolower($match[1])] = trim($match[2]);
				}
			}
		}
		
		$names['name'] = $names['plugin name'];
		return $names;
	}
	
	function get_theme_info( $file_contents ) {
		//Lets do this the simple way, too!
		$theme_data = $file_contents;
		
		$names = array('theme name'=>'', 'theme uri'=>'', 'description'=>'', 'author uri'=> '', 
			'template' => '', 'version' => '', 'status' => 'publish', 'tags' => '', 'author' => 'Anonymous');
		
		if ( preg_match_all('/('.implode('|', array_keys($names)).'):(.*)$/mi', 
			 	$theme_data, $matches, PREG_SET_ORDER)	)
		{
			foreach($matches as $match){
				$names[strtolower($match[1])] = trim($match[2]);
			}
		}
		$names['name'] = $names['theme name'];
		return $names;
	}
    
    function is__writable($path)
	{
	    //will work in despite of Windows ACLs bug
	    //NOTE: use a trailing slash for folders!!!
	    //see http://bugs.php.net/bug.php?id=27609
	    //see http://bugs.php.net/bug.php?id=30931
	
	    if ($path{strlen($path) - 1} == '/') // recursively return a temporary file path
	
	        return $this->is__writable($path . uniqid(mt_rand()) . '.tmp');
	    else
	        if (is_dir($path))
	            return $this->is__writable($path . '/' . uniqid(mt_rand()) . '.tmp');
	    // check tmp file for read/write capabilities
	    $rm = file_exists($path);
	    $f = @fopen($path, 'a');
	    if ($f === false)
	        return false;
	    fclose($f);
	    if (!$rm)
	        unlink($path);
	    return true;
	}
	
	function options_page(){
		if (!empty($_POST['action']) && ($_POST['action']=='update') ){
			$this->options['updater_module'] = $_POST['updater_module'];
			$this->options['enable_plugin_checks'] = !empty($_POST['enable_plugin_checks']);
			$this->options['enable_wordpress_checks'] = !empty($_POST['enable_wordpress_checks']);
			$this->options['anonymize'] = !empty($_POST['anonymize']);
			$this->options['mark_plugins_with_notifications'] = !empty($_POST['mark_plugins_with_notifications']);
			$this->options['plugin_check_interval'] = intval($_POST['plugin_check_interval']);
			$this->options['wordpress_check_interval'] = intval($_POST['wordpress_check_interval']);
			$this->options['debug'] = !empty($_POST['debug']);
			$this->options['confirm_remote_installs'] = !empty($_POST['confirm_remote_installs']);
			$this->options['show_miniguide'] = !empty($_POST['show_miniguide']);
			$this->debug = $this->options['debug'];
			$this->options['global_notices'] = !empty($_POST['global_notices']);
			$this->options['hide_notifications_for_inactive'] = !empty($_POST['hide_notifications_for_inactive']);
			$this->options['hide_update_count_blurb'] = !empty($_POST['hide_update_count_blurb']);
			if (!empty($_POST['new_file_permissions']))
				$this->options['new_file_permissions'] = octdec(intval($_POST['new_file_permissions']));
			
			update_option($this->options_name, $this->options);

			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		} 
		
		?>
<div class="wrap">
<h2>Upgrade Settings</h2>
<p>Here you can configure plugin update notifications, set how often WordPress checks for new versions, 
and so on. This page was created by the 
<a href='http://w-shadow.com/blog/2008/04/06/one-click-updater-plugin-20/'>One Click Plugin Updater</a> 
plugin.</p>

<form name="plugin_upgrade_options" method="post" 
action="<?php echo $_SERVER['PHP_SELF']; ?>?page=plugin_upgrade_options">

<input type='hidden' name='action' value='update' />
<h3>Plugin Updater Module</h3>
<table class="form-table">
	<tr>
		<th align='left'><label><input name="updater_module" type="radio" value="updater_plugin" class="tog" 
		<?php
			if ($this->options['updater_module']=='updater_plugin') echo "checked='checked'";
		?> /> Updater plugin</label></th>
		<td>
		Always uses direct file access, so <code>wp-content/plugins</code> must be writable by WordPress. 
		In WP 2.5, it also provides an "Upgrade All" option to update all plugins with a single click.
		<br />
		<?php
		//Self-test. Let the user know if the plugin is functional
		$ok = true;
		if (!$this->is__writable(ABSPATH . PLUGINDIR . '/')) {
			$error .= " Plugin folder is not writable. ";
			$ok = false;
		}
		if (!function_exists('gzopen')){
			$ok = false;
			$error .= " PclZip not supported. ";
		}
		if (!$this->is__writable(ABSPATH . PLUGINDIR . '/')) {
			$error .= " Theme folder is not writable. ";
		}
		if (!function_exists('curl_init')){
			if (file_exists(ABSPATH . 'wp-includes/class-snoopy.php')){
				$error .= " Using Snoopy. ";
			} else if (ini_get('allow_url_fopen')){
				$error .= " Using fopen(). ";
			} else {
				$ok = false;
				$error .= " No way to download files. ";
			}
		}
		echo "Status : ";
		if ($ok){
			echo "OK";
			if (!empty($error)){
				echo " ($error)";
			}
		} else {
			echo "Error - ".$error;
		}
		?>
		
		</td>
	</tr>
	<tr>
		<th align='left'><label><input name="updater_module" type="radio" value="wp_core" class="tog" <?php
			if ($this->options['updater_module']=='wp_core') echo "checked='checked'";
		?> /> WordPress core</label></th>
		<td>Requires at least WP 2.5. Can use FTP if the plugin directory isn't writable.</td>
	</tr>
</table>

<h3>Plugin Updates</h3>
<table class="form-table">
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='enable_plugin_checks' id='enable_plugin_checks' <?php
			if ($this->options['enable_plugin_checks']) echo "checked='checked'";
		?> />
		Enable plugin update checks</label></th>
	</tr>
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='anonymize' id='anonymize' <?php
			if ($this->options['anonymize']) echo "checked='checked'";
		?> />
		Don't send the real WP version and URL when checking</label></th>
	</tr>
	<tr>
		<th align='left'>Check interval</th>
		<td><input type='text' name='plugin_check_interval' size="10" value="<?php
			echo $this->options['plugin_check_interval'];
		?>"  /> seconds</td>
	</tr>
<?php if (function_exists('activate_plugin')) { ?>
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='global_notices' id='global_notices' <?php
			if ($this->options['global_notices']) echo "checked='checked'";
		?> />
		Show global update notices</label></th>
	</tr>
<?php } ?>	
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='mark_plugins_with_notifications' id='mark_plugins_with_notifications' <?php
			if ($this->options['mark_plugins_with_notifications']) echo "checked='checked'";
		?> />
		Highlight plugins that have update notifications enabled.</label></th>
	</tr>
	
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='hide_notifications_for_inactive' id='hide_notifications_for_inactive' <?php
			if ($this->options['hide_notifications_for_inactive']) echo "checked='checked'";
		?> />
		Hide update notifications for inactive plugins.</label></th>
	</tr>
	
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='hide_update_count_blurb' id='hide_update_count_blurb' <?php
			if ($this->options['hide_update_count_blurb']) echo "checked='checked'";
		?> />
		Hide the little update count blurb near "Plugins" menu.</label></th>
	</tr>
</table>

<h3>WordPress Updates</h3>
<table class="form-table">
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='enable_wordpress_checks' id='enable_wordpress_checks' <?php
			if ($this->options['enable_wordpress_checks']) echo "checked='checked'";
		?> />
		Enable WordPres update checks</label></th>
	</tr>
	<tr>
		<th align='left'>Check interval</th>
		<td><input type='text' name='wordpress_check_interval' size="10" value="<?php
			echo $this->options['wordpress_check_interval'];
		?>"  /> seconds</td>
	</tr>
</table>

<h3>FireFox Extension</h3>
<table class="form-table">
	<tr>
		<td colspan='2' style='font-size: 1em;'>
		URL for the <a href='<?php echo $this->ff_extension_url; ?>'>
			One-Click Installer for WP</a> extension: <br />
		<?php
		echo get_option('siteurl').'/wp-admin/plugins.php?page=install_plugin&magic='
			 	.$this->options['magic_key'];
		?>
		</td>
	</tr>
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='confirm_remote_installs' id='confirm_remote_installs' <?php
			if ($this->options['confirm_remote_installs']) echo "checked='checked'";
		?> />
		Ask for confirmation when installing from FireFox </label>
		</th>
	</tr>
	
</table>

<h3>Other</h3>
<table class="form-table">
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='show_miniguide' id='show_miniguide' <?php
			if ($this->options['show_miniguide']) echo "checked='checked'";
		?> />
		Show the Miniguide submenu on Dashboard</label></th>
	</tr>

	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='debug' id='debug' <?php
			if ($this->debug) echo "checked='checked'";
		?> />
		Enable debug mode </label></th>
	</tr>
	
	<tr>
		<th align='left'>File Mode</th>
		<td>
			<input type='text' name='new_file_permissions' id='new_file_permissions' value='<?php
			echo decoct($this->options['new_file_permissions']);
			?>' maxlength='5' size='10' /><br />
			The file permissions that should be assigned to files created by this plugin. 
			Leave this option alone if you don't know what it means. The plugin will <em>try</em>
			to set these permissions but it may not always succeed.
		</td>
	</tr>
	
</table>

<p class="submit"><input type="submit" name="Submit" value="Save Changes" />
</p>
</form>

</div>
<?php
	}
	
	function do_install($url='', $filename='', $type='autodetect'){
		@set_time_limit(0);
		@ignore_user_abort(true);
		/**
		 * Download the file (if neccessary).
		 */
		if (empty($filename) && !empty($url)){
			//URL is okay, lets try downloading
			$contents = $this->download_page($url, 300);
			if ($contents){
				$this->dprint("Downloaded ".strlen($contents)." bytes.", 1);
				
				//First try : save in the plugin's own directory (and don't use tempnam() - buggy sometimes).
				$filename=dirname(__FILE__)."/plg".md5(microtime().'|'.rand(0,1000000)).".zip";
				$this->dprint("Will save the new version archive (zip) to a temporary file '$filename'.");
				$handle = @fopen($filename, "wb");
				
				if(!$handle) {
					$this->dprint("Warning: couldn't create a temporary file at '$filename'.", 2);

					//Second try : use the default (hopefully) system directory
					$filename = tempnam("/tmp", "PLG");
					$this->dprint("Using alternate temporary file '$filename'.", 1);
					$handle = fopen($filename, "wb");
					
					//That didn't work too, try one last time and don't use tempnam (buggy on some systems).
					if(!$handle) {
						$this->dprint("Warning: couldn't create a temporary file at '$filename'.", 2);

						//Last try : the plugin's directory, but with tempnam() 
						$filename=tempnam(dirname(__FILE__), "PLG");
						$this->dprint("Last attempt : using alternate temporary file '$filename'.", 1);
						$handle = fopen($filename, "wb");
					}
				}
				if(!$handle) {
					$this->dprint("Error: couldn't create a temporary file '$filename'.", 3);
					return new WP_Error('fs_tmp_failed', "Can't create a temporary file '$filename'.");	
				}
				
				fwrite($handle, $contents);
				fclose($handle);
				unset($contents);
			} else {
				$this->dprint("Download failed.", 3);
				return new WP_Error('download_failed', "Download failed.");
			}
		}
		if(empty($filename)){
			return new WP_Error('fs_no_file', "No file to extract. Weird.");
		}
		/**
		 * Extract the file
		 */
		$this->dprint("About to extract '$filename'.");
		$rez = $this->extractFile($filename, $type);
		if (is_wp_error($rez)){
			if ( ($rez->get_error_code() == 'zip_pclzip_unusable') || ($rez->get_error_code() == 'zip_unsupported') ){
				//Maybe we can try exec(unzip)...
				if (!empty($type)){
					$this->dprint("PclZip unavailable, using unzip.", 2);
					$rez = $this->extractFile($filename, $type, '', false);
					//Let the error code "fall through" to the end of the function.
				} else {
					$this->dprint("Error : PclZip error and can't use 'unzip' with autodetection.", 3);
					$rez = new WP_Error('zip_error', "Can't unzip the file. PclZip failed and 'unzip'
						can't be used with autodetection.");
				}
				
			}
		}
		
		/**
		 * Kill the temporary file no matter what
		 */
		@unlink($filename);
		
		return $rez;
	}
	
	function installer_page(){
		$type='autodetect';
		if (!empty($_POST['installtype'])){
			$type = $_POST['installtype'];
		} else if (!empty($_GET['installtype'])){
			$type = $_GET['installtype'];
		} else {
			$parts = explode('_', $_GET['page']);
			$type = $parts[1];
		}
		
		if (!in_array($type, array('autodetect', 'plugin', 'theme')))
			$type = 'autodetect';
			
		//Some quick status-checks based on type
		if ('autodetect' != $type){
			if ($type == 'plugin'){
				$target = WP_PLUGIN_DIR . '/';
			} else if ($type == 'theme'){
				if (function_exists('get_theme_root')){
					$target = get_theme_root() . '/';
				} else {
					$target = WP_CONTENT_DIR . '/themes/';
				} 
			}
			if (!$this->is__writable($target)){
				echo "<div class='error'><p><strong>Warning</strong> : The folder <code>$target</code>
				must be writable by PHP for the $type installer to work. See 
				<a href='http://codex.wordpress.org/Changing_File_Permissions'>Changing File Permissions</a>
				for a general guide on how to fix this.</p>
				</div>";
			}
		}
		
		$url = '';
		if (!empty($_POST['fileurl'])){
			$url = $_POST['fileurl'];
		} else if (!empty($_GET['fileurl'])){
			$url = $_GET['fileurl'];
		}
		
		//stupid URL verification
		$parts = @parse_url($url);
		if (empty($parts['scheme']) || ($parts['scheme']!='http')) 
			$url = '';
			
		$filename = '';
		if(!empty($_FILES['zipfile']['tmp_name'])) 
			$filename = $_FILES['zipfile']['tmp_name'];

		echo "<div class=\"wrap\">";
		
		if (!empty($url) || !empty($filename)){
			//Looks like there's something to do! But lets verify the nonce first.
			$arr = array_merge($_GET, $_POST);
			$nonce = !empty($arr['_wpnonce'])?$arr['_wpnonce']:'';
			$verified = false;
			if (empty($nonce)){
				//Missing nonce. Can only happen with external URL-based requests, legitimate or not.
				//So check for the presence of the "magic" key
				$magic = empty($_GET['magic'])?'':$_GET['magic'];
				if ($magic != $this->options['magic_key']){
					//It's debatable whether this looks good.
					echo "<div class='updated' style='text-align: center'><h3>Insecure Request</h3>\n
					<p>Looks like you're using the old OneClick FireFox extension, which is no
					longer supported by this plugin.</p>
					<p>Please install the new <strong>One-Click Installer for WP</strong> extension,
					which is more secure and easier to use.
					</p>
					<p>
					<a href='{$this->ff_extension_url}' 
						class='button'>Install The New Extension</a> 
					</p>
					</div>";
				} else {
					//Magic key OK. Do I need to ask for confirmation?
					if ($this->options['confirm_remote_installs']){
						//Let the user choose.
						$install_url = trailingslashit(get_option('siteurl')).
							'wp-admin/plugins.php?page=install_plugin&fileurl='.urlencode($url).
							"&installtype=$type";
						$install_url = wp_nonce_url($install_url, 'install_file');
							
						$dontinstall_url = trailingslashit(get_option('siteurl')).
							'wp-admin/plugins.php?page=install_plugin';
						
						if (($type == 'plugin') || ($type == 'theme')){
							$what = $type;
						} else $what = 'plugin or theme';
						
						//It's debatable whether this looks good.
						echo "<div class='updated' style='text-align: center'><h3>Are you sure?</h3>\n
						<p>Do you really want to install this $what on your blog? </p>
						<p><strong>$url</strong></p>
						<p>&nbsp;</p>
						<p>
						<a href='$install_url' class='button button-highlighted'>Yes, Install It</a> 
						<a href='$dontinstall_url' class='button' style='margin-left: 20px;'>Don't Install</a>
						</p>
						</div>";
					} else {
						//Good to go without nonce verification
						$verified = true;
					}
				}
			} elseif (wp_verify_nonce($nonce, 'install_file')){
				$verified = true;
			} else {
				//This shouldn't be possible. Nonce is invalid.
				echo "<div class='updated' style='text-align: center'><h3>Invalid Request</h3>\n
				<p>Do you really want to install this $what on your blog? </p>
				<p><strong>$url</strong></p>
				<p>&nbsp;</p>
				<p>
				<a href='$install_url' class='button button-highlighted'>Yes, Install It</a> 
				<a href='$dontinstall_url' class='button' style='margin-left: 20px;'>Don't Install</a>
				</p>
				</div>";
			}
				
			if ($verified) {
				//The nonce is valid.
				//Call the installer handler
				$rez = $this->do_install($url, $filename, $type);
				if (is_wp_error($rez)){
					//Format the error message nicely
					echo "<div class='error'><h3>Installer Error</h3>\n";
					echo "<p>".implode("\n<br />",$rez->get_error_messages())."</p>";
					
					echo "<p><a href='#' id='show_installer_log'>View the full log</a></p>";
					echo "<div id='installer_log' class='ws_installer_log'>";
					echo $this->format_debug_log();
					echo "</div>";
					
					echo "</div>";
				} else {
					$what = ($rez['type']); 
					$uwhat = ucfirst($what);
					
					echo "<div class='updated'><h3>$uwhat Installed</h3>\n";
					if(!empty($rez['file_header'])){
						$h = $rez['file_header'];
						echo "<p>The $what <strong>$h[name] $h[version]</strong> was installed successfuly.</p>";
						
						//Additional type-specific links 
						if ('plugin' == $rez['type']){
							//Activation link
							$plugin_file = $rez['plugin_file'];
							$activation_url = get_option('siteurl')."/wp-admin/" 
								.wp_nonce_url("plugins.php?action=activate&plugin=$plugin_file", 
									'activate-plugin_' . $plugin_file);
							echo "<p><a href='$activation_url'>Activate the plugin</a></p>";
						} else if ('theme' == $rez['type']){
							//No special processing
						}
						echo "<p><a href='";
						echo trailingslashit(get_option('siteurl'));
						echo "wp-admin/{$what}s.php'>View all installed {$what}s</a></p>";
						
					} else {
						echo "<p>However, I couldn't verify that it really is a $what. Hmm.</p>";
					}
					
					echo "<p><a href='#' id='show_installer_log'>View installation log</a></p>";
					echo "<div id='installer_log' class='ws_installer_log'>";
					echo $this->format_debug_log();
					echo "</div>";
					
					echo "</div>";
				}
			
?>
 <script> //<![CDATA[
 	$j = jQuery.noConflict();    
	// When the page is ready
	$j(document).ready(function(){
		$j("#show_installer_log").click(function(event){
			log = $j("#installer_log");
			if (log.is(':visible')){
				log.hide('normal');
			} else {
				log.show('normal');
			}
			// Stop the link click from doing its normal thing
			return false;
		});
   });
 //]]></script>
<?php
			}
		}
?>
<h2>Install From URL</h2>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $_GET['page']; ?>" method="post">
<?php wp_nonce_field('install_file'); ?>
<table class="form-table">
	<tr>
		<th>URL : </th>
		<td>
		<input type='text' name='fileurl' size='40' />
		</td>
	</tr>
	<tr>
		<th>Type: </th>
		<td>
			<select name="installtype" id="installtype">
				<option value="autodetect" <?php if ('autodetect' == $type) echo "selected='selected'";?>>
					Detect automatically
				</option>
				<option value="plugin" <?php if ('plugin' == $type) echo "selected='selected'";?>>
					Plugin
				</option>
				<option value="theme" <?php if ('theme' == $type) echo "selected='selected'";?>>
					Theme
				</option>
			</select>
		</td>
	</tr>
	<tr>
		<td></td>
		<td>
			<input type="submit" name="Submit" value="Install" class="button installer-button" />
		</td>
	</tr>
</table>
</form>
  
<h2>Install From File</h2>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $_GET['page']; ?>" 
ENCTYPE="multipart/form-data" method="post">
<?php wp_nonce_field('install_file'); ?>
<table class="form-table">
	<tr>
		<th>File : </th>
		<td>
		<input type='file' name='zipfile' size='40' />
		</td>
	</tr>
	<tr>
		<th>Type: </th>
		<td>
			<select name="installtype" id="installtype">
				<option value="autodetect" <?php if ('autodetect' == $type) echo "selected='selected'";?>>
					Detect automatically
				</option>
				<option value="plugin" <?php if ('plugin' == $type) echo "selected='selected'";?>>
					Plugin
				</option>
				<option value="theme" <?php if ('theme' == $type) echo "selected='selected'";?>>
					Theme
				</option>
			</select>
		</td>
	</tr>
	<tr>
		<td></td>
		<td>
			<input type="submit" name="Submit" value="Install" class="button installer-button" />
		</td>
	</tr>
</table>  
</form>
  
</div>
<?php

	}
	
	/**
	 * Displays a page discussing the differences between OneClick and this plugin,
	 * and how to use this plugin.
	 */
	function miniguide_page(){
?>
	<div class="wrap">

  	<h2>One Click Updater Miniguide</h2>
  	<p>Greetings, sentient. On this page you will find a short overview of the plugin's features and how to 
	use them. This will be especially useful if you have been using the old OneClick plugin before.</p>
	
	<h3>"One Click Plugin Updater" vs "OneClick"</h3>
	<p>These are two different plugins, created by two different developers and - initially - for different 
	goals. However, <em>One Click Updater</em> has now officially become <em>OneClick's</em> successor. 
	It includes all the main features of OneClick, and more.</p>
	
	<h3>Installing Plugins and Themes</h3>
	You can do this by going to <em>Plugins -&gt; Install a Plugin</em> or <em>Design -&gt; Install a Theme</em>,
	respectively. Plugins/themes can be installed either from an URL (e.g. "http://something.com/plugin.zip"), 
	or by uploading a ZIP archive from your computer. You will need to make sure the relevant
	directories are writable by WordPress, as at this time the plugin only supports direct filesystem access.
	FTP support may be added in a later version.
	
	<h3>Firefox Extension</h3>
	<p>You can also use a Firefox add-on that will allow you to easily install plugins or themes by 
	right-clicking on a download link and selecting "Install on your blog". The plugin automatically
	detects wheter the link represents a plugin or a theme.
	<a href='<?php echo $this->ff_extension_url; ?>'>Get the extension here.</a></p>
	
	<p>Older versions of this plugin (up to version 2.1.2) support the 
	<a href='https://addons.mozilla.org/en-US/firefox/addon/5503'>OneClick for WordPress</a> add-on. This 
	backwards compatibility has been dropped in later versions in favor of the 
	<a href='<?php echo $this->ff_extension_url; ?>'>aforementioned extension</a>, which is more secure
	and supports autodetection.  
	</p>
	
	<h3>Deleting Plugins and Themes</h3>
	<p>You can delete an inactive plugin by clicking the appropriate "Delete" link in the <em>Plugins</em> 
	tab. You can also delete themes in a similar way in the <em>Design -&gt; Themes</em> tab. 
	The plugin will ask for confirmation when you try to delete something, but if you say "Yes" the thing
	you deleted will be gone for good, so use this feature wisely.
	</p>
	
	<h3>Automatic Upgrades</h3>
	<p>This plugin lets you upgrade other plugins with a single click in WP 2.3 and up. WP 2.5 introduced
	it's own built-in plugin updater, but you can still use this plugin for that if the built-in version
	doesn't work. If using the plugin, you can also perform all pending updates with a single click ;)  
	Select which one to use under <em>Plugins -&gt; Upgrade Settings</em>.</p>
	
	<p>Some other interesting stuff you can configure on that page : 
	<ul>
		<li>Global update notifications (notifies you about available upgrades on all admin pages, not just
			<em>Plugins</em>).</li>
		<li>Whether to inform WordPress.org about your blog's URL and version when checking 
			(WP does this by default).</li>
		<li>How often should WP check for plugin and core updates (you can even completely turn them off
			if you want).</li>
	</ul> 
	</p>
	
	<h3>Reporting Bugs</h3>
	You can leave a comment <a href='http://w-shadow.com/blog/2008/04/06/one-click-updater-plugin-20/'>
	on my blog</a> or email me at <a href='mailto:whiteshadow@w-shadow.com'>whiteshadow@w-shadow.com</a>. 
	
	<p>That's all for this short introduction :) If you like, you can hide this page by unchecking the
	"Show Miniguide" box under <em>Plugins -&gt; Upgrade Settings</em>.</p>  
	
  	</div>
  	
<?php		
	}
	
	function permanent_notices(){
		$notices = get_option ('permanent_admin_notices');
		if (empty($notices)) return;
		foreach ($notices as $key=>$notice){
			echo "<div class='ws-plugin-update' id='permanent-notice-$key'>";
			echo $notice;
			echo " <small><a href='javascript:hide_permanent_notice(\"$key\")'>[hide]</a></small>";
			echo "</div>";
		}
	}
	
	/** 
	 * Displays a message that plugin updates are available if they are
	 * Originally by Viper007Bond @ http://www.viper007bond.com/
	 */
	function global_plugin_notices() {
		//Only administrators will see the notices
		if (!current_user_can('edit_plugins')) return;
		
		$current = $this->get_update_plugins();

		if ( empty( $current->response ) ) return; // No plugin updates available
		
		if (!function_exists('activate_plugin')) return; //Only in WP 2.5

		$active = get_option('active_plugins');
		//if ( empty($active_plugins) || !is_array($active_plugins) ) return;

		$plugins = get_plugins();

		$update_list = array();
		
		$header = false;
		foreach ( $current->response as $plugin_file => $update_data ) {
 
			//Skip certain notifications
			if ( 
				 //Plugin must be actually installed
				 empty( $plugins[$plugin_file] ) ||
				 //Plugin must be active (user-configurable)
				 (
				 	$this->options['hide_notifications_for_inactive'] 
					 && !in_array( $plugin_file, $active)
				 ) 
				) continue;
				
				
			// Make sure there is something to display
			if ( empty($plugins[$plugin_file]['Name']) ) $plugins[$plugin_file]['Name'] = $plugin_file;
			
			$update_list[] = "<strong>".$plugins[$plugin_file]['Name']."</strong>";
		}
		
		if (count($update_list)>0){
			echo '	<div class="ws-plugin-update">';
			$link =  get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
					'/do_update.php?action=upgrade_all';
			$link = wp_nonce_url($link, 'upgrade_all');
			//$plugin_msg .= " <a href=\'$link\' class=\'button\'>Upgrade All</a>";
			
			if (count($update_list)==1){
				$name = array_pop($update_list);
				if ( !current_user_can('edit_plugins') )
					printf( __('There is a new version of %1$s available.'), $name);
				else
					printf( __('There is a new version of %1$s available. 
						<a href="%2$s" class="button">Upgrade Automatically</a>'), 
						$name, $link);
			} else{
				//make a nice listing :)
				$name = implode(', ', array_slice($update_list,0,count($update_list)-1));
				$name .= ' and '.array_pop($update_list);
				
				if ( !current_user_can('edit_plugins') )
					printf( __('There are new versions available for %1$s.'), $name);
				else
					printf( __('There are new versions available for %1$s. 
						<a href="%2$s" class="button">Upgrade All</a>'), 
						$name, $link);
			}
	
			echo "</div>\n";
		}
	}
	
	/**
	 * Recursively delete a directory and all files in it (ver 2)
	 */
	function deltree($directory){
	    if (substr($directory, -1) == '/')
	    {
	        $directory = substr($directory, 0, -1);
	    }
	    if (!file_exists($directory) || !is_dir($directory))
	    {
	    	$this->dprint("'$directory' : Path doesn't exist or isn't a directory!", 3);
	        return false;
	    }  else  {
	    	$this->dprint("Processing directory '$directory'...");
	        $handle = opendir($directory);
	        while (false !== ($item = readdir($handle)))
	        {
	            if ( ($item != '.') && ($item != '..'))
	            {
	                //$path = $directory . DIRECTORY_SEPARATOR . $item; //this just causes trouble 
					//Edit: Situation ambiguous. At this time the (non-)use of 
					//the DIRECTORY_SEPARATOR constant is inconsistent in the code.
	                $path = $directory . '/' . $item;
	                if (is_dir($path) && !is_link($path)) {
	                    if (!$this->deltree($path)){
							return false;
						};
	                } else {
	                	$this->dprint("Deleting file $path",1);
	                    if (!unlink($path)){
							$this->dprint("Can't delete file '$path'",3);
							return false;
						};
	                }
	            }
	        }
	        closedir($handle);
	        $this->dprint("Deleting directory $directory",1);
            if (!rmdir($directory)) {
            	$this->dprint("Can't delete directory '$directory'",3);
	            return false;
	        }
	        return true;
	    }
	}
    
}//class ends here

} // if !class_exists... ends here

$ws_pup = new ws_oneclick_pup();

//}

?>