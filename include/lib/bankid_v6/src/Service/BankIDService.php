<?php

use Dimafe6\BankID\Model\CollectResponse;
use Dimafe6\BankID\Model\OrderResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Class BankIDService
 *
 * @category PHP
 * @package  Dimafe6\BankID\Service
 * @author   Dmytro Feshchenko <dimafe2000@gmail.com>
 */
class BankIDService
{
	/** @var Client $client Guzzle http client */
	private $client;

	/** @var string $apiUrl BankID API base url */
	private $apiUrl;

	/** @var array $options Guzzle client options. @see http://docs.guzzlephp.org/en/stable/request-options.html */
	private $options;

	/** @var string $endUserIp The user IP address as seen by RP. String. IPv4 and IPv6 is allowed */
	private $endUserIp;

	/**
	 * BankIDService constructor.
	 * @param string $apiUrl
	 * @param string $endUserIp
	 * @param array $options
	 */
	public function __construct($apiUrl, $endUserIp, $options = [])
	{
		$this->apiUrl = $apiUrl;
		$this->endUserIp = $endUserIp;

		$options['base_uri'] = $apiUrl;
		$options['json'] = true;

		$this->options = $options;

		$this->client = new Client($this->options);
	}

	/**
	 * @param string|null $personalNumber The personal number of the user. String. 12 digits. Century must be included.
	 * @return OrderResponse
	 * @throws ClientException
	 */
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

	/**
	 * @param string $orderRef Used to collect the status of the order.
	 * @return CollectResponse
	 * @throws ClientException
	 */
	public function collectResponse($orderRef)
	{
		$responseData = $this->client->post('collect', ['json' => ['orderRef' => $orderRef]]);

		return new CollectResponse($responseData);
	}
}