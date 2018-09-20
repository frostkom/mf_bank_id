<?php

class mf_bank_id
{
	function __construct(){}

	function admin_init()
	{
		global $pagenow;

		if($pagenow != 'profile.php' && get_option('setting_bank_id_activate') == 'yes' && get_the_author_meta('profile_ssn', get_current_user_id()) == '')
		{
			$profile_redirect = admin_url('profile.php');

			mf_redirect($profile_redirect);
		}
	}

	function filter_ssn($in)
	{
		$out = '';

		if($in != '')
		{
			$out = str_replace("-", "", $in);

			switch(strlen($out))
			{
				case 12:
					//Do nothing
				break;

				case 10:
					$out = (substr($out, 0, 2) > date("y") ? 19 : 20).$out;
				break;

				default:
					$out = '';
				break;
			}
		}

		return $out;
	}

	function get_message($action = '')
	{
		switch($action)
		{
			case 'RFA1':
			case 'RFA18':
			case 'OUTSTANDING_TRANSACTION':
			case 'NO_CLIENT':
				$out = __("Start your BankID app", 'lang_bank_id');
			break;

			case 'RFA2':
				$out = __("The BankID app is not installed. Please contact your internet bank.", 'lang_bank_id');
			break;

			case 'RFA3':
			case 'ALREADY_IN_PROGRESS':
				$out = __("Already in Progress. Wait a few seconds and then try again.", 'lang_bank_id');
			break;

			case 'RFA3':
			case 'CANCELLED':
				$out = __("Action cancelled. Please try again.", 'lang_bank_id');
			break;

			case 'RFA5':
			case 'RETRY':
			case 'INTERNAL_ERROR':
				$out = __("Internal error. Please try again.", 'lang_bank_id');
			break;

			case 'RFA6':
			case 'USER_CANCEL':
				$out = __("Action cancelled", 'lang_bank_id');
			break;

			case 'RFA8':
			case 'EXPIRED_TRANSACTION':
				$out = __("The BankID app is not responding. Please check that the program is started and that you have internet access. If you don’t have a valid BankID you can get one from your bank. Try again.", 'lang_bank_id');
			break;

			case 'RFA9':
			case 'USER_SIGN':
				$out = __("Enter your security code in the BankID app and select Identify or Sign.", 'lang_bank_id');
			break;

			case 'RFA12':
			case 'CLIENT_ERR':
				$out = __("Internal error. Update your BankID app and try again.", 'lang_bank_id');
			break;

			case 'RFA13':
			case 'OUTSTANDING_TRANSACTION':
				$out = __("Trying to start your BankID app.", 'lang_bank_id');
			break;

			case 'RFA14(A)':
			case 'RFA14(B)':
			case 'RFA15(A)':
			case 'RFA15(B)':
			case 'STARTED':
				$out = __("Searching for BankID:s, it may take a little while&hellip;", 'lang_bank_id'); //If a few seconds have passed and still no BankID has been found, you probably don’t have a BankID which can be used for this login/signature on this computer. If you have a BankID card, please insert it into your card reader. If you don’t have a BankID you can order one from your internet bank. If you have a BankID on another device you can start the BankID app on that device.
			break;

			case 'RFA16':
			case 'CERTIFICATE_ERR':
				$out = __("The BankID you are trying to use is revoked or too old. Please use another BankID or order a new one from your internet bank.", 'lang_bank_id');
			break;

			case 'RFA17':
			case 'START_FAILED':
				$out = sprintf(__("The BankID app couldn't be found on your computer or mobile device. Please install it and order a BankID from your internet bank. Install the app from %s.", 'lang_bank_id'), "<a href='//install.bankid.com'>install.bankid.com</a>");
			break;

			case 'RFA19':
				$out = __("Would you like to login or sign with a BankID on this computer or with a Mobile BankID?", 'lang_bank_id');
			break;

			case 'RFA20':
				$out = __("Would you like to login or sign with a BankID on this device or with a BankID on another device?", 'lang_bank_id');
			break;

			default:
				$out = __("Unknown Action", 'lang_bank_id')." (".$action.")";
			break;
		}

		return $out;
	}

