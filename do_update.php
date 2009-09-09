<?php
/*
	Update the plugin(s)
*/
	/* 
	Note: This script must be able to execute up to the plugin reactivation block 
	without the OCPU plugin itself being active. This is because OCPU will be deactivated
	when it updates itself along with other plugins (via "Upgrade All") and thus won't be available
	in this script.
	*/

	//Let the main plugin file know it must load even though is_admin() will be false.
	define('MUST_LOAD_OCPU', true);

	//Load the WordPress core & admin backend
	require_once("../../../wp-config.php");
	require_once(ABSPATH . "/wp-admin/admin.php");
	
	/**
	 * Get the main parameters
	 */
	$nonce = empty($_GET['_wpnonce'])?'':$_GET['_wpnonce'];
	$action = empty($_GET['action'])?'update_plugin':$_GET['action'];
	$plugin_file = empty($_GET['plugin_file'])?'':$_GET['plugin_file'];
	$plugin_url = empty($_GET['plugin_url'])?'':$_GET['plugin_url'];
	$download_url = empty($_GET['download_url'])?'':$_GET['download_url'];
	$theme = empty($_GET['theme'])?'':$_GET['theme'];
	
	/**
	 * Set general execution options
	 */
	if ( isset($ws_pup) && $ws_pup->debug ) {
		error_reporting(E_ALL);
		$ws_pup->dprint("Error reporting set to E_ALL.");
	};	
	
	@set_time_limit(0);
	@ignore_user_abort(true);
	
	/**
	 * Determine which directories need to be writable and what user permissions are required
	 */
	 
	//Which directory do I need to check?
	$check_dir = '';
	$plugin_dir = realpath( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR); 
	//It seems that on some systems realpath() will strip out the last slash, so I'll add it here. 
	if ( (substr($plugin_dir,-1) != DIRECTORY_SEPARATOR) && (substr($plugin_dir,-1)!='\\') ){
		$plugin_dir .= DIRECTORY_SEPARATOR;
	}
	
	if (isset($ws_pup)) $ws_pup->dprint("Plugin directory is '$plugin_dir'",0);
	$theme_dir = get_theme_root() . DIRECTORY_SEPARATOR;
	 
	$what = 'thing'; //used in error messages - theme or plugin
	$required_capability = 'edit_plugins'; //permission check later
	if ( in_array($action, array('upgrade_all', 'update_plugin', 'delete_plugin', 'reactivate_all')) ){
		$check_dir = $plugin_dir;
		$what = 'plugin';
		$required_capability = 'edit_plugins';
		
	} else if ( in_array($action, array('delete_theme')) ){
		$check_dir = $theme_dir;
		$what = 'theme';
		$required_capability = 'edit_themes'; 
	}
	
	/**
	 * Permission check
	 */
	if(!current_user_can($required_capability)) {
		wp_die("Oops, sorry, you are not authorized to fiddle with {$what}s!", 'Access Denied');
	}
		
	/**
	 * Handle reactivation (doesn't require nonce verification)
	 */
	if ($action == 'reactivate_all'){
		
		//User needs "edit_plugins" privileges to do this
		if(!current_user_can('edit_plugins')) {
			wp_die('Oops, sorry, you are not authorized to fiddle with plugins!');
		}
		
		$to_activate = get_option('plugins_to_reactivate');
		if ( !is_array($to_activate) || (count($to_activate)<1) ){
			//Nothing left to do!
			wp_redirect(get_option('siteurl').'/wp-admin/plugins.php?activate=true');
			die();
		}
		
		//If there is only one plugin to activate it can be done using the normal WP mechanism. 
		if (count($to_activate)==1){
			$redirect = get_option('siteurl')."/wp-admin/" 
					.wp_nonce_url("plugins.php?action=activate&plugin=$plugin_file", 
					'activate-plugin_' . $to_activate[0]);
			//stupid wp_nonce_ulr, escaping ampersands!
			$redirect = html_entity_decode($redirect);
			header("Location: $redirect");
			die();
		}
		
		//Redirect to this URL if a plugin crashes on activation
		$continue_url = get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__)).
		 				'/do_update.php?action=reactivate_all';
		
		//Activate every plugin, more or less safely
		wp_redirect($continue_url);
		$remaining = $to_activate;
		foreach($to_activate as $key => $plugin_file){
			unset($remaining[$key]);
			update_option('plugins_to_reactivate', $remaining);
			activate_plugin($plugin_file);
		}
		wp_redirect(get_option('siteurl').'/wp-admin/plugins.php?activate=true');
		die();
	}
	
	/**
	 * Check if the target directory is writable by PHP
	 */
	if (!empty($check_dir)) {
		$ws_pup->dprint("Checking to see if $check_dir is writable.");
		
		if (!$ws_pup->is__writable($check_dir)){
			wp_die("The directory $check_dir is not writable by Wordpress.<br/>
			You may need to assign permissions 666, 755 or even 777 to your \"{$what}s\" directory
			(depending on your server configuration). For more information on what file permissions are and
			how to change them read 
			<a href='http://www.interspire.com/content/articles/12/1/FTP-and-Understanding-File-Permissions'>Understanding file permisssions</a>.","Plugin Folder is Not Writable");
		} else {
			$ws_pup->dprint('Okay.');
		}
	}
	
	/**
	 * Nonce verification (should do this earlier?)
	 */
	if ($action == 'update_plugin'){
		$nonce_action = 'update_plugin-'.$plugin_file;
	} else {
		$nonce_action = $action;
	}
	 
	if (!wp_verify_nonce($nonce, $nonce_action)){
		$ws_pup->dprint("Nonce verification failed.", 3);
		wp_die("I can't do this because the link you used doesn't appear to be legitimate.", 
			"Nonce verification failed");
	} else {
		$ws_pup->dprint("Nonce verification passed.", 0);
	}

	/**
	 * Perform the $action
	 */	
	 
	$upgrades = array();
//de-indented for better readability
switch ($action){
	/**
	 * Update all plugins 
	 */
	case "upgrade_all":
		$update =  $ws_pup->get_update_plugins();
		if (isset($update->response) && is_array($update->response)){
			foreach($update->response as $file => $info){
				if (!empty($info->package))
					$upgrades[$file] = $info->package;
			}
		}
		
		//... and let it fall through to the next option
		
	/**
	 * Update a plugin 
	 */
	case "update_plugin":
		if ($plugin_file){
			$upgrades[$plugin_file] = $download_url;
		}
		
		if (count($upgrades)<1) wp_die("No plugins to upgrade!", "Installer Error");
		
		$ws_pup->dprint("About to upgrade ".count($upgrades)." plugins.",1);
	
		$errors = array();
		$to_activate = array();
		
		/**
		 * Perform all requested upgrades
		 */
		foreach($upgrades as $plugin_file => $download_url){
			$ws_pup->dprint("Upgrading '$plugin_file', download URL is '$download_url'.",1);
			
			$was_active = false;
			//Deactivate the plugin (if active)
			if (in_array($plugin_file, get_option('active_plugins'))){
				$ws_pup->dprint("The plugin that needs to be upgraded is active. Deactivating.",1);
				$was_active = true;
				$ws_pup->deActivatePlugin($plugin_file);
			} else {
				$ws_pup->dprint("The plugin that needs to be upgraded is not active. Good.");
				$was_active = false;
			}
			//Download and install
			$plugin_info = $ws_pup->do_install($download_url,'', 'plugin');
			if (is_wp_error($plugin_info)){
				$errors[$plugin_file] = $plugin_info;
			} else {
				if (!empty($plugin_info['plugin_file'])) $plugin_file = $plugin_info['plugin_file'];  
				//Store the plugin for activation
				if ($was_active) {
					$ws_pup->dprint("Upgraded plugin was active. It will be reactivated.",1);
					$to_activate[] = $plugin_file;
				}
			}
		}
		// Force refresh of plugin update information
		if ( function_exists('delete_transient') ){
			delete_transient('update_plugins');
		}
		delete_option('update_plugins');
		
		
		$ws_pup->dprint("Main loop finished.");
		/**
		 * Display errors, if any.
		 */
		if (count($errors)>0){
			$message = "Errors occured during upgrade. <br/>";
			foreach ($errors as $plugin_file => $error){
				$message .= "<strong>While upgrading $plugin_file : </strong><br/>";
				$message = "<p>".implode("\n<br />",$error->get_error_messages())."</p>";
			}
			$message .= "<p><strong>The full installation log is below : </strong></p>";
			$message .= $ws_pup->format_debug_log();
			
			wp_die($message, "Installer Error");
			
		} else {
			/**
			 * Redirect back to the plugin tab (sorry, no nice messages here!)
			 */
			if (count($to_activate)>0){
				//Move on to reactivation
				if (count($to_activate)==1){
					//We can reactivate a single plugin right away.
					$plugin_file = $to_activate[0];
					$redirect = get_option('siteurl')."/wp-admin/" 
						.wp_nonce_url("plugins.php?action=activate&plugin=$plugin_file", 
						'activate-plugin_' . $plugin_file);
					//stupid wp_nonce_ulr, escaping ampersands!
					$redirect = html_entity_decode($redirect);
				} else {
					//Or handle multiple plugins - more complex.
					update_option('plugins_to_reactivate', $to_activate);
					$redirect=get_option('siteurl').'/wp-content/plugins/'.$ws_pup->myfolder.
			 			'/do_update.php?action=reactivate_all';
			 	}
				
			} else {
				$redirect = get_option('siteurl')."/wp-admin/plugins.php";
			}
	
			$ws_pup->dprint("Should redirect to $redirect");
			if (!$ws_pup->debug) {
				header("Location: $redirect");
			} else {
				$ws_pup->dprint("(Debug version = redirection will not happen. Script execution finished.)",1);
			};
		}	
		
		die();
		
		break; //Hmm, it's dead already.
	
	/**
	 * Delete a plugin
	 */
	case "delete_plugin":
		$ws_pup->dprint("About to delete the plugin '$plugin_file'", 1);
		if (empty($plugin_file)){
			wp_die("Invalid request - no plugin specified.", "Boring Error");
		}
		$parts = preg_split('/[\\/]/', $plugin_file);
		$parts = array_filter($parts);
		if (count($parts)>1){
			//the plugin is in a subfolder, so kill the folder
			$directory = $plugin_dir . $parts[0];
			$ws_pup->dprint("Deleting directory '$directory'...",1);
			if (!$ws_pup->deltree($directory)){
				wp_die("Can't delete the directory <strong>$parts[0]</strong><br/>Log :<br/>".
					$ws_pup->format_debug_log(), "File Access Error");
			}
		} else {
			//it seems to be a single file inside wp-content/plugins
			$ws_pup->dprint("Deleting file '$plugin_file'",1);
			if (!unlink($plugin_dir . $plugin_file)){
				//error!
				$ws_pup->dprint("Failed.", 3);
				wp_die("Can't delete <strong>$plugin_file</strong><br/>Log : <br/>".
					$ws_pup->format_debug_log(), "File Access Error");
			};
			$ws_pup->dprint("File removed OK.",1);
		}
		//remove the deleted plugin from the list of updates (if present)
		$update = $ws_pup->get_update_plugins();
		if (!empty($update->response) && isset($update->response[$plugin_file])){
			$ws_pup->dprint("Removing an update notification for this plugin.",1);
			unset($update->response[$plugin_file]);
			$ws_pup->set_update_plugins( $update );
		}
		
		if (!$ws_pup->debug){
			wp_redirect(get_option('siteurl').'/wp-admin/plugins.php');
		}

		break;
		
	/**
	 * Delete a theme
	 */
	case "delete_theme":
		$ws_pup->dprint("About to delete the theme '$theme'", 1);
		if (empty($theme)){
			wp_die("Invalid request - no theme specified.", "Boring Error");
		}
		//$theme is a directory name under wp-content/themes (usually)
		$directory = $theme_dir . $theme;
		$ws_pup->dprint("Deleting directory '$directory'...",1);
		if (!$ws_pup->deltree($directory)){
			wp_die("Can't delete the theme <strong>$theme</strong><br/>Log :<br/>".
				$ws_pup->format_debug_log(), "File Access Error");
		} else {
			$ws_pup->dprint("Directory removed OK.",1);
		}
		
		if (!$ws_pup->debug){
			wp_redirect(get_option('siteurl').'/wp-admin/themes.php');
		}
	
		break;
		
	default: 
		wp_die("Invalid parameters (action = '$action'). Didn't do anything much.", "Installer Error");
}

	//That is all.
	$ws_pup->dprint('Script finished.');

?>