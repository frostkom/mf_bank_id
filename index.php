<?php
/*
Plugin Name: MF BankID
Plugin URI: https://github.com/frostkom/mf_bank_id
Description: Extension to login with BankID
Version: 2.7.16
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://martinfors.se
Text Domain: lang_bank_id
Domain Path: /lang

Credit URI: https://github.com/dimafe6/bank-id

Depends: MF Base
GitHub Plugin URI: frostkom/mf_bank_id
*/

if(!function_exists('is_plugin_active') || function_exists('is_plugin_active') && is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	load_plugin_textdomain('lang_bank_id', false, basename(dirname(__FILE__)).'/lang');

	$obj_bank_id = new mf_bank_id();

	add_action('cron_base', array($obj_bank_id, 'cron_base'), mt_rand(1, 10));

	if(is_admin())
	{
		register_uninstall_hook(__FILE__, 'uninstall_bank_id');

		add_filter('site_transient_update_plugins', array($obj_bank_id, 'site_transient_update_plugins'));

		add_action('admin_init', array($obj_bank_id, 'settings_bank_id'));
		add_action('admin_init', array($obj_bank_id, 'admin_init'), 0);

		add_filter('filter_sites_table_settings', array($obj_bank_id, 'filter_sites_table_settings'));

		add_filter('upload_mimes', array($obj_bank_id, 'upload_mimes'));

		add_action('manage_users_columns', array($obj_bank_id, 'manage_users_columns'));
		add_action('manage_users_custom_column', array($obj_bank_id, 'manage_users_custom_column'), 10, 3);

		if(get_site_option('setting_bank_id_certificate') != '' && get_option('setting_bank_id_activate') == 'yes')
		{
			add_action('rwmb_meta_boxes', array($obj_bank_id, 'rwmb_meta_boxes'));

			add_action('admin_notices', array($obj_bank_id, 'admin_notices'));
		}

		add_action('show_user_profile', array($obj_bank_id, 'edit_user_profile'));
		add_action('edit_user_profile', array($obj_bank_id, 'edit_user_profile'));
		add_action('profile_update', array($obj_bank_id, 'profile_update'));

		add_filter('filter_theme_core_seo_type', array($obj_bank_id, 'filter_theme_core_seo_type'));

		add_filter('filter_cookie_types', array($obj_bank_id, 'filter_cookie_types'));
	}

	else
	{
		if(get_site_option('setting_bank_id_certificate') != '' && get_option('setting_bank_id_activate') == 'yes')
		{
			add_action('login_form', array($obj_bank_id, 'login_form'));
		}

		add_action('register_form', array($obj_bank_id, 'register_form'), 0);
		add_action('user_register', array($obj_bank_id, 'user_register'));

		add_filter('filter_profile_fields', array($obj_bank_id, 'filter_profile_fields'));

		add_filter('filter_is_password_protected', array($obj_bank_id, 'filter_is_password_protected'), 10, 2);
		add_filter('the_content', array($obj_bank_id, 'the_content'));
	}

	add_filter('filter_user_allowed_to_login', array($obj_bank_id, 'filter_user_allowed_to_login'), 10, 2);

	function uninstall_bank_id()
	{
		include_once("include/classes.php");

		$obj_bank_id = new mf_bank_id();

		mf_uninstall_plugin(array(
			'uploads' => $obj_bank_id->post_type,
			'options' => array('setting_bank_id_certificate', 'option_bank_id_certificate', 'setting_bank_id_certificate_expiry_date', 'setting_bank_id_activate', 'setting_bank_id_login_methods', 'setting_bank_id_login_fields', 'setting_bank_id_api_mode', 'setting_bank_id_login_intent', 'setting_bank_id_sign_intent'),
			'meta' => array('profile_ssn'),
		));
	}
}