	function user_exists($in)
	{
		global $wpdb;

		if($in != '')
		{
			$this->user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM ".$wpdb->users." INNER JOIN ".$wpdb->usermeta." ON ".$wpdb->users.".ID = ".$wpdb->usermeta.".user_id WHERE meta_key = 'profile_ssn' AND meta_value = %s", $in));

			//do_log($this->user_login." exists because profile_ssn = ".$in);

			return ($this->user_login != '');
		}

		else
		{
			//do_log("Does not exist because empty");

			return false;
		}
	}

	/* The same as used in Custom Login*/
	function login($username)
	{
		if(is_user_logged_in())
		{
			wp_logout();
		}

		add_filter('authenticate', array($this, 'allow_programmatic_login'), 10, 3); // hook in earlier than other callbacks to short-circuit them
		$user = wp_signon(array('user_login' => $username, 'remember' => true));
		remove_filter('authenticate', array($this, 'allow_programmatic_login'), 10);

		if(is_a($user, 'WP_User'))
		{
			//wp_clear_auth_cookie();
			wp_set_current_user($user->ID);
			//wp_set_auth_cookie($user->ID, true);

			if(is_user_logged_in())
			{
				return true;
			}
		}

		return false;
	}

	function allow_programmatic_login($user, $username, $password)
	{
		return get_user_by('login', $username);
	}

