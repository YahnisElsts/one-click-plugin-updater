<?php
/*
Plugin Name: One Click Plugin Updater
Plugin URI: http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/
Description: Upgrade plugins with a single click, install new plugins or themes from an URL or by uploading a file, see which plugins have update notifications enabled, control how often WordPress checks for updates, and more. Beta.
Version: 2.0.7
Author: Janis Elsts
Author URI: http://w-shadow.com/blog/
*/

/*
Created by Janis Elsts (email : whiteshadow@w-shadow.com) 
It's GPL.
*/

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

if (!class_exists('ws_oneclick_pup')) {

class ws_oneclick_pup {
	var $version='2.0.7';
	var $myfile='';
	var $myfolder='';
	var $mybasename='';
	var $debug=false;
	var $debug_log;
	
	var $update_enabled='';
	
	var $options;
	var $options_name='ws_oneclick_options';
	var $defaults;

	function ws_oneclick_pup() {
		$my_file = str_replace('\\', '/',__FILE__);
		$my_file = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', $my_file);

		$this->myfile=$my_file;
		$this->myfolder=basename(dirname(__FILE__));
		$this->mybasename=plugin_basename(__FILE__);
		
		$this->debug_log = array();
		
		$this->defaults = array(
			'version' => $this->version,
			'updater_module' => 'updater_plugin',
			'enable_plugin_checks' => true,
			'enable_wordpress_checks' => true,
			'anonymize' => false,
			'plugin_check_interval' => 43201,
			'wordpress_check_interval' => 43200,
			'global_notices' => false,
			'debug' => false,
		);
		$this->options = get_option($this->options_name);
		if(!is_array($this->options)){
			$this->options = $this->defaults;				
		} else {
			$this->options = array_merge($this->defaults, $this->options);
		}
		
		$this->debug = $this->options['debug'];
		
		$this->update_enabled=get_option('update_enabled_plugins');
		
		add_action('activate_'.$this->myfile, array(&$this,'activation'));
		add_action('admin_head', array(&$this,'admin_head'));
		add_action('admin_print_scripts', array(&$this,'admin_scripts'));
		add_action('admin_footer', array(&$this,'admin_foot'));
		add_action('admin_menu', array(&$this, 'add_admin_menus'));
		//add_action('admin_init', array(&$this, 'admin_init'));
		
		//This is used both for marking plugins with enabled notifications
		//and checking at different time intervals.	
		if ($this->options['enable_plugin_checks'])
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
		
		//The global update notices (WP 2.5 only)
		if ( file_exists( ABSPATH . 'wp-admin/update.php' ) ) {
			if ($this->options['global_notices'])
				add_action( 'admin_notices', array(&$this, 'global_plugin_notices') );
		}
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
		update_option($this->options_name, $this->options);
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
	
	function admin_init(){
		//Hackety-hack! Unfortunately I can't do this earlier (AFAIK).
		//Unfortunately, the admin_init hook only exists in WP 2.5
		
		if ($this->options['enable_plugin_checks']){
			if ($this->options['updater_module']=='updater_plugin'){
				remove_action('after_plugin_row', 'wp_plugin_update_row'); //Muahahaha
				add_action('after_plugin_row', array(&$this, 'plugin_update_row'));
			}
		} else {
			remove_action('after_plugin_row', 'wp_plugin_update_row');
			remove_action('load-plugins.php', 'wp_update_plugins');
		}
		
		if (!$this->options['enable_wordpress_checks']){
			remove_filter( 'update_footer', 'core_update_footer' );
			remove_action( 'admin_notices', 'update_nag', 3 );
		}
		
		if( $this->options['anonymize'] || ($this->options['plugin_check_interval'] != 43200) ){
			remove_action('load-plugins.php', 'wp_update_plugins');					
		}
		
	}
	
	function add_admin_menus(){
		//*
		add_submenu_page('plugins.php', 'Upgrade Settings', 'Upgrade Settings', 'edit_plugins', 
			'plugin_upgrade_options', array(&$this, 'options_page'));
			
		add_submenu_page('plugins.php', 'Install New', 'Install a Plugin', 'edit_plugins', 
			'install_plugin', array(&$this, 'installer_page'));
		add_submenu_page('themes.php', 'Install New', 'Install a Theme', 'edit_themes', 
			'install_theme', array(&$this, 'installer_page'));
		//*/
	}
	
	function admin_head(){
		//In this version the theme is also used in other pages.
		if ( (stristr($_SERVER['REQUEST_URI'], 'plugins.php'===false)) &&
			 (stristr($_SERVER['REQUEST_URI'], 'themes.php'===false)) )
			return;
		
		$this->admin_init();
		
		echo "<link rel='stylesheet' href='";
		echo get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.'/single-click.css';
		echo "' type='text/css' />";
	}
	
	function admin_scripts(){
		if (stristr($_SERVER['REQUEST_URI'], 'plugins.php')===false) return;
		wp_print_scripts( array( 'jquery' ));
	}
	
	function admin_foot(){
		//xxxx
		/*
		echo '<pre>';
		$update = get_option('update_plugins');
		print_r($update);
		echo '</pre>';
		//*/
		
		//Only execute on the plugin list itself.
		if ( (stristr($_SERVER['REQUEST_URI'], 'plugins.php')===false) ||
			 (!empty($_GET['page']))
		) return;
		
		$plugins=get_plugins();
		$update = get_option('update_plugins');
		$active  = get_option('active_plugins' );
		$count_active = count($active);
		if (is_array($update->response)){
			$count_update = count($update->response);
		} else $count_update = 0;
		
		$plugin_msg = "$count_active active plugins";
		if ($count_update>0){
			$s = ($count_update==1)?'':'s';
			$plugin_msg .= ", <strong>$count_update upgrade$s available</strong>";
		
			if (function_exists('activate_plugin')){
				$link =  get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
					'/do_update.php?action=upgrade_all';
				$link = wp_nonce_url($link, 'upgrade_all');
				$plugin_msg .= " <a href=\'$link\' class=\'button\'>Upgrade All</a>";
			}
		}

		?>

<script type="text/javascript">
	var update_enabled_plugins = Array();
<?php 
if (isset($this->update_enabled->status) && (count($this->update_enabled->status)>0)) {
	foreach($this->update_enabled->status as $file => $enabled) {
		echo "\t update_enabled_plugins[\"",$plugins[$file]['Name'],"\"] = ",
			($enabled?'true':'false'),";\n";
	}
}

echo "\tvar plugin_msg = '$plugin_msg';";
?>

	$j = jQuery.noConflict();
	
	$j(document).ready(function() {
		//Add different CSS dependent on whether a plugin has update notifications enabled. 
		$j("td.name").each(function (x) {
			if (update_enabled_plugins[$j(this).text()]) {
				$j(this).addClass('update-notification-enabled');
			} else {
				$j(this).addClass('update-notification-disabled');
			};
		});
		
		//Add a status msg. 
		$j("div.wrap h2:first").after("<p class='plugins-overview'>"+plugin_msg+"</p>");
	});	
</script>
		<?php
		
	}
	
	function plugin_update_row( $file ) {
		global $plugin_data;
		
		$current = get_option( 'update_plugins' );
		if ( !isset( $current->response[ $file ] ) ){
			return false;
		}
	
		$r = $current->response[ $file ];
		$autoupdate_url=get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
		 '/do_update.php?plugin_file='.urlencode($file);
		if(!empty($r->package)){
			$autoupdate_url .= '&download_url='.urlencode($r->package);
		} else {
			$autoupdate_url .='&plugin_url='.urlencode($r->url);
		}
		
		//Add nonce verification for security
		$autoupdate_url = wp_nonce_url($autoupdate_url, 'update_plugin-'.$file);
	
		echo "<tr><td colspan='5' class='plugin-update'>";
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
		echo "</td></tr>";
	}
	
	function check_update_notifications(){
		global $wp_version;
		@set_time_limit(300);

		if ( !function_exists('fsockopen') )
			return false;
		
		$plugins = get_plugins();
		$orig_plugins = $plugins;
		$active  = get_option( 'active_plugins' );
		
		$plugin_changed = false;
		$core_override = false; 
		/* Whether to set the update_plugins option. There's additional checking and 
		   processing at the end of this function.	*/
		
		foreach ( $plugins as $file => $p ) {
			$plugins[$file]['Version']='0.0'; //fake zero version 
			if( !isset($this->update_enabled->status[$file]) ) {
				$this->update_enabled->status[$file]=false; //not known yet, assume false
				$plugin_changed = true;
				continue;
			}
		}
		//Remove information about deleted plugins
		$remaining = $this->update_enabled->status;
		foreach($remaining as $file => $status){
			if (!isset($plugins[$file])){
				unset($this->update_enabled->status[$file]);
				$plugin_changed = true;
				$core_override = true;
			}
		}
		
		//$plugin_changed=true; //XXXXXX debug - force status update
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
		$http_request .= 'User-Agent: WordPress/2.3; http://not-really-a-domain.com/' . "\r\n";
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
			$cleaned_response = array();
			foreach($response as $file => $data) {
				$this->update_enabled->status[$file]=true;
				if ($data->new_version > $orig_plugins[$file]['Version']){
					$cleaned_response[$file] = $data;
				}
			}
		}
		
		update_option( 'update_enabled_plugins', $this->update_enabled);
		
		/** Save the info for WP as well, if I'm supposed to **/
		if( ($this->options['anonymize'] || ($this->options['plugin_check_interval'] != 43200) || $core_override)
			&& ($this->options['enable_plugin_checks'])
		 ){
			$new_option = '';
			$new_option->last_checked = time();
			$new_option->checked = array(); //it's not being used anywhere else in WP core anyway.
			if ( isset($cleaned_response) ) $new_option->response = $cleaned_response;
			
			update_option( 'update_plugins', $new_option );						
		}
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
	
	function download_page($url, $timeout=40){
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
		
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			/* Currently redirection support is not absolutely necessary, so it's OK
			if this line fails due to safemode restrictions */
			
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
	
	function extractPlugin($zipfile) {
		$this->dprint("extractPlugin() method entered.");
		
		$target_dir=ABSPATH.'wp-content/plugins/';
		$this->dprint("Extraction target directory : '$target_dir' (should be absolute path)");
		
	    $archive = new PclZip($zipfile);
	    $rez = false;
	    if (function_exists('gzopen')) {
		    $this->dprint("gzopen() found, will use PclZip.");
	        if ($extracted_files = $archive->extract(PCLZIP_OPT_PATH, $target_dir, 
	        					PCLZIP_OPT_REPLACE_NEWER, PCLZIP_OPT_STOP_ON_ERROR)) 
	        {
		        
		        $this->dprint("PclZip reports success - plugin should have been extracted.");
		        $this->dprint("PclZIp extraction log : <pre>");
		        if ($this->debug) {
			        print_r($extracted_files);
		        };
		        $this->dprint("</pre>");
		        
		        $rez=true;
	        } else {
		        $this->dprint("Error: PclZip reports failure. '".$archive->errorInfo(true)."'");
	        };
        }
        if ((!$rez) && function_exists('exec')) {
	        $this->dprint("gzopen() not found or PclZip error. Running unzip instead.");
			exec("unzip -uovd $target_dir $zipfile", $ignored, $return_val);
			$rez = $return_val == 0;
			$this->dprint("unzip returned value '$return_val'. unzip log : ");
			if($this->debug) {
				echo "<pre>";
				print_r($ignored);
				echo "</pre>";
			};			
	    }
	    
	    if (!$rez) {
		    $this->dprint("extractPlugin() failed. No way to extract zip files.");
	    } else {
		    $this->dprint("extractPlugin() succeeded.");
	    }
	    
        return $rez;
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
				$target = ABSPATH . PLUGINDIR . '/';
			} else if ($type == 'theme'){
				if (function_exists('get_theme_root')){
					$target = get_theme_root() . '/';
				} else {
					$target = ABSPATH . 'wp-content/themes/';
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
				$this->dprint("unzip returned value '$return_val'. unzip log : ");
				if($this->debug) {
					echo "<pre>";
					print_r($ignored);
					echo "</pre>";
				};
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
		    
			//Try to extract all of the files in-memory. Warning : may overrun memory limits!!
			if ( false == ($archive_files = $archive->listContent()) ){
				// Nope.
				$this->dprint("PclZip failed!", 3);
				return new WP_Error('zip_unsupported', "The archive format is not supported.");
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
							$target = ABSPATH . PLUGINDIR . '/';
							break;
						}
						
						$theme = $this->get_theme_info($file['content']);
						if (!empty($theme['theme name'])) {
							$this->dprint("\tFound a theme header! It is '".$theme['theme name']."'", 1);
							$type = 'theme';
							$magic_descriptor['file_header'] = $theme;
							$magic_descriptor['type'] = $type;
							$target = ABSPATH . 'wp-content/themes/';
							break;
						}
						
					}
					
					if (empty($target)){
						$this->dprint("Autodetection failed!", 3);
						return new WP_Error("ad_failed", "Autodetection failed - this doesn't look like a plugin or a theme.");
					}
				}
				//Finally, extract the files! Code shamelessly stolen from WP core (file.php).
				$to = trailingslashit($target);
				$this->dprint("Starting extraction to folder '$to'.", 1);
				/**
				 * I'm going to bloody well assume the target directory exists! This is a
				 * workaround for a bug; it needs to be fixed sooner or later!
				 */ 
				/*
				$path = explode('/', $to);
				$tmppath = '';
				for ( $j = 0; $j < count($path) - 1; $j++ ) {
					$tmppath .= $path[$j] . '/';
					if ( ! is_dir($tmppath) && ($tmppath != '/')){
						$this->dprint("Creating directory '$tmppath'", 1);
						if ( ! mkdir($tmppath, 0755)) {
							$this->dprint("Can't create directory '$tmppath!'", 3);
							return new WP_Error('fs_mkdir', "Can't create directory '$tmppath'.");
						};
					}
				}
				*/

				foreach ($archive_files as $file) {
					$path = explode('/', $file['filename']);
					$tmppath = '';
					// Loop through each of the items and check that the folder exists.
					for ( $j = 0; $j < count($path) - 1; $j++ ) {
						if ($path[$j]=='') continue;
						$tmppath .= $path[$j] . '/';
						if ( ! is_dir($to . $tmppath) ){
							$this->dprint("Creating directory '{$to}{$tmppath}'", 1);
							if ( !mkdir($to . $tmppath, 0755) ){
								$this->dprint("Can't create directory '$tmppath' in '$to'!", 3);
								return new WP_Error('fs_mkdir', "Can't create directory '$tmppath' in '$to'.");
							}
						}
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
							//special handling for zero-byte files (file_put_contents woudln't work)
							$fh = @fopen($to . $file['filename'], 'wb');
							if(!$fh){
								$this->dprint("Can't create a zero-byte file $file[filename] in $to!", 3);
								return new WP_Error('fs_put_contents', 
									"Can't create a zero-byte file '$file[filename]' in '$to'");
							}
							fclose($fh);
						}
						@chmod($to . $file['filename'], 0644); //I think this can be allowed to fail.
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
				$names[strtolower($match[1])] = trim($match[2]);
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
			$this->options['plugin_check_interval'] = intval($_POST['plugin_check_interval']);
			$this->options['wordpress_check_interval'] = intval($_POST['wordpress_check_interval']);
			$this->options['debug'] = !empty($_POST['debug']);
			$this->debug = $this->options['debug'];
			update_option($this->options_name, $this->options);

			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		} 
		
		?>
<div class="wrap">
<h2>Upgrade Settings</h2>
<p>Here you can configure plugin update notifications, set how often WordPress checks for new versions, and so on. This page was created by the <a>One Click Plugin Updater</a> plugin.</p>

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

<h3>Other</h3>
<table class="form-table">
	<tr>
		<td colspan='2' style='font-size: 1em;'>
		URL for the <a href='https://addons.mozilla.org/en-US/firefox/addon/5503'>OneClick FireFox extension</a> (different author): <br />
		<?php
		echo get_option('siteurl').'/wp-admin/plugins.php?page=install_plugin';
		?>
		</td>
	</tr>
	<tr>
		<th colspan='2' align='left'>
		<label><input type='checkbox' name='debug' id='debug' <?php
			if ($this->debug) echo "checked='checked'";
		?> />
		Enable debug mode </label></th>
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
			$contents = $this->download_page($url, 120);
			if ($contents){
				$this->dprint("Downloaded ".strlen($contents)." bytes.", 1);
				$filename = tempnam("/tmp", "PLG");
				$this->dprint("Will save the new version archive (zip) to a temporary file '$filename'.");
				$handle = @fopen($filename, "wb");
				if(!$handle) {
					$this->dprint("Warning: couldn't create a temporary file at '$filename'.", 2);
					//try to use the plugin's folder instead
					$filename=tempnam(dirname(__FILE__), "PLG");
					$this->dprint("Using alternate temporary file '$filename'.", 1);
					$handle = fopen($filename, "wb");
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
			if ($rez->get_error_code() == 'zip_pclzip_unusable'){
				//Maybe we can try exec(unzip)...
				if (!empty($type)){
					$this->dprint("PclZip unavailable, using unzip.", 2);
					$rez = $this->extractFile($filename, $type, '', false);
					//Let the error code "fall through" to the end of the function.
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
				$target = ABSPATH . PLUGINDIR . '/';
			} else if ($type == 'theme'){
				if (function_exists('get_theme_root')){
					$target = get_theme_root() . '/';
				} else {
					$target = ABSPATH . 'wp-content/themes/';
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
			if (!wp_verify_nonce($nonce, 'install_file')){
				//Invalid nonce. Can only happen with external URL-based requests, legitimate or not.
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
	 * Displays a message that plugin updates are available if they are
	 *
	 * @author	Viper007Bond
	 * @authoruri http://www.viper007bond.com/
	 */
	function global_plugin_notices() {
		$current = get_option( 'update_plugins' );

		if ( empty( $current->response ) ) return; // No plugin updates available

		// Since the message can get spammy, only display activated plugins
		$active_plugins = get_option('active_plugins');
		if ( empty($active_plugins) || !is_array($active_plugins) ) return;

		$plugins = get_plugins();

		$updatelist = array();

		$first = true;

		foreach ( $current->response as $plugin_file => $update_data ) {
			// Make sure the plugin data is known and that it's activated
			if ( empty( $plugins[$plugin_file] ) || !in_array( $plugin_file, $active_plugins ) ) continue;

			// Make syre there is something to display
			if ( empty($plugins[$plugin_file]['Name']) ) $plugins[$plugin_file]['Name'] = $plugin_file;
			
			$r = $update_data;
			$autoupdate_url=get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
			 '/do_update.php?plugin_file='.urlencode($plugin_file);
			if(!empty($r->package)){
				$autoupdate_url .= '&download_url='.urlencode($r->package);
			} else {
				$autoupdate_url .='&plugin_url='.urlencode($r->url);
			}
			//Add nonce verification for security
			$autoupdate_url = wp_nonce_url($autoupdate_url, 'update_plugin-'.$plugin_file);

			echo '	<div class="plugin-update"';
			if ( TRUE != $first ) echo ' style="border-top:none"';
			echo '>';

			if ( !current_user_can('edit_plugins') )
				printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a>.'), 
					$plugins[$plugin_file]['Name'], $update_data->url, $update_data->new_version);
			elseif ( empty($update_data->package) )
				printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a> <em>automatic upgrade unavailable for this plugin</em>.'), 
					$plugins[$plugin_file]['Name'], $update_data->url, $update_data->new_version);
			else
				printf( __('There is a new version of %1$s available. <a href="%2$s">Download version %3$s here</a> or <a href="%4$s">upgrade automatically</a>.'), 
					$plugins[$plugin_file]['Name'], $update_data->url, $update_data->new_version,
						$autoupdate_url);

			echo "</div>\n";

			$first = FALSE;
		}
	}
    
}//class ends here

} // if class_exists... ends here

$ws_pup = new ws_oneclick_pup();

?>