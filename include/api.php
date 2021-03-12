<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_bank_id/include", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

session_start();

include_once("classes.php");

$obj_bank_id = new mf_bank_id();

$action = check_var('action');
$user_ssn = check_var('user_ssn');
$orderref = check_var('orderref');

list($upload_path, $upload_url) = get_uploads_folder();

$setting_bank_id_certificate = get_site_option('setting_bank_id_certificate');
$setting_bank_id_certificate = str_replace($upload_url, $upload_path, $setting_bank_id_certificate);

if(!file_exists($setting_bank_id_certificate))
{
	do_log(sprintf("The file %s does not exist", $setting_bank_id_certificate));
}

/*$setting_bank_id_api_version = get_option('setting_bank_id_api_version');

switch($setting_bank_id_api_version)
{
	default:
	case 4:
		define('JPATH_BASE', __DIR__);

		include_once("lib/class-bankid.php");
		include_once("lib/class-utils.php");

		$arr_params = array(
			//'logo' => '',
			//'welcome_text' => '',
			//'background_color' => '',
			//'button_color' => '',
			//'background_image' => '',
			'cert_path' => (get_option('setting_bank_id_api_mode') == 'live' || get_site_option('setting_bank_id_test_mode') == 'yes' ? __DIR__.'/certs/certname.pem' : $setting_bank_id_certificate),
			'test_mode' => (get_option('setting_bank_id_api_mode') == 'live' || get_site_option('setting_bank_id_test_mode') == 'yes'),
		);
	break;

	case 5:*/
		include_once("lib/bankid_v5/vendor/autoload.php");
		include_once("lib/bankid_v5/src/Service/BankIDService.php");

		if(get_option('setting_bank_id_api_mode') == 'test' || get_site_option('setting_bank_id_test_mode') == 'yes')
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
	/*break;
}*/

$user_ssn = $obj_bank_id->filter_ssn($user_ssn);

$json_output = array();

switch($action)
{
	case 'init':
		//$json_output = array();

		/*switch($setting_bank_id_api_version)
		{
			default:
			case 4:
				$bankid_client = new BankID($arr_params);
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

			case 5:*/
				$json_output['error'] = 0;

				$_SESSION['personelnumber'] = $user_ssn;

				if(!empty($user_ssn)) 
				{
					$bankIDService = new BankIDService($api_url, $_SERVER['REMOTE_ADDR'], $arr_params);

					try
					{
						$response = $bankIDService->getAuthResponse($user_ssn);
						$_SESSION['start_token'] = $response->autoStartToken;
						$_SESSION['orderRef'] = $response->orderRef;
					}

					catch(Exception $e)
					{
						$message_arr = json_decode($e->getMessage());

						$json_output['error'] = 1;
						$json_output['msg'] = $message_arr->response;
					}
				}
			/*break;
		}*/
	break;

	case 'check':
		/*$json_output = array(
			'error' => 0,
			'success' => 0,
			'retry' => 0,
		);*/

		/*switch($setting_bank_id_api_version)
		{
			default:
			case 4:
				$bankid_client = new BankID($arr_params);
				$order_reference = $bankid_client->collect($orderref);
				$result = $order_reference[0];

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
								$json_output['msg'] = __("Something went wrong when trying to login. If the problem persists, please contact an admin.", $obj_bank_id->lang_key);
							}
						}

						else
						{
							$json_output = array(
								'error' => 1,
								'msg' => __("The social security number that you are trying to login with is not connected to any user. Please login with you username and password, go to your Profile and add your social security number there.", $obj_bank_id->lang_key),
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

			case 5:*/
				$orderref = $_SESSION['orderRef'];
				$user_ssn = $_SESSION['personelnumber'];

				$bankIDService = new BankIDService($api_url, $_SERVER['REMOTE_ADDR'], $arr_params);

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

							if($obj_bank_id->user_exists($user_ssn))
							{
								if($obj_bank_id->login($obj_bank_id->user_login))
								{
									$json_output['success'] = 1;
									$json_output['msg'] = __("The validation was successful! You are being logged in...", $obj_bank_id->lang_key);
									$json_output['redirect'] = admin_url();
								}

								else
								{
									$json_output['error'] = 1;
									$json_output['msg'] = __("Something went wrong when trying to login. If the problem persists, please contact an admin.", $obj_bank_id->lang_key);
								}
							}

							else
							{
								$json_output['error'] = 1;
								$json_output['msg'] = __("The social security number that you are trying to login with is not connected to any user. Please login with you username and password, go to your Profile and add your social security number there.", $obj_bank_id->lang_key);
							}
						break;

						case 'NO_CLIENT':
							$json_output['error'] = 1;
							$json_output['retry'] = 1;
							$json_output['msg'] = __("Login attempt timed out. Please try again.", $obj_bank_id->lang_key);
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
			/*break;
		}*/
	break;

	default:
		$json_output = array(
			'error' => 1,
			'msg' => $obj_bank_id->get_message($action),
		);
	break;
}

echo json_encode($json_output);
exit;