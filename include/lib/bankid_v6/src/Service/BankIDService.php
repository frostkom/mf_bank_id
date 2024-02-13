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

	public function getAuthResponse($personalNumber = null)
	{
		$parameters = [
			'endUserIp' => $this->endUserIp,
			'requirement' => [
				'pinCode' => false,
			],
		];

		$responseData = $this->client->post('auth', ['json' => $parameters]);

		$response = new OrderResponse($responseData);

		return $response;
	}

	public function collectResponse($orderRef)
	{
		$responseData = $this->client->post('collect', ['json' => ['orderRef' => $orderRef]]);

		return new CollectResponse($responseData);
	}
}