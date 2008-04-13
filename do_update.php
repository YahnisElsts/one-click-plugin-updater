<?php
/*
	Update the plugin(s)
*/
	require_once("../../../wp-config.php");
	require_once(ABSPATH . "/wp-admin/admin.php");
	
	if(!current_user_can('edit_plugins')) {
		wp_die('Oops, sorry, you are not authorized to fiddle with plugins!');
	}

	/**
	 * Get the main parameters
	 */
	$nonce = empty($_GET['_wpnonce'])?'':$_GET['_wpnonce'];
	$action=empty($_GET['action'])?'update_plugin':$_GET['action'];
	
	/**
	 * Handle reactivation
	 */
	if ($action == 'reactivate_all'){
		$to_activate = get_option('plugins_to_reactivate');
		if ( !is_array($to_activate) || (count($to_activate)<1) ){
			//Nothing left to do!
			wp_redirect(get_option('siteurl').'/wp-admin/plugins.php?activate=true');
			die();
		}
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
		$continue_url = get_option('siteurl').'/wp-content/plugins/'.$ws_pup->myfolder.
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
	 * Handle plugin upgrades
	 */
	 
	//check if it's possible to write to plugin directory
	$plugin_dir=ABSPATH.'wp-content/plugins/';
	$ws_pup->dprint("Checking to see if $plugin_dir is writable.");
	
	if (!$ws_pup->is__writable($plugin_dir)){
		wp_die("The directory $plugin_dir; is not writable by Wordpress.<br/>
		You may need to assign permissions 666, 755 or even 777 to your \"plugins\" directory
		(depending on your server configuration). For more information on what file permissions are and
		how to change them read 
		<a href='http://www.interspire.com/content/articles/12/1/FTP-and-Understanding-File-Permissions'>Understanding file permisssions</a>.","Plugin Folder is Not Writable");
	} else {
		$ws_pup->dprint('Okay.');
	}
	
	if ($ws_pup->debug) {
		error_reporting(E_ALL);
		$ws_pup->dprint("Error reporting set to E_ALL.");
	};	
	
	@set_time_limit(0);
	@ignore_user_abort(true);

	$plugin_file=empty($_GET['plugin_file'])?'':$_GET['plugin_file'];
	$plugin_url=empty($_GET['plugin_url'])?'':$_GET['plugin_url'];
	$download_url = empty($_GET['download_url'])?'':$_GET['download_url'];
	
	$upgrades = array();
	if ($action == 'upgrade_all'){
		$update = get_option('update_plugins');
		if (is_array($update->response)){
			foreach($update->response as $file => $info){
				if (!empty($info->package))
					$upgrades[$file] = $info->package;
			}
		}
		
		$nonce_action = $action;
	} else {
		$upgrades[$plugin_file] = $download_url;
		$nonce_action = 'update_plugin-'.$plugin_file;
	}
	
	/**
	 * Nonce verification (should do this earlier?)
	 */
	if (!wp_verify_nonce($nonce, $nonce_action)){
		$ws_pup->dprint("Nonce verification failed.", 3);
		wp_die("I can't upgrade the plugin(s) because the link you used request doesn't appear to be legitimate.", 
			"Nonce verification failed");
	} else {
		$ws_pup->dprint("Nonce verification passed.", 0);
	}
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
			$ws_pup->dprint("(Debug version = redirection will not happen. Script execution finished.)");
		};
	}
?>