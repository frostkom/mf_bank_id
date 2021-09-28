<?php

class mf_bank_id
{
	function __construct()
	{
		$this->meta_prefix = 'mf_bank_id_';
	}

	function get_login_methods_for_select()
	{
		return array(
			'username' => __("Username", 'lang_bank_id'),
			'ssc' => __("Social Security Number", 'lang_bank_id'),
			'qr' => __("QR Code", 'lang_bank_id'),
			'connected' => __("Same Device", 'lang_bank_id'),
		);
	}

	function get_api_modes_for_select()
	{
		return array(
			'test' => __("Test", 'lang_bank_id'),
			'live' => __("Live", 'lang_bank_id'),
		);
	}

	function cron_base()
	{
		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
			$option_bank_id_certificate = get_site_option('option_bank_id_certificate');
			$setting_bank_id_activate = get_option('setting_bank_id_activate');

			if($setting_bank_id_certificate == '' && $option_bank_id_certificate != '' && $setting_bank_id_activate == 'yes')
			{
				do_log("The backup (option_bank_id_certificate: ".$option_bank_id_certificate.") was set but the setting in use (setting_bank_id_certificate) was empty even though the site was using BankID (setting_bank_id_activate: ".$setting_bank_id_activate.")");

				//update_site_option('setting_bank_id_certificate', $option_bank_id_certificate);
			}
		}

