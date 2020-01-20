<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_bank_id/include", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

session_start();

define('JPATH_BASE', __DIR__);

include_once("lib/class-bankid.php");
include_once("lib/class-utils.php");
include_once("classes.php");

$obj_bank_id = new mf_bank_id();

$action = check_var('action');
$user_ssn = check_var('user_ssn'); //, 'soc'
$orderref = check_var('orderref');

list($upload_path, $upload_url) = get_uploads_folder();

$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
$setting_bank_id_certificate = str_replace($upload_url, $upload_path, $setting_bank_id_certificate);

if(!file_exists($setting_bank_id_certificate))
{
	do_log(sprintf("The file %s does not exist", $setting_bank_id_certificate));
}

$plg_params = array(
	'logo' => '',
	'welcome_text' => '',
	'background_color' => '',
	'button_color' => '',
	'background_image' => '',
	'cert_path' => $setting_bank_id_certificate,
	'test_mode' => get_site_option('setting_bank_id_test_mode') == 'yes' ? 1 : 0,
);

$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

/*if($obj_bank_id->user_exists($user_ssn))
{*/
	switch($action)
	{
		case 'init':
			$json_output = array();

			$bankid_client = new BankID($plg_params);
			$order_reference = $bankid_client->authenticate($user_ssn);
			$result = $order_reference[0];

			if(!empty($result))
			{
				$_SESSION['orderref'] = $result->orderRef;

				$json_output['start_token'] = $result->autoStartToken;
				$json_output['orderref'] = $result->orderRef;
			}

			else
			{
				$json_output['error'] = 1;
				$json_output['msg'] = $obj_bank_id->get_message($order_reference[1]);
			}
		break;

		case 'check':
			$json_output = array(
				'error' => 0,
				'success' => 0,
				'retry' => 0,
			);

			$bankid_client = new BankID($plg_params);
			$order_reference = $bankid_client->collect($orderref);
			$result = $order_reference[0];

			/*$order_reference = array ( 0 => stdClass::__set_state(array( 'progressStatus' => 'COMPLETE', 'signature' => '[signature]', 'userInfo' => stdClass::__set_state(array( 'givenName' => '[first_name]', 'surname' => '[sur_name]', 'name' => '[full_name]', 'personalNumber' => '[ssn]', 'notBefore' => '[date]', 'notAfter' => '[date]', 'ipAddress' => '[ip]', )), 'ocspResponse' => '[signature]', )), 1 => NULL, )*/

			if(!empty($result))
			{
				if($result->progressStatus == "USER_SIGN")
				{
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $obj_bank_id->get_message($result->progressStatus);
				}

				else if($result->progressStatus == "COMPLETE")
				{
					$user_ssn = $result->userInfo->personalNumber;
					$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

					if($obj_bank_id->user_exists($user_ssn))
					{
						if($obj_bank_id->login($obj_bank_id->user_login))
						{
							$json_output['success'] = 1;
							$json_output['redirect'] = admin_url(); //$obj_bank_id->get_message($result->progressStatus)
						}

						else
						{
							$json_output['error'] = 1;
							$json_output['msg'] = __("Something went wrong when trying to login. If the problem persists, please contact an admin", 'lang_bank_id');
						}
					}

					else
					{
						$json_output = array(
							'error' => 1,
							'msg' => __("The social security number that you are trying to login with is not connected to any user. Please login with you username and password, go to your Profile and add your social security number there.", 'lang_bank_id'),
						);
					}
				}

				else
				{
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $obj_bank_id->get_message($result->progressStatus);
				}
			}

			else
			{
				$json_output['error'] = 1;
				$json_output['msg'] = $obj_bank_id->get_message('CHECK_ERROR'); //$reply
			}
		break;

		default:
			$json_output = array(
				'error' => 1,
				'msg' => $obj_bank_id->get_message($action),
			);
		break;
	}
/*}

else
{
	$json_output = array(
		'error' => 1,
		'msg' => __("The social security number that you are trying to login with is not connected to any user. Please login with your username and password, go to your Profile and add your social security number there.", 'lang_bank_id'),
	);
}*/

echo json_encode($json_output);
exit;