	function settings_bank_id()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_bank_id_certificate' => __("Certificate File", 'lang_bank_id'),
			);

			if(get_site_option('setting_bank_id_certificate') != '')
			{
				$arr_settings['setting_bank_id_activate'] = __("Activate", 'lang_bank_id');

				if(get_option('setting_bank_id_activate') == 'yes')
				{
					$arr_settings['setting_bank_id_disable_default_login'] = __("Disable Login with Username", 'lang_bank_id');
					$arr_settings['setting_bank_id_test_mode'] = __("Use Test Mode", 'lang_bank_id');
					$arr_settings['setting_bank_id_v2'] = __("Use Login v2", 'lang_bank_id');
				}
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_bank_id_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("BankID", 'lang_bank_id'));
	}

	function setting_bank_id_certificate_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		$description = '';

		if($option == '')
		{
			$description = sprintf(__("The file should be a %s file.", 'lang_bank_id'), ".pem")
				." <a href='//bankid.com/kontakt/foeretag/saeljare'>".__("Get yours here", 'lang_bank_id')."</a>";
		}

		echo get_media_library(array('name' => $setting_key, 'value' => $option, 'type' => 'file', 'description' => $description));
	}

	function setting_bank_id_activate_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function setting_bank_id_test_mode_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function setting_bank_id_disable_default_login_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function setting_bank_id_v2_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function upload_mimes($existing_mimes = array())
	{
		$existing_mimes['pem'] = 'application/x-pem-file';

		return $existing_mimes;
	}

	function manage_users_columns($cols)
	{
		unset($cols['posts']);

		$cols['profile_ssn'] = __("Social Security Number", 'lang_bank_id');

		return $cols;
	}

	function manage_users_custom_column($value, $col, $id)
	{
		switch($col)
		{
			case 'profile_ssn':
				$post_meta = get_the_author_meta($col, $id);

				if($post_meta != '')
				{
					return substr($post_meta, 0, 8)."&hellip;";
				}
			break;
		}

		return $value;
	}

	function admin_notices()
	{
		global $pagenow, $error_text;

		if($pagenow == 'profile.php' && get_option('setting_bank_id_activate') == 'yes' && get_the_author_meta('profile_ssn', get_current_user_id()) == '')
		{
			if(get_option('setting_bank_id_disable_default_login') == 'yes')
			{
				$error_text = __("You have to enter your Social Security Number to be able to login in the future", 'lang_bank_id');
			}

			else
			{
				$error_text = __("You have to enter your Social Security Number", 'lang_bank_id');
			}

			echo get_notification();
		}
	}

	function show_user_profile($user)
	{
		if(get_site_option('setting_bank_id_certificate') != '')
		{
			$out = "";

			$meta_key = 'profile_ssn';
			$meta_value = get_the_author_meta($meta_key, $user->ID);
			$meta_text = __("Social Security Number", 'lang_bank_id');

			$out .= "<tr class='".str_replace("_", "-", $meta_key)."-wrap'>
				<th><label for='".$meta_key."'>".$meta_text."</label></th>
				<td>".show_textfield(array('name' => $meta_key, 'value' => $meta_value, 'xtra' => "class='regular-text' maxlength='12' required"))."</td>
			</tr>";

			if($out != '')
			{
				echo "<table class='form-table'>".$out."</table>";
			}
		}
	}

	function personal_options_update($user_id)
	{
		if(get_site_option('setting_bank_id_certificate') != '')
		{
			if(current_user_can('edit_user', $user_id))
			{
				$this->user_register($user_id);
			}
		}
	}

	function register_form()
	{
		if(get_site_option('setting_bank_id_certificate') != '')
		{
			$meta_key = 'profile_ssn';
			$meta_value = check_var($meta_key);
			$meta_text = __("Social Security Number", 'lang_bank_id');

			echo "<p>
				<label for='".$meta_key."'>".$meta_text."</label><br>
				<input type='text' name='".$meta_key."' value='".$meta_value."' class='regular-text' maxlength='12' required>
			</p>";
		}
	}

	function user_register($user_id, $password = '', $meta = array())
	{
		global $wpdb;

		if(get_site_option('setting_bank_id_certificate') != '')
		{
			$meta_key = 'profile_ssn';
			$meta_value = $this->filter_ssn(check_var($meta_key));

			$user_id_temp = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM ".$wpdb->usermeta." WHERE user_id != '%d' AND meta_key = %s AND meta_value = %s", $user_id, $meta_key, $meta_value));

			if($user_id_temp > 0)
			{
				//do_log(__("The user %s tried to enter a social security number that %s already has", 'lang_bank_id'), get_user_info(array('id' => $user_id)), get_user_info(array('id' => $user_id_temp)));

				$meta_value = '';
			}

			update_user_meta($user_id, $meta_key, $meta_value);
		}
	}

	function login_init()
	{
		if(get_option('setting_bank_id_activate') == 'yes')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			$setting_bank_id_disable_default_login = isset($_GET['allow_default_login']) ? false : get_option('setting_bank_id_disable_default_login');

			mf_enqueue_style('style_bank_id', $plugin_include_url."style.css", $plugin_version);
			mf_enqueue_script('script_bank_id', $plugin_include_url."script.js", array('bank_id_v2' => (get_option('setting_bank_id_v2') == 'yes' ? 'yes' : 'no'), 'plugin_url' => $plugin_include_url, 'disable_default_login' => $setting_bank_id_disable_default_login, 'open_bank_id_application_text' => __("I'm trying to open the BankID Application. If it doesn't open automatically you have to do it manually", 'lang_bank_id'), 'took_too_long_text' => __("The login took too long. Please try again", 'lang_bank_id')), $plugin_version);
		}
	}

	function login_form()
	{
		if(get_option('setting_bank_id_activate') == 'yes')
		{
			$setting_bank_id_disable_default_login = isset($_GET['allow_default_login']) ? false : get_option('setting_bank_id_disable_default_login');

			echo "<p class='login_or'><label>".__("or", 'lang_bank_id')."</label></p>
			<div id='login_fields'>"
				.show_textfield(array('custom_tag' => 'p', 'name' => 'user_ssn', 'text' => __("BankID", 'lang_bank_id')." <a href='//support.bankid.com/sv'>(".__("Get BankID", 'lang_bank_id').")</a>", 'required' => ($setting_bank_id_disable_default_login == 'yes'), 'placeholder' => __("Social Security Number", 'lang_bank_id'), 'xtra' => "class='input' autocomplete='off'"))
			."</div>
			<div id='login_loading' class='hide'><i class='fa fa-spinner fa-spin fa-3x'></i></div>
			<div id='notification' class='hide'></div>";
		}
	}
}