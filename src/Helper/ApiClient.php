<?php

namespace App\Helper;

use Exception;

class ApiClient {
	const METHOD_GET = 'GET';
	const METHOD_PUT = 'PUT';
	const METHOD_POST = 'POST';
	const METHOD_DELETE = 'DELETE';
	protected $validMethods = [
		self::METHOD_GET,
		self::METHOD_PUT,
		self::METHOD_POST,
		self::METHOD_DELETE,
	];
	protected $apiUrl;
	protected $cURL;

	/**
	 * ApiClient constructor.
	 *
	 * @param string $apiUrl
	 * @param string $username
	 * @param string $apiKey
	 */
	public function __construct( $apiUrl, $username, $apiKey ) {
		$this->apiUrl = rtrim( $apiUrl, '/' ) . '/api/';
		//Initializes the cURL instance
		$this->cURL = curl_init();
		curl_setopt( $this->cURL, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->cURL, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $this->cURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $this->cURL, CURLOPT_USERPWD, $username . ':' . $apiKey );
		curl_setopt(
			$this->cURL,
			CURLOPT_HTTPHEADER,
			[ 'Content-Type: application/json; charset=utf-8' ]
		);
	}

	/**
	 * @return ApiClient
	 */
	public static function init() {
		$obj = new self( $_ENV['SHOPWARE5_BASE_URL'], $_ENV['SHOPWARE5_USER'], $_ENV['SHOPWARE5_API_KEY'] );

		return $obj;
	}

	/**
	 * @param string $url
	 * @param string $method
	 * @param array  $data
	 * @param array  $params
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function call( $url, $method = self::METHOD_GET, $data = [], $params = [] ) {
		if ( ! in_array( $method, $this->validMethods ) ) {
			throw new Exception( 'Invalid HTTP-Methode: ' . $method );
		}
		$queryString = '';
		if ( ! empty( $params ) ) {
			$queryString = http_build_query( $params );
		}
		$url        = rtrim( $url, '?' ) . '?';
		$url        = $this->apiUrl . $url . $queryString;
		$dataString = json_encode( $data );

		curl_setopt( $this->cURL, CURLOPT_URL, $url );
		curl_setopt( $this->cURL, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $this->cURL, CURLOPT_POSTFIELDS, $dataString );
		$result   = curl_exec( $this->cURL );
		$httpCode = curl_getinfo( $this->cURL, CURLINFO_HTTP_CODE );

		return $this->prepareResponse( $result, $httpCode );
	}

	/**
	 * @param string $url
	 * @param array  $params
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function get( $url, $params = [] ) {
		return $this->call( $url, self::METHOD_GET, [], $params );
	}

	/**
	 * @param string $url
	 * @param array  $data
	 * @param array  $params
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function post( $url, $data = [], $params = [] ) {
		return $this->call( $url, self::METHOD_POST, $data, $params );
	}

	/**
	 * @param string $url
	 * @param array  $data
	 * @param array  $params
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function put( $url, $data = [], $params = [] ) {
		return $this->call( $url, self::METHOD_PUT, $data, $params );
	}

	/**
	 * @param string $url
	 * @param array  $params
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function delete( $url, $params = [] ) {
		return $this->call( $url, self::METHOD_DELETE, [], $params );
	}

	protected function prepareResponse( $result, $httpCode ) {

		if ( ! $result ) {
			print( 'Could not get result from API' );

			return false;
		}
		if ( null === $decodedResult = json_decode( $result, true ) ) {
			$jsonErrors = [
				JSON_ERROR_NONE      => 'No error occurred',
				JSON_ERROR_DEPTH     => 'The maximum stack depth has been reached',
				JSON_ERROR_CTRL_CHAR => 'Control character issue, maybe wrong encoded',
				JSON_ERROR_SYNTAX    => 'Syntaxerror',
			];

			print( 'Could not decode json' );
			print( 'json_last_error: ' . $jsonErrors[ json_last_error() ] );
			print( 'Raw:' );
			print( print_r( $result, true ) );

			return false;
		}

		if ( ! isset( $decodedResult['success'] ) ) {
			print( 'Invalid Response' );

			return false;
		}

		if ( ! $decodedResult['success'] ) {
			print( 'No Success' );
			print( $decodedResult['message'] );

			if ( array_key_exists( 'errors', $decodedResult ) && is_array( $decodedResult['errors'] ) ) {
				print( $decodedResult['errors'] );
			}

			return false;
		}

		return $decodedResult;
	}
}
