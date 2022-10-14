<?php
/*
Plugin Name: MF BankID
Plugin URI: https://github.com/frostkom/mf_bank_id
Description: Extension to login with BankID
Version: 2.4.15
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
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

	add_action('cron_base', 'activate_bank_id', mt_rand(1, 10));
	add_action('cron_base', array($obj_bank_id, 'cron_base'), mt_rand(1, 10));

	if(is_admin())
	{
		register_activation_hook(__FILE__, 'activate_bank_id');
		register_uninstall_hook(__FILE__, 'uninstall_bank_id');

		add_action('admin_init', array($obj_bank_id, 'settings_bank_id'));
		add_action('admin_init', array($obj_bank_id, 'admin_init'), 0);

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
			add_action('login_init', array($obj_bank_id, 'login_init'), 0);
			add_action('login_form', array($obj_bank_id, 'login_form'));
		}

		add_action('register_form', array($obj_bank_id, 'register_form'), 0);
		add_action('user_register', array($obj_bank_id, 'user_register'));

		add_filter('filter_profile_fields', array($obj_bank_id, 'filter_profile_fields'));

		add_filter('filter_is_password_protected', array($obj_bank_id, 'filter_is_password_protected'), 10, 2);
		add_filter('the_content', array($obj_bank_id, 'the_content'));
	}

	function activate_bank_id()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_bank_id_v2', 'setting_bank_id_api_version', 'setting_bank_id_test_mode', 'setting_bank_id_disable_default_login'),
		));
	}

	function uninstall_bank_id()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_bank_id_certificate', 'option_bank_id_certificate', 'setting_bank_id_activate', 'setting_bank_id_login_methods', 'setting_bank_id_login_fields', 'setting_bank_id_api_mode'),
			'meta' => array('profile_ssn'),
		));
	}
}