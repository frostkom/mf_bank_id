<?php

class mf_bank_id
{
	var $post_type = 'mf_bank_id';
	var $meta_prefix;

	function __construct()
	{
		$this->meta_prefix = $this->post_type.'_';
	}

	function cron_base()
	{
		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			// Check certificate
			#######################
			$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
			$option_bank_id_certificate = get_site_option('option_bank_id_certificate');
			$setting_bank_id_certificate_expiry_date = get_site_option('setting_bank_id_certificate_expiry_date');
			$setting_bank_id_activate = get_option('setting_bank_id_activate');

			if($setting_bank_id_certificate == '' && $option_bank_id_certificate != '' && $setting_bank_id_activate == 'yes')
			{
				do_log("The backup (option_bank_id_certificate: ".$option_bank_id_certificate.") was set, but the setting in use (setting_bank_id_certificate) was empty even though the site was using BankID (setting_bank_id_activate: ".$setting_bank_id_activate.")");

				//update_site_option('setting_bank_id_certificate', $option_bank_id_certificate);
			}

			if($setting_bank_id_certificate_expiry_date > DEFAULT_DATE && $setting_bank_id_certificate_expiry_date < date("Y-m-d", strtotime("+2 month")))
			{
				do_log("The certificate is expiring ".$setting_bank_id_certificate_expiry_date);
			}
			#######################

			// Delete old uploads
			#######################
			list($upload_path, $upload_url) = get_uploads_folder($this->post_type);

			get_file_info(array('path' => $upload_path, 'callback' => 'delete_files_callback', 'time_limit' => WEEK_IN_SECONDS));
			get_file_info(array('path' => $upload_path, 'folder_callback' => 'delete_empty_folder_callback'));
			#######################
		}

