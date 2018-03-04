<?php

namespace apisnetworks\Beacon;

class Client {
	const ENDPOINT = 'http://localhost:2082/soap';
	const WSDL = '/apnscp.wsdl';

	/**
	 * @var \SoapClient
	 */
	protected $client;

	protected $key;

	public function __construct($key, $endpoint, array $options = []) {
		$this->setKey($key);
		$options['location'] = $endpoint . '?authkey=' . $this->key;
		$this->client = new \SoapClient(
			static::makeWSDL($endpoint),
			$options
		);
	}


	protected static function makeWSDL($endpoint) {
		return dirname($endpoint) . static::WSDL;
	}

	public function __call($method, $args = null) {
		if (empty($this->key)) {
			throw new \RuntimeException("no API key set");
		}
		try {
			$response = $this->client->__soapCall($method, $args);
		} catch (\SoapFault $e) {
			if (strstr($e->getMessage(), "is not a valid method for this service")) {
				// WSDL determines whether method may be invoked before sending
				throw $e;
			}
			if ($this->client->trace) {
				print ">>> REQUEST HEADERS: ";
				print $this->client->__getLastRequestHeaders();
				print PHP_EOL . PHP_EOL;
				print "<<< RESPONSE BODY: ";
				print $this->client->__getLastResponse();
			}
			throw $e;
		}
		return $response;

	}

	public function setKey($key) {
		$this->key = preg_replace('/[\s-]+/', '', $key);
	}

	public function getKey() {
		return $this->key;
	}
}