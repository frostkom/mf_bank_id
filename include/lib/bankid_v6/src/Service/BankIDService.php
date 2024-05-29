<?php

use Dimafe6\BankID\Model\CollectResponse;
use Dimafe6\BankID\Model\OrderResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class BankIDService
{
	private $client;
	private $apiUrl;
	private $options;
	private $endUserIp;

	public function __construct($apiUrl, $endUserIp, $options = [])
	{
		$this->apiUrl = $apiUrl;
		$this->endUserIp = $endUserIp;

		$options['base_uri'] = $apiUrl;
		$options['json'] = true;

		$this->options = $options;

		$this->client = new Client($this->options);
	}

	public function getAuthResponse($data)
	{
		$parameters = array(
			'endUserIp' => $this->endUserIp,
			//'userNonVisibleData' => base64_encode("[data]"),
			//'returnRisk' => true,
			'requirement' => array(
				'pinCode' => false,
			),
		);

		if($data['intent'] != '')
		{
			$parameters['userVisibleData'] = base64_encode($data['intent']);
			$parameters['userVisibleDataFormat'] = "simpleMarkdownV1";
		}

		$response = new OrderResponse($this->client->post('auth', array('json' => $parameters)));

		return $response;
	}

	public function collectResponse($orderRef)
	{
		$responseData = $this->client->post('collect', ['json' => ['orderRef' => $orderRef]]);

		return new CollectResponse($responseData);
	}
}