<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_bank_id/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

session_start();

include_once("../classes.php");
include_once("../lib/bankid_v5/vendor/autoload.php");
include_once("../lib/bankid_v5/src/Service/BankIDService.php");

if(!isset($obj_bank_id))
{
	$obj_bank_id = new mf_bank_id();
}

$action = check_var('action');
$login_type = check_var('login_type');
$user_ssn = check_var('user_ssn');
$orderref = check_var('orderref');

list($upload_path, $upload_url) = get_uploads_folder();

$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
//$setting_bank_id_certificate = str_replace($upload_url, $upload_path, $setting_bank_id_certificate);
list($domain, $file_path) = explode("/wp-content/uploads/", $setting_bank_id_certificate);
$setting_bank_id_certificate = $upload_path.$file_path;

if(!file_exists($setting_bank_id_certificate))
{
	do_log(sprintf("The file %s does not exist", $setting_bank_id_certificate)." (".$upload_url." -> ".$upload_path.")");
}

if(get_option('setting_bank_id_api_mode') == 'test')
{
	$api_url = "https://appapi.test.bankid.com/rp/v5/";

	$arr_params = array(
		'cert' => __DIR__."/certs/certname.pem",
		//'verify' => __DIR__."/certs/appapi2.test.bankid.com.crt",
		'verify' => false,
	);
}

else
{
	$api_url = "https://appapi2.bankid.com/rp/v5/";

	$arr_params = array(
		'cert' => $setting_bank_id_certificate,
		//'verify' => __DIR__."/certs/appapi2.bankid.com.crt",
		'verify' => false,
	);
}

$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

$json_output = array();

switch($action)
{
	case 'ssc_init':
		$json_output['error'] = 0;

		$_SESSION['personelnumber'] = $user_ssn;

		if(!empty($user_ssn))
		{
			$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

			try
			{
				$response = $bankIDService->getAuthResponse($user_ssn);
				$_SESSION['start_token'] = $response->autoStartToken;
				$_SESSION['orderRef'] = $response->orderRef;

				$json_output['msg'] = sprintf(__("I am trying to open the %s application. If it does not open automatically you have to do it manually", 'lang_bank_id'), "BankID");

				// This will result in timeout anyway...
				/*if($response->autoStartToken != '')
				{
					$json_output['msg'] .= " <a href='bankid:///?autostarttoken=".$response->autoStartToken."&redirect=null'>".sprintf(__("Click to open %s app...", 'lang_bank_id'), "BankID")."</a>";
				}*/
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
		$orderref = (isset($_SESSION['orderRef']) ? $_SESSION['orderRef'] : '');
		$user_ssn = (isset($_SESSION['personelnumber']) ? $_SESSION['personelnumber'] : '');

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$result = $bankIDService->collectResponse($orderref);

			switch($result->status)
			{
				case 'pending':
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $result->hintCode;
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
					$json_output['msg'] = $result->status;
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

	case 'qr_init':
		include_once("../lib/phpqrcode/qrlib.php");

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getAuthResponse();
			$_SESSION['start_token'] = $response->autoStartToken;
			$_SESSION['orderRef'] = $response->orderRef;

			$qr_content = "bankid:///?autostarttoken=".$response->autoStartToken;
			$qr_file = "qr_code_".md5($qr_content).".png";

			QRcode::png($qr_content, $upload_path.$qr_file);

			$json_output['success'] = 1;
			$json_output['html'] = "<img src='".$upload_url.$qr_file."'>";
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = (isset($message_arr->response) ? $message_arr->response : __("Unknown Error", 'lang_bank_id'));
		}
	break;

	case 'connected_init':
		include_once("../lib/phpqrcode/qrlib.php");

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$response = $bankIDService->getAuthResponse();
			$_SESSION['start_token'] = $response->autoStartToken;
			$_SESSION['orderRef'] = $response->orderRef;

			$connected_url = "bankid:///?autostarttoken=".$response->autoStartToken."&redirect=null";

			$json_output['success'] = 1;
			$json_output['html'] = "<a href='".$connected_url."'>".sprintf(__("Click to open %s app...", 'lang_bank_id'), "BankID")."</a>";
		}

		catch(Exception $e)
		{
			$message_arr = json_decode($e->getMessage());

			$json_output['error'] = 1;
			$json_output['msg'] = $message_arr->response;
		}
	break;

	case 'qr_check':
	case 'connected_check':
		$orderref = (isset($_SESSION['orderRef']) ? $_SESSION['orderRef'] : '');

		$bankIDService = new BankIDService($api_url, get_current_visitor_ip(), $arr_params);

		try
		{
			$result = $bankIDService->collectResponse($orderref);

			switch($result->status)
			{
				case 'pending':
					$json_output['error'] = 1;
					$json_output['retry'] = 1;
					$json_output['msg'] = $result->hintCode;
				break;

				case 'complete':
					$user_ssn = $result->completionData->user->personalNumber;
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
					$json_output['msg'] = $result->status;
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