<?php

namespace Dimafe6\BankID\Model;

/**
 * Class OrderResponse
 *
 * Response from auth and sign methods
 *
 * @property string $orderRef Used to collect the status of the order.
 * @property string $autoStartToken Used as reference to this order when the client is started automatically.
 */
class OrderResponse extends AbstractResponseModel
{
	// This will make the login crash
	/*var $orderRef;
	var $autoStartToken;
	var $qrStartToken;
	var $qrStartSecret;
	var $orderRef;
	var $status;
	var $hintCode;
	var $completionData;*/
}