		$obj_cron->end();
	}

	function site_transient_update_plugins($arr_plugins)
	{
		unset($arr_plugins->response['backwpup/backwpup.php']);

		return $arr_plugins;
	}

	function settings_bank_id()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array();

			if((!is_multisite() || is_main_site()) && (get_site_option('setting_bank_id_certificate') == '' || get_option('setting_bank_id_activate') != 'yes'))
			{
				$arr_settings['setting_bank_id_certificate'] = __("Certificate File", 'lang_bank_id');
			}

			if(get_site_option('setting_bank_id_certificate') != '')
			{
				if(!is_multisite() || is_main_site())
				{
					$arr_settings['setting_bank_id_certificate_expiry_date'] = __("Expiry Date", 'lang_bank_id');
				}

				$arr_settings['setting_bank_id_activate'] = __("Activate", 'lang_bank_id');

				if(get_option('setting_bank_id_activate') == 'yes')
				{
					$arr_settings['setting_bank_id_login_methods'] = __("Login Methods", 'lang_bank_id');
					$arr_settings['setting_bank_id_api_mode'] = __("API Mode", 'lang_bank_id');
					$arr_settings['setting_bank_id_login_intent'] = __("Login Intent", 'lang_bank_id');
					$arr_settings['setting_bank_id_sign_intent'] = __("Signature Intent", 'lang_bank_id');
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
			$description = sprintf(__("The file should be a %s file.", 'lang_bank_id'), ".pem")." <a href='//bankid.com/foretag/anslut-foeretag'>".__("Get yours here", 'lang_bank_id')."</a>";
		}

		echo get_media_library(array('name' => $setting_key, 'value' => $option, 'description' => $description));

		if(get_option('setting_bank_id_activate') == 'yes')
		{
			update_site_option('option_bank_id_certificate', $option);
		}
	}

	function setting_bank_id_certificate_expiry_date_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		echo show_textfield(array('type' => 'date', 'name' => $setting_key, 'value' => $option));
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

		$arr_data = array();
		$arr_data['username'] = __("Username", 'lang_bank_id');
		$arr_data['qr'] = __("QR Code", 'lang_bank_id');
		$arr_data['connected'] = __("Same Device", 'lang_bank_id');

		echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option));
	}

	function setting_bank_id_api_mode_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'live');

		$arr_data = array(
			'test' => __("Test", 'lang_bank_id'),
			'live' => __("Live", 'lang_bank_id'),
		);

		echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option));
	}

	function setting_bank_id_login_intent_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		$description = __("This is used as a way for you to display to the user why they are logging.", 'lang_bank_id')."<br><code>
			# ".__("Heading", 'lang_bank_id')." 1<br>
			".__("Text", 'lang_bank_id')."<br>
			---<br>
			## ".__("Heading", 'lang_bank_id')." 2<br>
			*".__("Highlight", 'lang_bank_id')."*<br>
			+ ".__("List Item", 'lang_bank_id')." 1<br>
			+ ".__("List Item", 'lang_bank_id')." 2<br>
			### ".__("Heading", 'lang_bank_id')." 3<br>
			| ".__("One", 'lang_bank_id')." | ".__("Two", 'lang_bank_id')." | ".__("Three", 'lang_bank_id')." |<br>
			|-|-|-|<br>
			| ".__("Row", 'lang_bank_id')." 1, ".__("Column", 'lang_bank_id')." 1 | ".__("Row", 'lang_bank_id')." 1, ".__("Column", 'lang_bank_id')." 2 | ".__("Row", 'lang_bank_id')." 1, ".__("Column", 'lang_bank_id')." 3 |<br>
			| ".__("Row", 'lang_bank_id')." 2, ".__("Column", 'lang_bank_id')." 1 | ".__("Row", 'lang_bank_id')." 2, ".__("Column", 'lang_bank_id')." 2 | ".__("Row", 'lang_bank_id')." 2, ".__("Column", 'lang_bank_id')." 3 |
		</code>";

		echo show_textarea(array('name' => $setting_key, 'value' => $option, 'description' => $description));
	}

	function setting_bank_id_sign_intent_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		echo show_textarea(array('name' => $setting_key, 'value' => $option));

		if(IS_SUPER_ADMIN && $option != '')
		{
			echo "<h3>".__("Sign", 'lang_bank_id')."</h3>
			<div class='widget login_form'>
				<div id='loginform'>";

					$this->login_init(array('login_type' => 'user'));

					//$plugin_include_url = plugin_dir_url(__FILE__);

					echo "<div class='login_loading hide'><i class='fa fa-spinner fa-spin fa-3x'></i></div>
					<div class='notification hide'></div>
					<div id='sign_form'>
						<div id='sign_qr' class='bankid_button'>"
							//."<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>"
							."<span>".__("Mobile BankID", 'lang_bank_id')."</span>
						</div>
						<div id='sign_connected' class='bankid_button'>
							<span>".__("BankID on This Device", 'lang_bank_id')."</span>
						</div>
					</div>";

				echo "</div>
			</div>";
		}
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

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_bank_id'] = array(
			'setting_bank_id_certificate' => array(
				'type' => 'string',
				'global' => true,
				'icon' => "fas fa-lock",
				'name' => __("BankID", 'lang_bank_id')." - ".__("Certificate File", 'lang_bank_id'),
			),
			'setting_bank_id_certificate_expiry_date' => array(
				'type' => 'string',
				'global' => true,
				'icon' => "fa fa-calendar-alt",
				'name' => __("BankID", 'lang_bank_id')." - ".__("Expiry Date", 'lang_bank_id'),
			),
			'setting_bank_id_activate' => array(
				'type' => 'bool',
				'global' => false,
				'icon' => "fa fa-check",
				'name' => __("BankID", 'lang_bank_id')." - ".__("Activate", 'lang_bank_id'),
			),
		);

		return $arr_settings;
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

	function get_qr_code($data)
	{
		if(!class_exists('QRcode'))
		{
			include_once("lib/phpqrcode/qrlib.php");
		}

		$qrStartToken = check_var('sesStartToken');
		$elapsedTime = (time() - check_var('sesTimeCreated', 'char', true, time()));
		$qrStartSecret = check_var('sesStartSecret');

		$qr_content = sprintf('bankid.%s.%d.%s', $qrStartToken, $elapsedTime, hash_hmac('sha256', $elapsedTime, $qrStartSecret));

		$qr_file = "qr_code_".md5($qr_content).".png";

		list($upload_path_qr, $upload_url_qr) = get_uploads_folder($this->post_type);

		QRcode::png($qr_content, $upload_path_qr.$qr_file, QR_ECLEVEL_H, 5, 7); // L/M/Q/H

		$site_icon = get_option('site_icon');

		if($site_icon > 0)
		{
			list($upload_path, $upload_url) = get_uploads_folder();

			//do_log(__FUNCTION__.": ".$site_icon." -> ".mf_get_post_content($site_icon)." -> ".str_replace($upload_url, $upload_path, mf_get_post_content($site_icon)));

			$logo_file = str_replace($upload_url, $upload_path, mf_get_post_content($site_icon, 'guid'));
			$logo_fraction = 6;
			$logo_padding = 2; // Adjust this value to control padding

			// Load QR code image
			$qr_image = imagecreatefrompng($upload_path_qr.$qr_file);

			// Create white background for logo
			##########################
			$qr_size = imagesx($qr_image);
			$logo_size = ($qr_size / $logo_fraction - 2 * $logo_padding); // Logo size calculated from QR code size
			$bg_size = ($qr_size / $logo_fraction);

			$white_bg = imagecreatetruecolor($bg_size, $bg_size);
			$white = imagecolorallocate($white_bg, 255, 255, 255);
			imagefill($white_bg, 0, 0, $white);
			##########################

			// Load & resize logo
			##########################
			$logo = imagecreatefrompng($logo_file);
			$logo_width = imagesx($logo);
			$logo_height = imagesy($logo);

			// Create a new image with white background
			$white_logo_bg = imagecreatetruecolor($logo_width, $logo_height);
			$white_logo = imagecolorallocate($white_logo_bg, 255, 255, 255);
			imagefill($white_logo_bg, 0, 0, $white_logo);

			// Copy the logo onto the white background
			imagecopy($white_logo_bg, $logo, 0, 0, 0, 0, $logo_width, $logo_height);
			$logo = $white_logo_bg;

			$logo_resized = imagecreatetruecolor($logo_size, $logo_size);
			imagecopyresampled($logo_resized, $logo, 0, 0, 0, 0, $logo_size, $logo_size, imagesx($logo), imagesy($logo));
			##########################

			// Center logo on white background
			$logo_pos = $logo_padding;
			imagecopy($white_bg, $logo_resized, $logo_pos, $logo_pos, 0, 0, imagesx($logo_resized), imagesy($logo_resized));

			// Calculate position to center logo on QR code
			$logo_qr_pos = (($qr_size - $bg_size) / 2);

			// Merge logo with white background onto QR code
			imagecopy($qr_image, $white_bg, $logo_qr_pos, $logo_qr_pos, 0, 0, $bg_size, $bg_size);

			// Save the final image
			imagepng($qr_image, $upload_path_qr.$qr_file);

			if(is_resource($qr_image) && get_resource_type($qr_image) === 'gd')
			{
				imagedestroy($qr_image);
			}

			if(is_resource($logo) && get_resource_type($logo) === 'gd')
			{
				imagedestroy($logo);
			}

			if(is_resource($logo_resized) && get_resource_type($logo_resized) === 'gd')
			{
				imagedestroy($logo_resized);
			}

			if(is_resource($white_bg) && get_resource_type($white_bg) === 'gd')
			{
				imagedestroy($white_bg);
			}

			if(is_resource($white_logo_bg) && get_resource_type($white_logo_bg) === 'gd')
			{
				imagedestroy($white_logo_bg);
			}
		}

		$data['json_output']['html'] = "<p>".__("Open your BankID app and scan the QR code", 'lang_bank_id')."</p>
		<div class='qr_code'>
			<svg>";

				for($i = 0; $i < 4; $i++)
				{
					$data['json_output']['html'] .= "<path d='M216,0h48a12,12,0,0,1,12,12V60.762' fill='none' stroke='#1d3a8f' stroke-linecap='round' stroke-width='8'></path>";
				}

			$data['json_output']['html'] .= "</svg>
			<img src='".$upload_url_qr.$qr_file."'>
		</div>";

		return $data['json_output'];
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
				$out = sprintf(__("Searching for %s, it may take a little while...", 'lang_bank_id'), "BankID"); //If a few seconds have passed and still no BankID has been found, you probably don't have a BankID which can be used for this login/signature on this computer. If you have a BankID card, please insert it into your card reader. If you don't have a BankID you can order one from your internet bank. If you have a BankID on another device you can start the BankID app on that device.
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
					$json_output['msg'] = __("The social security number that you are trying to login with is not connected to any user. Please login with your username and password, go to your Profile and add your social security number there.", 'lang_bank_id');
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

	// The same as used in Custom Login
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
			if($this->allow_username_login())
			{
				$error_text = __("You have to enter your Social Security Number", 'lang_bank_id');
			}

			else
			{
				$error_text = __("You have to enter your Social Security Number to be able to login in the future", 'lang_bank_id');
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
				<td>".show_textfield(array('name' => $meta_key, 'value' => $meta_value, 'placeholder' => __("YYMMDD-XXXX", 'lang_bank_id'), 'required' => true, 'xtra' => "class='regular-text' maxlength='12'"))."</td>"
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

	function filter_cookie_types($array)
	{
		$array['login'][$this->meta_prefix] = array('label' => __("Indicates whether you are logged in", 'lang_bank_id'), 'used' => false, 'lifetime' => "1 week");

		return $array;
	}

	function register_form()
	{
		$meta_key = 'profile_ssn';
		$meta_value = check_var($meta_key);
		$meta_text = __("Social Security Number", 'lang_bank_id');

		$post_id = (int)apply_filters('get_block_search', 'mf/custom_registration');

		if(!($post_id > 0))
		{
			$post_id = (int)apply_filters('get_widget_search', 'registration-widget');
		}

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
			'attributes' => array(
				'maxlength' => 12,
			),
		);

		return $arr_fields;
	}

	function filter_is_password_protected($is_protected, $data)
	{
		if($is_protected == false && get_post_meta($data['post_id'], $this->meta_prefix.'activate', true) == 'yes')
		{
			if($data['check_login'] == true && $this->is_address_logged_in())
			{
				// Do nothing
				//$is_protected = false;
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
			$emlAddressEmail = $wpdb->get_var($wpdb->prepare("SELECT addressEmail FROM ".$wpdb->prefix."address WHERE addressBirthDate = %s", $in));

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
		$cookie_value = 'address_ssn_'.get_current_visitor_ip();

		setcookie($cookie_name, md5($cookie_value), strtotime("+1 week"), COOKIEPATH);

		return true;
	}

	function is_address_logged_in()
	{
		$cookie_name = $this->meta_prefix.COOKIEHASH;
		$cookie_value = 'address_ssn_'.get_current_visitor_ip();

		$cookie_value_md5 = (isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '');

		return ($cookie_value_md5 == md5($cookie_value));
	}

	function the_content($html)
	{
		global $post;

		if(isset($post->ID) && is_user_logged_in() == false && apply_filters('filter_is_password_protected', false, array('post_id' => $post->ID, 'check_login' => true)) == true)
		{
			$this->login_init(array('login_type' => 'address'));

			$html = "<form id='loginform' class='mf_form' action='#' method='post'>
				<p>".__("To view the content on this page you have to first login.", 'lang_bank_id')."</p>"
				.$this->login_form(array('print' => false))
				."<div".get_form_button_classes().">"
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

		mf_enqueue_style('style_bank_id', $plugin_include_url."style.css");
		mf_enqueue_script('script_bank_id', $plugin_include_url."script.js", array(
			'plugin_url' => $plugin_include_url,
			'allow_username_login' => $this->allow_username_login(),
			'login_type' => $data['login_type'],
			'took_too_long_text' => sprintf(__("The login took too long. %sPlease try again%s.", 'lang_bank_id'), "<a href='?try_again'>", "</a>"),
		));
	}

	function login_form($data = array())
	{
		global $error_text;

		if(!is_array($data)){			$data = array();}
		if(!isset($data['print'])){		$data['print'] = true;}

		$this->login_init(array('login_type' => 'user'));

		$out = "";

		//$plugin_include_url = plugin_dir_url(__FILE__);

		$setting_bank_id_login_methods = get_option_or_default('setting_bank_id_login_methods', array());

		//$add_login_or = false;
		//$has_ssc_login = (count($setting_bank_id_login_methods) == 0 || in_array('ssc', $setting_bank_id_login_methods));
		$has_qr_login = (count($setting_bank_id_login_methods) == 0 || in_array('qr', $setting_bank_id_login_methods));
		$has_connected_login = (count($setting_bank_id_login_methods) == 0 || in_array('connected', $setting_bank_id_login_methods));

		if($has_qr_login || $has_connected_login) //$has_ssc_login || 
		{
			$out .= "<div class='login_loading hide'><i class='fa fa-spinner fa-spin fa-3x'></i></div>
			<div class='notification hide'></div>";
		}

		if($this->allow_username_login() && ($has_qr_login || $has_connected_login)) //$has_ssc_login || 
		{
			$out .= "<div id='login_choice'>
				<div class='login_choice_bankid bankid_button'>"
					//."<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>"
					."<span>".sprintf(__("Use %s", 'lang_bank_id'), "BankID")."</span>
				</div>
				<div class='login_choice_username bankid_button'>
					<span>".__("Use e-mail & password", 'lang_bank_id')."</span>
				</div>
			</div>";
		}

		/*if($has_ssc_login)
		{
			$out .= "<div id='login_ssn' class='flex_flow'>"
				//."<img src='".$plugin_include_url."images/bankid.svg' class='logo'>"
				.show_textfield(array('custom_tag' => 'p', 'name' => 'user_ssn', 'required' => ($this->allow_username_login() == false), 'placeholder' => __("Social Security Number", 'lang_bank_id'), 'xtra' => "class='input' autocomplete='off'"))
			."</div>";

			//$add_login_or = true;
		}*/

		if($has_qr_login)
		{
			$out .= "<div id='login_qr' class='bankid_button'>"
				//."<img src='".$plugin_include_url."images/bankid_black.svg' class='logo'>"
				."<span>".__("Mobile BankID", 'lang_bank_id')."</span>
			</div>";

			//$add_login_or = true;
		}

		if($has_connected_login)
		{
			/*if($add_login_or == true)
			{
				$out .= "<p class='login_or'><label>".__("or", 'lang_bank_id')."</label></p>";
			}*/

			$out .= "<div id='login_connected' class='bankid_button'>
				<span>".__("BankID on This Device", 'lang_bank_id')."</span>
			</div>";
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