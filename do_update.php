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
	$ws_pup->dprint("Checking to see if $plugin_dir is writable.");
	
	if (!$ws_pup->is__writable($plugin_dir)){
		?>
		<b>Error : the directory <?php echo $plugin_dir; ?> is not writable by Wordpress.</b><br/>
		You may need to assign permissions 666, 755 or even 777 to your "plugins" directory
		(depending on your server configuration). For more information on what file permissions are and
		how to change them read 
		<a href='http://www.interspire.com/content/articles/12/1/FTP-and-Understanding-File-Permissions'>Understanding file permisssions</a>.
		<?php
		die();
	} else {
		$ws_pup->dprint('Okay.');
	}
	
	$ws_pup->dprint('Loading PclZip...');
	if (!class_exists('PclZip'))
	{
	    require_once ('pclzip.lib.php');
	    $ws_pup->dprint('Okay.');
	}
	
	if ($ws_pup->debug) {
		error_reporting(E_ALL);
		$ws_pup->dprint("Error reporting set to E_ALL.");
	};	
	
	@set_time_limit(0);
	@ignore_user_abort(true);

	/*
	echo '<pre>';
	print_r($_GET);
	//*/
	
	$plugin_file=$_GET['plugin_file'];
	$plugin_url=$_GET['plugin_url'];
	$ws_pup->dprint("Will update plugin '$plugin_file'. Plugin website : '$plugin_url'");
	
	$ws_pup->dprint("Accessing the above page to find new version's download URL.");
	//Download the page at Wordpress.org Extend
	$plugin_page=$ws_pup->download_page($plugin_url);
	if (!$plugin_page) die("Error: Couldn't load URL '$plugin_page' !<br/>".
		"You need either the CURL library installed or allow_url_fopen set in php.ini for this to work.");
	$ws_pup->dprint("Page loaded, ".strlen($plugin_page)." bytes.");
		
	//Get the .zip download link from the page
	if (!preg_match('/<a\s+href=[\'"](http:\/\/downloads\.wordpress\.org\/plugin\/[\w\-\.]+\.zip)[\'"][^><]?>/', $plugin_page, $matches)){
		die("Error : Download link not found on $plugin_page.");
	};
	$download_url=$matches[1];
	$ws_pup->dprint("Found plugin download URL : '$download_url'");
	
	//Deactivate the plugin (if active)
	if ( get_option('active_plugins') ) $current_plugins = get_option('active_plugins');
	if (!empty($current_plugins) && in_array($plugin_file, $current_plugins)) {
		$ws_pup->dprint("The plugin that needs to be upgraded is active. Deactivating.");
		$was_active = true;
		$ws_pup->deActivatePlugin($plugin_file);
	} else {
		$ws_pup->dprint("The plugin that needs to be upgraded is not active. Good.");
		$was_active = false;
	};
	
	//Download the new version (a ZIP file)
	$ws_pup->dprint("Downloading new version...");
	$zipdata = $ws_pup->download_page($download_url, 600);
	if(!$zipdata) die("Error : couldn't download the new version from '$download_url'!<br/>".
		"You need either the CURL library installed or allow_url_fopen set in php.ini for this to work.");
	$ws_pup->dprint("Okay, ".strlen($zipdata)." bytes downloaded.");	
	
	//Save to a temporary location
	$zipfile = tempnam("/tmp", "PLG");
	$ws_pup->dprint("Will save the new version archive (zip) to a temporary file '$zipfile'.");
	$handle = fopen($zipfile, "w");
	if(!$handle) die("Error : couldn't create a temporary file.");
	fwrite($handle, $zipdata);
	fclose($handle);
	unset($zipdata);
	
	$ws_pup->dprint("About to extract the new version.");
	//Extract plugin files to the 'plugins' folder
	$ws_pup->extractPlugin($zipfile) 
		or die("Error : couldn't unzip the new version of the plugin.<br/>
		Your server may not have ziplib installed <b>and</b> the unzip command doesn't work either.");
	
	$ws_pup->dprint("Plugin extracted, deleting temporary file '$zipfile'");
	//Delete the temporary file
	@unlink($zipfile);
		
	//Get activation URL for the plugin if necessary
	if ($was_active) {
		$ws_pup->dprint("Upgraded plugin was active. It will be reactivated by redirecting to the appropriate URL.");
		$redirect = get_option('siteurl')."/wp-admin/".wp_nonce_url("plugins.php?action=activate&plugin=$plugin_file", 'activate-plugin_' . $plugin_file);
		$redirect = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $redirect);
	} else {
		$ws_pup->dprint("Upgraded plugin was not active. Will redirect back to plugin list.");
		$redirect = get_option('siteurl').'/wp-admin/plugins.php';
	}
	
	//Go back to the plugin tab or activation URL
	//die("\nGo to $redirect");
	$ws_pup->dprint("Shoould redirect to $redirect");
	$ws_pup->dprint("(Debug version - redirection will not happen. Script execution finished.)");
	if (!$ws_pup->debug) {
		header("Location: $redirect");
	};
	die();
?>