		$obj_cron->end();
	}

	function settings_bank_id()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array();

			if(get_site_option('setting_bank_id_certificate') == '' || get_option('setting_bank_id_activate') != 'yes')
			{
				$arr_settings['setting_bank_id_certificate'] = __("Certificate File", 'lang_bank_id');
			}

			if(get_site_option('setting_bank_id_certificate') != '')
			{
				$arr_settings['setting_bank_id_activate'] = __("Activate", 'lang_bank_id');

				if(get_option('setting_bank_id_activate') == 'yes')
				{
					$arr_settings['setting_bank_id_login_methods'] = __("Login Methods", 'lang_bank_id');
					$arr_settings['setting_bank_id_api_mode'] = __("API Mode", 'lang_bank_id');
				}
			}

			else
			{
				delete_option('setting_bank_id_activate');
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_bank_id_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, "BankID");
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

		echo get_media_library(array('name' => $setting_key, 'value' => $option, 'description' => $description));

		if(get_option('setting_bank_id_activate') == 'yes')
		{
			update_site_option('option_bank_id_certificate', $option);
		}
	}

	function setting_bank_id_activate_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		$setting_bank_id_certificate = str_replace(WP_CONTENT_URL, "", get_site_option('setting_bank_id_certificate'));

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => sprintf(__("Use the certificate file %s", 'lang_bank_id'), $setting_bank_id_certificate)));
	}

	function setting_bank_id_login_methods_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, array());

		echo show_select(array('data' => $this->get_login_methods_for_select(), 'name' => $setting_key."[]", 'value' => $option));
	}

	function setting_bank_id_api_mode_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'live');

		echo show_select(array('data' => $this->get_api_modes_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function admin_init()
	{
		global $pagenow;

		if($pagenow != 'profile.php' && get_option('setting_bank_id_activate') == 'yes' && get_the_author_meta('profile_ssn', get_current_user_id()) == '')
		{
			$profile_redirect = admin_url("profile.php");

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
					//$out = (substr($out, 0, 2) > date("y") ? 19 : 20).$out;

					// Just in case this outlives me :)
					$current_century = substr(date("Y"), 0, 2);
					$out = (substr($out, 0, 2) > date("y") ? ($current_century - 1) : $current_century).$out;
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
				$out = sprintf(__("Start your %s app", 'lang_bank_id'), "BankID");
			break;

			case 'RFA2':
				$out = sprintf(__("The %s app is not installed. Please contact your internet bank.", 'lang_bank_id'), "BankID");
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
				$out = sprintf(__("The %s app is not responding. Please check that the program is started and that you have internet access. If you do not have a valid %s you can get one from your bank. Try again.", 'lang_bank_id'), "BankID", "BankID");
			break;

			case 'RFA9':
			case 'USER_SIGN':
				$out = sprintf(__("Enter your security code in the %s app and select Identify or Sign.", 'lang_bank_id'), "BankID");
			break;

			case 'RFA12':
			case 'CLIENT_ERR':
				$out = sprintf(__("Internal error. Update your %s app and try again.", 'lang_bank_id'), "BankID");
			break;

			case 'RFA13':
			case 'OUTSTANDING_TRANSACTION':
				$out = sprintf(__("Trying to start your %s app", 'lang_bank_id'), "BankID");
			break;

			case 'RFA14(A)':
			case 'RFA14(B)':
			case 'RFA15(A)':
			case 'RFA15(B)':
			case 'STARTED':
				$out = sprintf(__("Searching for %s, it may take a little while...", 'lang_bank_id'), "BankID"); //If a few seconds have passed and still no BankID has been found, you probably don’t have a BankID which can be used for this login/signature on this computer. If you have a BankID card, please insert it into your card reader. If you don’t have a BankID you can order one from your internet bank. If you have a BankID on another device you can start the BankID app on that device.
			break;

			case 'RFA16':
			case 'CERTIFICATE_ERR':
				$out = sprintf(__("The %s you are trying to use is revoked or too old. Please use another %s or order a new one from your internet bank.", 'lang_bank_id'), "BankID", "BankID");
			break;

			case 'RFA17':
			case 'START_FAILED':
				$out = sprintf(__("The %s app could not be found on your computer or mobile device. Please install it and order a %s from your internet bank. Install the app from %s.", 'lang_bank_id'), "BankID", "BankID", "<a href='//install.bankid.com'>install.bankid.com</a>");
			break;

			case 'RFA19':
				$out = sprintf(__("Would you like to login or sign with a %s on this computer or with a Mobile %s?", 'lang_bank_id'), "BankID", "BankID");
			break;

			case 'RFA20':
				$out = sprintf(__("Would you like to login or sign with a %s on this device or with a %s on another device?", 'lang_bank_id'), "BankID", "BankID");
			break;

			default:
				$out = __("Unknown Action", 'lang_bank_id')." (".$action.")";
			break;
		}

		return $out;
	}

	function validate_and_login($data, &$json_output)
	{
		switch($data['type'])
		{
			default:
			case 'user':
				if($this->user_exists($data['ssn']))
				{
					if($this->login($this->user_login))
					{
						$json_output['success'] = 1;
						$json_output['msg'] = __("The validation was successful! You are being logged in...", 'lang_bank_id');
						$json_output['redirect'] = admin_url();
					}

					else
					{
						$json_output['error'] = 1;
						$json_output['msg'] = __("Something went wrong when trying to login. If the problem persists, please contact an admin.", 'lang_bank_id');
					}
				}

				else
				{
					$json_output['error'] = 1;
					$json_output['msg'] = __("The social security number that you are trying to login with is not connected to any user. Please login with you username and password, go to your Profile and add your social security number there.", 'lang_bank_id');
				}
			break;

			case 'address':
				if($this->address_exists($data['ssn']))
				{
					if($this->login_address())
					{
						$json_output['success'] = 1;
						$json_output['msg'] = __("The validation was successful! You are being logged in...", 'lang_bank_id');
						$json_output['redirect'] = wp_get_referer();
					}

					else
					{
						$json_output['error'] = 1;
						$json_output['msg'] = __("Something went wrong when trying to login. If the problem persists, please contact an admin.", 'lang_bank_id');
					}
				}

				else
				{
					$json_output['error'] = 1;
					$json_output['msg'] = __("The social security number that you are trying to login with is not connected to anyone. Please contact an admin.", 'lang_bank_id');
				}
			break;
		}
	}

	function user_exists($in)
	{
		global $wpdb;

		if($in != '')
		{
			$this->user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM ".$wpdb->users." INNER JOIN ".$wpdb->usermeta." ON ".$wpdb->users.".ID = ".$wpdb->usermeta.".user_id WHERE meta_key = %s AND meta_value = %s", 'profile_ssn', $in));

			return ($this->user_login != '');
		}

		else
		{
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
				$author_meta = get_the_author_meta($col, $id);

				if($author_meta != '')
				{
					return substr($author_meta, 0, 8)."&hellip;";
				}
			break;
		}

		return $value;
	}

	function rwmb_meta_boxes($meta_boxes)
	{
		if(is_plugin_active("mf_address/index.php"))
		{
			$meta_boxes[] = array(
				'id' => $this->meta_prefix.'settings',
				'title' => "BankID",
				'post_types' => array('page'),
				'context' => 'side',
				'priority' => 'low',
				'fields' => array(
					array(
						'name' => __("Activate", 'lang_bank_id'),
						'id' => $this->meta_prefix.'activate',
						'type' => 'select',
						'options' => get_yes_no_for_select(),
						'std' => 'no',
					),
				)
			);
		}

		return $meta_boxes;
	}

	function admin_notices()
	{
		global $pagenow, $error_text;

		if($pagenow == 'profile.php' && get_the_author_meta('profile_ssn', get_current_user_id()) == '')
		{
			if($this->allow_username_login() == false)
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

	function edit_user_profile($user)
	{
		$meta_key = 'profile_ssn';
		$meta_value = get_the_author_meta($meta_key, $user->ID);
		$meta_text = __("Social Security Number", 'lang_bank_id');

		echo "<table class='form-table'>
			<tr class='".str_replace("_", "-", $meta_key)."-wrap'>
				<th><label for='".$meta_key."'>".$meta_text."</label></th>
				<td>".show_textfield(array('name' => $meta_key, 'value' => $meta_value, 'placeholder' => __("YYMMDD-XXXX", 'lang_bank_id'), 'required' => true, 'xtra' => "class='regular-text' maxlength='12'"))."</td>" // required
			."</tr>
		</table>";
	}

	function profile_update($user_id)
	{
		if(current_user_can('edit_user', $user_id))
		{
			$this->user_register($user_id);
		}
	}

	function filter_theme_core_seo_type($seo_type)
	{
		global $post;

		if($seo_type == '')
		{
			$post_activate = get_post_meta($post->ID, $this->meta_prefix.'activate', true);

			if($post_activate == 'yes')
			{
				$seo_type = 'password_protected';
			}
		}

		return $seo_type;
	}

	function register_form()
	{
		$meta_key = 'profile_ssn';
		$meta_value = check_var($meta_key);
		$meta_text = __("Social Security Number", 'lang_bank_id');

		$post_id = apply_filters('get_widget_search', 'registration-widget');

		if($post_id > 0)
		{
			echo show_textfield(array('name' => $meta_key, 'text' => $meta_text, 'value' => $meta_value, 'placeholder' => __("YYMMDD-XXXX", 'lang_bank_id'), 'required' => true, 'xtra' => "maxlength='12'"));
		}

		else
		{
			echo "<p>
				<label for='".$meta_key."'>".$meta_text."</label><br>
				<input type='text' name='".$meta_key."' value='".$meta_value."' class='regular-text' placeholder='".__("YYMMDD-XXXX", 'lang_bank_id')."' maxlength='12' required>
			</p>";
		}
	}

	function user_register($user_id, $password = '', $meta = array())
	{
		global $wpdb;

		$meta_key = 'profile_ssn';
		$meta_value = $this->filter_ssn(check_var($meta_key));

		$user_id_temp = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM ".$wpdb->usermeta." WHERE user_id != '%d' AND meta_key = %s AND meta_value = %s", $user_id, $meta_key, $meta_value));

		if($user_id_temp > 0)
		{
			//do_log(sprintf("The user %s tried to enter a social security number that %s already has", get_user_info(array('id' => $user_id)), get_user_info(array('id' => $user_id_temp)));

			$meta_value = '';
		}

		update_user_meta($user_id, $meta_key, $meta_value);
	}

	function filter_profile_fields($arr_fields)
	{
		$arr_fields[] = array(
			'type' => 'text',
			'name' => 'profile_ssn',
			'text' => __("Social Security Number", 'lang_bank_id'),
			//'placeholder' => __("YYMMDD-XXXX", 'lang_bank_id'),
			'required' => true,
			'attributes' => " maxlength='12'",
		);

		return $arr_fields;
	}

	function filter_is_password_protected($is_protected, $data)
	{
		if($is_protected == false && get_post_meta($data['post_id'], $this->meta_prefix.'activate', true) == 'yes')
		{
			if($data['check_login'] == true && $this->is_address_logged_in())
			{
				$is_protected = false;
			}

			else
			{
				$is_protected = true;
			}
		}

		return $is_protected;
	}

	function address_exists($in)
	{
		global $wpdb;

		if($in != '')
		{
			$emlAddressEmail = $wpdb->get_var($wpdb->prepare("SELECT addressEmail FROM ".get_address_table_prefix()."address WHERE addressBirthDate = %s", $in));

			return ($emlAddressEmail != '');
		}

		else
		{
			return false;
		}
	}

	function login_address()
	{
		$cookie_name = $this->meta_prefix.COOKIEHASH;
		$cookie_value = 'address_ssn_'.$_SERVER['REMOTE_ADDR']; //$data['ssn']

		setcookie($cookie_name, md5($cookie_value), strtotime("+1 week"), COOKIEPATH);

		return true;
	}

	function is_address_logged_in()
	{
		$cookie_name = $this->meta_prefix.COOKIEHASH;
		$cookie_value = 'address_ssn_'.$_SERVER['REMOTE_ADDR']; //$data['ssn']

		$cookie_value_md5 = (isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '');

		return ($cookie_value_md5 == md5($cookie_value));
	}

	function the_content($html)
	{
		global $post;

		if(is_user_logged_in() == false && isset($post->ID) && apply_filters('filter_is_password_protected', false, array('post_id' => $post->ID, 'check_login' => true)) == true)
		{
			$this->login_init(array('login_type' => 'address'));

			$html = "<form id='loginform' class='mf_form' action='#' method='post'>
				<p>".__("To view the content on this page you have to first login.", 'lang_bank_id')."</p>"
				.$this->login_form(array('print' => false))
				."<div class='form_button'>"
					.show_button(array('name' => 'btnBankIDLogin', 'text' => __("Log in", 'lang_bank_id')))
				."</div>
			</form>";
		}

		return $html;
	}

	function allow_username_login()
	{
		if(isset($_GET['allow_default_login']))
		{
			return true;
		}

		else
		{
			$setting_bank_id_login_methods = get_option_or_default('setting_bank_id_login_methods', array());

			return (count($setting_bank_id_login_methods) == 0 || in_array('username', $setting_bank_id_login_methods));
		}
	}

	function login_init($data = array())
	{
		if(!is_array($data)){				$data = array();}
		if(!isset($data['login_type'])){	$data['login_type'] = 'user';}

		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_style('style_bank_id', $plugin_include_url."style.css", $plugin_version);
		mf_enqueue_script('script_bank_id', $plugin_include_url."script.js", array(
			'plugin_url' => $plugin_include_url,
			'disable_default_login' => ($this->allow_username_login() ? 'no' : 'yes'),
			'login_type' => $data['login_type'],
			'took_too_long_text' => __("The login took too long. Please try again.", 'lang_bank_id'),
		), $plugin_version);
	}

	function login_form($data = array())
	{
		global $error_text;

		if(!is_array($data)){			$data = array();}
		if(!isset($data['print'])){		$data['print'] = true;}

		$out = "";

		$plugin_include_url = plugin_dir_url(__FILE__);

		$setting_bank_id_login_methods = get_option_or_default('setting_bank_id_login_methods', array());

		$has_username_login = $add_login_or = $this->allow_username_login();
		$has_ssc_login = (count($setting_bank_id_login_methods) == 0 || in_array('ssc', $setting_bank_id_login_methods));
		$has_qr_login = (count($setting_bank_id_login_methods) == 0 || in_array('qr', $setting_bank_id_login_methods));
		$has_connected_login = (count($setting_bank_id_login_methods) == 0 || in_array('connected', $setting_bank_id_login_methods));

		if($has_username_login && ($has_ssc_login || $has_qr_login || $has_connected_login))
		{
			$out .= "<div id='login_choice'>
				<div class='login_choice_bankid bankid_button'>
					<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>
					<span>".sprintf(__("Use %s", 'lang_bank_id'), "BankID")."</span>
				</div>
				<div class='login_choice_username bankid_button'>
					<span>".__("Use e-mail & password", 'lang_bank_id')."</span>
				</div>
			</div>";
		}

		if($has_ssc_login)
		{
			$field_required = ($this->allow_username_login() == false);

			/*if($add_login_or == true)
			{
				$out .= "<p class='login_or'><label>".__("or", 'lang_bank_id')."</label></p>";
			}*/

			$out .= "<div id='login_ssn' class='flex_flow'>
				<img src='".$plugin_include_url."images/bankid.svg' class='logo'>"
				.show_textfield(array('custom_tag' => 'p', 'name' => 'user_ssn', 'required' => $field_required, 'placeholder' => __("Social Security Number", 'lang_bank_id'), 'xtra' => "class='input' autocomplete='off'")) //, 'text' => "BankID <a href='//support.bankid.com/sv'>(".sprintf(__("Get %s", 'lang_bank_id'), "BankID").")</a>"
			."</div>";

			$add_login_or = true;
		}

		if($has_qr_login)
		{
			$plugin_include_url = plugin_dir_url(__FILE__);

			if($add_login_or == true)
			{
				$out .= "<p class='login_or'><label>".__("or", 'lang_bank_id')."</label></p>";
			}

			$out .= "<div id='login_qr' class='bankid_button'>
				<span>".__("Get QR Code", 'lang_bank_id')."</span>
				<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>
			</div>";

			$add_login_or = true;
		}

		if($has_connected_login)
		{
			$plugin_include_url = plugin_dir_url(__FILE__);

			if($add_login_or == true)
			{
				$out .= "<p class='login_or'><label>".__("or", 'lang_bank_id')."</label></p>";
			}

			$out .= "<div id='login_connected' class='bankid_button'>
				<span>".__("Same Device", 'lang_bank_id')."</span>
				<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>
			</div>";

			$add_login_or = true;
		}

		if($has_ssc_login || $has_qr_login || $has_connected_login)
		{
			$out .= "<div id='login_loading' class='hide'><i class='fa fa-spinner fa-spin fa-3x'></i></div>
			<div id='notification' class='hide'></div>";
		}

		if($data['print'] == true)
		{
			echo $out;
		}

		else
		{
			return $out;
		}
	}
}