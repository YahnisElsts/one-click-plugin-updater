<?php
/*
	Update the plugin identified by $_GET['plugin_file']
*/
	require_once("../../../wp-config.php");
	
	if(!current_user_can('edit_plugins')) {
			die('Oops, sorry, you are not authorized to fiddle with plugins!');
	}
	
	//check if it's possible to write to plugins directory
	$plugin_dir=ABSPATH.'wp-content/plugins/';
	if (!$ws_pup->is__writable($plugin_dir)){
		?>
		<b>Error : the directory <?php echo $plugin_dir; ?> is not writable by Wordpress.</b><br/>
		You may need to assign permissions 666, 755 or even 777 to your "plugins" directory
		(depending on your server configuration). For more information on what file permissions are and
		how to change them read 
		<a href='http://www.interspire.com/content/articles/12/1/FTP-and-Understanding-File-Permissions'>Understanding file permisssions</a>.
		<?php
		die();
	}
	
	if (!class_exists('PclZip'))
	{
	        require_once ('pclzip.lib.php');
	}
	//error_reporting(E_ALL ^ E_NOTICE);
	
	@set_time_limit(0);
	@ignore_user_abort(true);

	/*
	echo '<pre>';
	print_r($_GET);
	//*/
	
	$plugin_file=$_GET['plugin_file'];
	$plugin_url=$_GET['plugin_url'];
	
	//Download the page at Wordpress.org Extend
	$plugin_page=$ws_pup->download_page($plugin_url);
	if (!$plugin_page) die("Couldn't load $plugin_page !");
	
	//Get the .zip download link from the page
	if (!preg_match('/<a\s+href=[\'"](http:\/\/downloads\.wordpress\.org\/plugin\/[\w\-\.]+\.zip)[\'"][^><]?>/', $plugin_page, $matches)){
		die('Error : Download link not found.');
	};
	$download_url=$matches[1];
	
	//Deactivate the plugin (if active)
	if ( get_option('active_plugins') ) $current_plugins = get_option('active_plugins');
	if (!empty($current_plugins) && in_array($plugin_file, $current_plugins)) {
		$was_active = true;
		$ws_pup->deActivatePlugin($plugin_file);
	} else {
		$was_active = false;
	};
	
	//Download the new version (a ZIP file)
	$zipdata = $ws_pup->download_page($download_url, 600);
	if(!$zipdata) die("Error : couldn't download the new version from '$download_url'!");

	//Save to a temporary location
	$zipfile = tempnam("/tmp", "PLG");
	$handle = fopen($zipfile, "w");
	fwrite($handle, $zipdata);
	fclose($handle);
	unset($zipdata);
	
	//Extract plgin files to the 'plugins' folder
	$ws_pup->extractPlugin($zipfile) 
		or die("Error : couldn't unzip the new version of the plugin.<br/>
		Your server may not have ziplib installed <b>and</b> the unzip command doesn't work either.");
	
	//Delete the temporary file
	unlink($zipfile);
		
	//Get activation URL for the plugin if necessary
	if ($was_active) {
		$redirect = get_option('siteurl')."/wp-admin/".wp_nonce_url("plugins.php?action=activate&plugin=$plugin_file", 'activate-plugin_' . $plugin_file);
		$redirect = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $redirect);
	} else {
		$redirect = get_option('siteurl').'/wp-admin/plugins.php';
	}
	
	//Go back to the plugin tab or activation URL
	//die("\nGo to $redirect");
	header("Location: $redirect");
?>