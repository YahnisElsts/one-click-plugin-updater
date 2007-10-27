<?php
/*
Plugin Name: One Click Plugin Updater
Plugin URI: http://w-shadow.com/blog/2007/10/19/one-click-plugin-updater/
Description: Adds an "update automatically" link to plugin update notifications.
Version: 1.0.5
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
	

	function ws_oneclick_pup() {
		global $wpdb;
		
		$my_file = str_replace('\\', '/',__FILE__);
		$my_file = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', $my_file);

		$this->myfile=$my_file;
		$this->myfolder=basename(dirname(__FILE__));
		$this->mybasename=plugin_basename(__FILE__);
		
		add_action('after_plugin_row', array(&$this, 'plugin_update_row'));
		add_action('admin_head', array(&$this,'admin_head'));
	}
	
	function admin_head(){
		if (preg_match('/\/plugins\.php/', $_SERVER['REQUEST_URI'])) {
			echo "<link rel='stylesheet' href='";
			echo get_option('siteurl').'/wp-content/plugins/'.$this->myfolder.'/single-click.css';
			echo "' type='text/css' />";
		};
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