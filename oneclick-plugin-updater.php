<?php
/*
Plugin Name: One Click Plugin Updater
Plugin URI: http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/
Description: Adds an "update automatically" link to plugin update notifications and marks plugins that have notifications enabled. 
Version: 1.1
Author: Janis Elsts
Author URI: http://w-shadow.com/blog/
*/

/*
Created by Janis Elsts (email : whiteshadow@w-shadow.com) 
It's GPL.
*/

if (!class_exists('ws_oneclick_pup')) {

class ws_oneclick_pup {
	var $version='1.0.5';
	var $myfile='';
	var $myfolder='';
	var $mybasename='';
	
	var $update_enabled='';

	function ws_oneclick_pup() {
		global $wpdb;
		
		$my_file = str_replace('\\', '/',__FILE__);
		$my_file = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', $my_file);

		$this->myfile=$my_file;
		$this->myfolder=basename(dirname(__FILE__));
		$this->mybasename=plugin_basename(__FILE__);
		
		$this->update_enabled=get_option('update_enabled_plugins');
		
		add_action('after_plugin_row', array(&$this, 'plugin_update_row'));
		add_action('admin_head', array(&$this,'admin_head'));
		add_action('admin_print_scripts', array(&$this,'admin_scripts'));
		add_action('load-plugins.php', array(&$this,'check_update_notifications'));
		
		//debug
		add_action('admin_footer', array(&$this,'admin_foot'));
	}
	
	function admin_head(){
		if (stristr($_SERVER['REQUEST_URI'], 'plugins.php')===false) return;
		//wp_print_scripts( array( 'jquery' ));
		echo "<link rel='stylesheet' href='";
		echo get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.'/single-click.css';
		echo "' type='text/css' />";
	}
	
	function admin_scripts(){
		if (stristr($_SERVER['REQUEST_URI'], 'plugins.php')===false) return;
		wp_print_scripts( array( 'jquery' ));
	}
	
	function admin_foot(){
		if (stristr($_SERVER['REQUEST_URI'], 'plugins.php')===false) return;
			$plugins=get_plugins();
		?>

<script type="text/javascript">
	var update_enabled_plugins = Array(
<?php 
if (isset($this->update_enabled->status) && (count($this->update_enabled->status)>0)) {
	foreach($this->update_enabled->status as $file => $enabled) {
		if($enabled)
			echo "\t\t\"",$plugins[$file]['Name'],"\",\n";
	}
}
?>
	"");

	$j = jQuery.noConflict();
	
	$j(document).ready(function() {
		for(i = 0; i < update_enabled_plugins.length - 1; i++) {
			plugin = update_enabled_plugins[i];
			$j("td.name:contains('"+plugin+"')").each(function (x) {
		        if ($j(this).text() == plugin) {
					$j(this).addClass('update-notification-enabled');
		        };
			});
		}
	});	
</script>
		<?php
		
	}
	
	function plugin_update_row( $file ) {
		global $plugin_data;
		$current = get_option( 'update_plugins' );
		if ( !isset( $current->response[ $file ] ) )
			return false;
	
		$r = $current->response[ $file ];
		$autoupdate_url=get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.
		 '/do_update.php?plugin_url='.urlencode($r->url).'&plugin_file='.urlencode($file);
	
		echo "<tr><td colspan='5' class='plugin-oneclick-update'>";
		printf('There is a new version of %s available. <a href="%s">Download version %s here</a>'.
			' or <a href="%s">update automatically</a>.', 
			$plugin_data['Name'], $r->url, $r->new_version, $autoupdate_url);
		echo "</td></tr>";
	}
	
	function check_update_notifications(){
		global $wp_version;

		if ( !function_exists('fsockopen') )
			return false;
		
		/*
		echo '<pre>';
		print_r($this->update_enabled);
		echo '</pre>';
		//*/
		
		$plugins = get_plugins();
		$active  = get_option( 'active_plugins' );
		
		$plugin_changed = false;
		foreach ( $plugins as $file => $p ) {
			$plugins[$file]['Version']='0.0'; //fake zero version 
			if( !isset($this->update_enabled->status[$file]) ) {
				$this->update_enabled->status[$file]=false; //not known yet, assume false
				$plugin_changed = true;
				continue;
			}
		}
		
		//$plugin_changed=true; //XXXXXX debug - force status update
		if (
			isset( $this->update_enabled->last_checked ) &&
			( ( time() - $this->update_enabled->last_checked ) < 172800) &&
			!$plugin_changed
		)
			return false;
			
		$this->update_enabled->last_checked=time();
	
		$to_send->plugins = $plugins;
		$to_send->active = $active;
		$send = serialize( $to_send );
		
		$request = 'plugins=' . urlencode( $send );
		$http_request  = "POST /plugins/update-check/1.0/ HTTP/1.0\r\n";
		$http_request .= "Host: api.wordpress.org\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		$http_request .= 'User-Agent: WordPress/' . $wp_version . '; ' . get_bloginfo('url') . "\r\n";
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
	}
	
	function download_page($url, $timeout=30){
		$parts=parse_url($url);
		if(!$parts) return false;
		
		if(!isset($parts['scheme'])) $url='http://'.$url;
		
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			
			$response = curl_exec($ch);
			curl_close($ch);
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
			echo 'Oops, sorry, you are not authorized to deactivate plugins!';
			return false;
		}
		global $wpdb;
		$current = get_option('active_plugins');
		array_splice($current, array_search($plugin, $current), 1 ); // Array-fu!
		update_option('active_plugins', $current);
		do_action('deactivate_' . trim( '../' . $plugin ));
	}
	
	function extractPlugin($zipfile) {
		$target_dir=ABSPATH.'wp-content/plugins/';
	    $archive = new PclZip($zipfile);
	    $rez = false;
	    if (function_exists('gzopen')) {
	        if ($archive->extract(PCLZIP_OPT_PATH, $target_dir, PCLZIP_OPT_REPLACE_NEWER) != 0) {
		        $rez=true;
	        };
        }
        if ((!$rez) && function_exists('exec')) {
			exec("unzip -d $target_dir $zipfile", $ignored, $return_val);
			$rez = $return_val == 0;
	    }
        return $rez;
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
    
}//class ends here

} // if class_exists... ends here

$ws_pup = new ws_oneclick_pup();

?>