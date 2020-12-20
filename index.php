<?php
/*
Plugin Name: MF BankID
Plugin URI: https://github.com/frostkom/mf_bank_id
Description: 
Version: 2.0.13
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_bank_id
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_bank_id
*/

include_once("include/classes.php");

$obj_bank_id = new mf_bank_id();

add_action('cron_base', array($obj_bank_id, 'cron_base'), mt_rand(1, 10));

if(is_admin())
{
	register_uninstall_hook(__FILE__, 'uninstall_bank_id');

	add_action('admin_init', array($obj_bank_id, 'settings_bank_id'));
	add_action('admin_init', array($obj_bank_id, 'admin_init'), 0);

	add_filter('upload_mimes', array($obj_bank_id, 'upload_mimes'));

	add_action('manage_users_columns', array($obj_bank_id, 'manage_users_columns'));
	add_action('manage_users_custom_column', array($obj_bank_id, 'manage_users_custom_column'), 10, 3);

	add_action('admin_notices', array($obj_bank_id, 'admin_notices'));

	add_action('show_user_profile', array($obj_bank_id, 'edit_user_profile'));
	add_action('edit_user_profile', array($obj_bank_id, 'edit_user_profile'));
	add_action('profile_update', array($obj_bank_id, 'profile_update'));
}

else
{
	add_action('login_init', array($obj_bank_id, 'login_init'), 0);
	add_action('login_form', array($obj_bank_id, 'login_form'));

	add_action('register_form', array($obj_bank_id, 'register_form'), 0);
	add_action('user_register', array($obj_bank_id, 'user_register'));

	add_filter('filter_profile_fields', array($obj_bank_id, 'filter_profile_fields'));
}

load_plugin_textdomain('lang_bank_id', false, basename(dirname(__FILE__)).'/lang');

function uninstall_bank_id()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_bank_id_certificate', 'option_bank_id_certificate', 'setting_bank_id_activate', 'setting_bank_id_disable_default_login', 'setting_bank_id_v2'),
		'meta' => array('profile_ssn'),
	));
}