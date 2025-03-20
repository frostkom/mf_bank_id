<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_bank_id/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

session_start();

include_once("../classes.php");
include_once("../lib/bankid_v6/vendor/autoload.php");
include_once("../lib/bankid_v6/src/Service/BankIDService.php");

if(!isset($obj_bank_id))
{
	$obj_bank_id = new mf_bank_id();
}

$action = check_var('action');
//$order_ref = check_var('orderref');

list($upload_path, $upload_url) = get_uploads_folder();

$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
list($domain, $file_path) = explode("/wp-content/uploads/", $setting_bank_id_certificate);
$setting_bank_id_certificate = $upload_path.$file_path;

if(!file_exists($setting_bank_id_certificate))
{
	do_log(sprintf("The file %s does not exist", $setting_bank_id_certificate)." (".$upload_url." -> ".$upload_path.")");
}

if(get_option('setting_bank_id_api_mode') == 'test')
{
	$api_url = "https://appapi.test.bankid.com/rp/v6.0/";

	$arr_params = array(
		'cert' => __DIR__."/certs/certname.pem",
		'verify' => false,
	);
}

else
{
	$api_url = "https://appapi2.bankid.com/rp/v6.0/";

	$arr_params = array(
		'cert' => $setting_bank_id_certificate,
		//'verify' => __DIR__."/certs/appapi2.bankid.com.crt",
		'verify' => false,
	);
}

$json_output = array();

switch($action)
{
	case 'qr_init':
		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getAuthResponse(array('intent' => $obj_bank_id->get_intent()));

			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;
			$_SESSION['sesStartToken'] = (isset($response->qrStartToken) ? $response->qrStartToken : '');
			$_SESSION['sesTimeCreated'] = time();
			$_SESSION['sesStartSecret'] = (isset($response->qrStartSecret) ? $response->qrStartSecret : '');

			$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output));
			$json_output['success'] = 1;
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'connected_init':
		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getAuthResponse(array('intent' => $obj_bank_id->get_intent()));

			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;

			$connected_url = "bankid:///?autostarttoken=".$response->autoStartToken."&redirect=null";

			$json_output['success'] = 1;
			$json_output['html'] = "<a href='".$connected_url."'>".sprintf(__("Click to open %s app...", 'lang_bank_id'), "BankID")."</a>";
			$json_output['redirect'] = $connected_url;
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'qr_check':
	case 'connected_check':
		$order_ref = check_var('sesOrderRef');
		$time_created = check_var('sesTimeCreated');

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->collectResponse($order_ref);

			switch($response->status)
			{
				case 'pending':
					$json_output['error'] = $json_output['retry'] = 1;

					if($action == 'qr_check')
					{
						$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output));
					}

					else
					{
						$json_output['msg'] = $response->hintCode;
					}
				break;

				case 'complete':
					$login_type = check_var('login_type');

					$user_ssn = $response->completionData->user->personalNumber;
					$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

					$obj_bank_id->validate_and_login(array('type' => $login_type, 'ssn' => $user_ssn), $json_output);
				break;

				case 'NO_CLIENT':
					$json_output['error'] = $json_output['retry'] = 1;
					$json_output['msg'] = __("Login attempt timed out. Please try again.", 'lang_bank_id');
				break;

				default:
					$json_output['error'] = $json_output['retry'] = 1;
					$json_output['msg'] = $response->status;
				break;
			}
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'sign_qr_init':
		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getSignResponse(array('intent' => get_option('setting_bank_id_sign_intent')));

			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;
			$_SESSION['sesStartToken'] = (isset($response->qrStartToken) ? $response->qrStartToken : '');
			$_SESSION['sesTimeCreated'] = time();
			$_SESSION['sesStartSecret'] = (isset($response->qrStartSecret) ? $response->qrStartSecret : '');

			$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output));
			$json_output['success'] = 1;
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'sign_connected_init':
		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getSignResponse(array('intent' => get_option('setting_bank_id_sign_intent')));

			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;

			$connected_url = "bankid:///?autostarttoken=".$response->autoStartToken."&redirect=null";

			$json_output['success'] = 1;
			$json_output['html'] = "<a href='".$connected_url."'>".sprintf(__("Click to open %s app...", 'lang_bank_id'), "BankID")."</a>";
			$json_output['redirect'] = $connected_url;
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'sign_qr_check':
	case 'sign_connected_check':
		$order_ref = check_var('sesOrderRef');
		$time_created = check_var('sesTimeCreated');

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->collectResponse($order_ref);

			switch($response->status)
			{
				case 'pending':
					$json_output['error'] = $json_output['retry'] = 1;

					if($action == 'sign_qr_check')
					{
						$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output));
					}

					else
					{
						$json_output['msg'] = $response->hintCode;
					}
				break;

				case 'complete':
					$user_ssn = $response->completionData->user->personalNumber;
					$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

					if($obj_bank_id->user_exists($user_ssn))
					{
						//$response->completionData->user->name, $response->completionData->user->givenName, $response->completionData->user->surname, $response->completionData->device->ipAddress, $response->completionData->signature, $response->completionData->ocspResponse

						$json_output['success'] = 1;
						$json_output['msg'] = __("The signature was successful!", 'lang_bank_id');
					}

					else
					{
						$json_output['error'] = 1;
						$json_output['msg'] = __("The social security number that you are trying to sign with is not connected to any user. Please login with your username and password, go to your Profile and add your social security number there.", 'lang_bank_id');
					}
				break;

				case 'NO_CLIENT':
					$json_output['error'] = $json_output['retry'] = 1;
					$json_output['msg'] = __("Login attempt timed out. Please try again.", 'lang_bank_id');
				break;

				default:
					$json_output['error'] = $json_output['retry'] = 1;
					$json_output['msg'] = $response->status;
				break;
			}
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	default:
		$json_output = array(
			'error' => 1,
			'msg' => $obj_bank_id->get_message($action),
		);
	break;
}

session_write_close();

echo json_encode($json_output);
exit;