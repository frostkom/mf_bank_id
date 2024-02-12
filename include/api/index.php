<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_bank_id/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

session_start();

include_once("../classes.php");

switch(get_option('setting_bank_id_api_version'))
{
	default:
	case 5:
		include_once("../lib/bankid_v5/vendor/autoload.php");
		include_once("../lib/bankid_v5/src/Service/BankIDService.php");
	break;

	case 6:
		include_once("../lib/bankid_v6/vendor/autoload.php");
		include_once("../lib/bankid_v6/src/Service/BankIDService.php");
	break;
}

if(!isset($obj_bank_id))
{
	$obj_bank_id = new mf_bank_id();
}

$action = check_var('action');
$login_type = check_var('login_type');
$order_ref = check_var('orderref');

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
	switch(get_option('setting_bank_id_api_version'))
	{
		default:
		case 5:
			$api_url = "https://appapi.test.bankid.com/rp/v5/";

			$user_ssn = check_var('user_ssn');
			$user_ssn = $obj_bank_id->filter_ssn($user_ssn);
		break;

		case 6:
			$api_url = "https://appapi.test.bankid.com/rp/v6.0/";
		break;
	}

	$arr_params = array(
		'cert' => __DIR__."/certs/certname.pem",
		'verify' => false,
	);
}

else
{
	switch(get_option('setting_bank_id_api_version'))
	{
		default:
		case 5:
			$api_url = "https://appapi2.bankid.com/rp/v5/";

			$user_ssn = check_var('user_ssn');
			$user_ssn = $obj_bank_id->filter_ssn($user_ssn);
		break;

		case 6:
			$api_url = "https://appapi2.bankid.com/rp/v6.0/";
		break;
	}

	$arr_params = array(
		'cert' => $setting_bank_id_certificate,
		//'verify' => __DIR__."/certs/appapi2.bankid.com.crt",
		'verify' => false,
	);
}

$json_output = array();

switch($action)
{
	// Can be removed when v6 is in use
	#######################
	case 'ssc_init':
		$json_output['error'] = 0;

		if(!empty($user_ssn))
		{
			$_SESSION['sesPersonelNumber'] = $user_ssn;

			$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

			try
			{
				$response = $bankIDService->getAuthResponse($user_ssn);
				$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
				$_SESSION['sesOrderRef'] = $response->orderRef;

				$json_output['msg'] = sprintf(__("I am trying to open the %s application. If it does not open automatically you have to do it manually", 'lang_bank_id'), "BankID");
			}

			catch(Exception $e)
			{
				$message_arr = json_decode($e->getMessage());

				$json_output['error'] = 1;
				$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
			}
		}
	break;

	case 'ssc_check':
		$order_ref = check_var('sesOrderRef');
		$user_ssn = check_var('sesPersonelNumber');

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->collectResponse($order_ref);

			switch($response->status)
			{
				case 'pending':
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $response->hintCode;
				break;

				case 'complete':
					$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

					$obj_bank_id->validate_and_login(array('type' => $login_type, 'ssn' => $user_ssn), $json_output);
				break;

				case 'NO_CLIENT':
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = __("Login attempt timed out. Please try again.", 'lang_bank_id');
				break;

				default:
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $response->status;
				break;
			}
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = $message_arr->response;
		}
	break;
	#######################

	case 'qr_init':
		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getAuthResponse();

			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;
			$_SESSION['sesStartToken'] = $response->qrStartToken;
			$_SESSION['sesTimeCreated'] = time();
			$_SESSION['sesStartSecret'] = $response->qrStartSecret;

			$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output)); //'response' => $response, 
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
			$response = $bankIDService->getAuthResponse();
			$_SESSION['sesAutoStartToken'] = $response->autoStartToken;
			$_SESSION['sesOrderRef'] = $response->orderRef;

			$connected_url = "bankid:///?autostarttoken=".$response->autoStartToken."&redirect=null";

			$json_output['success'] = 1;
			$json_output['html'] = "<a href='".$connected_url."'>".sprintf(__("Click to open %s app...", 'lang_bank_id'), "BankID")."</a>";
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
					if($action == 'qr_check')
					{
						$json_output['error'] = $json_output['retry'] = 1;
						$json_output = $obj_bank_id->get_qr_code(array('json_output' => $json_output)); //'response' => $response, 
					}

					else
					{
						$json_output['error'] = $json_output['retry'] = 1;
						$json_output['msg'] = $response->hintCode;
					}
				break;

				case 'complete':
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