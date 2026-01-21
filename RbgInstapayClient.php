<?php

namespace RbgInstapay;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RbgInstapay\Exception\ApiException;
use RbgInstapay\Exception\AuthenticationException;
use RbgInstapay\Exception\RbgInstapayException;

/**
 * RBG Instapay ISO20022 API Client
 * 
 * PHP SDK for RBG Instapay ISO20022 Service Endpoints
 */
class RbgInstapayClient
{
    /**
     * @var string API Base URL
     */
    private $baseUrl;

    /**
     * @var string JWT Token
     */
    private $token;

    /**
     * @var Client HTTP Client
     */
    private $httpClient;

    /**
     * @var array Configuration
     */
    private $config;

    /**
     * Constructor
     * 
     * @param array $config Configuration array
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->baseUrl = $config['base_url'] ?? $this->getDefaultBaseUrl($config['environment'] ?? 'uat');
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Validate configuration
     * 
     * @param array $config
     * @throws RbgInstapayException
     */
    private function validateConfig(array $config)
    {
        $required = ['username', 'password', 'partner_uuid', 'partner_id'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new RbgInstapayException("Missing required configuration: {$key}");
            }
        }

        if (empty($config['base_url']) && empty($config['environment'])) {
            $config['environment'] = 'uat';
        }
    }

    /**
     * Get default base URL based on environment
     * 
     * @param string $environment
     * @return string
     */
    private function getDefaultBaseUrl(string $environment): string
    {
        $urls = [
            'uat' => 'https://public-uat-partners.rbsoftech.online:7443/api/uat/v1',
            'production' => '', // To be provided via email
        ];

        return $urls[$environment] ?? $urls['uat'];
    }

    /**
     * Get authentication token
     * 
     * @param bool $forceRefresh Force refresh token even if cached
     * @return string
     * @throws AuthenticationException
     */
    public function getToken(bool $forceRefresh = false): string
    {
        if ($this->token && !$forceRefresh) {
            return $this->token;
        }

        try {
            $response = $this->httpClient->post('/GetToken', [
                'json' => [
                    'username' => $this->config['username'],
                    'password' => $this->config['password'],
                    'partner_uuid' => $this->config['partner_uuid'],
                    'partner_id' => $this->config['partner_id'],
                    'data' => new \stdClass(),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['token']) || $body['code'] !== Constants::RESPONSE_CODE_SUCCESS) {
                $errorMessage = $body['status'] ?? $body['message'] ?? 'Unknown error';
                throw new AuthenticationException('Failed to get token: ' . $errorMessage, 0, null, $body);
            }

            $this->token = $body['token'];
            return $this->token;
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Token request failed: ' . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * Make authenticated request
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return Response
     * @throws ApiException
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $token = $this->getToken();

        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);
            
            return new Response($body, $statusCode);
        } catch (GuzzleException $e) {
            // If token expired, try once more with refreshed token
            if ($e->getCode() === 401) {
                $token = $this->getToken(true);
                $options['headers']['Authorization'] = 'Bearer ' . $token;
                
                try {
                    $response = $this->httpClient->request($method, $endpoint, $options);
                    $statusCode = $response->getStatusCode();
                    $body = json_decode($response->getBody()->getContents(), true);
                    return new Response($body, $statusCode);
                } catch (GuzzleException $retryException) {
                    throw new ApiException('API request failed: ' . $retryException->getMessage(), $retryException->getCode());
                }
            }
            
            throw new ApiException('API request failed: ' . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get P2P QR Details
     * 
     * @param string $qrString QR code raw string
     * @param string|null $gatewayCode Gateway code (optional, auto-detected if not provided)
     * @return Response
     * @throws ApiException
     */
    public function getP2PQrDetails(string $qrString, ?string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_P2P_QR_DETAILS,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'rawString' => $qrString,
                ],
            ],
        ];

        return $this->makeRequest('POST', '/CallGateway', $data);
    }

    /**
     * Send P2P Transaction
     * 
     * @param array $params Transaction parameters:
     *   - amount (float): Transaction amount
     *   - currency (string): Currency code (default: PHP)
     *   - debtor_account (array): Sender account info
     *   - creditor_account (array): Receiver account info
     *   - remittance_information (string): Payment description
     *   - instruction_id (string, optional): Custom instruction ID
     * @param string|null $gatewayCode Gateway code (optional)
     * @return Response
     * @throws ApiException
     */
    public function sendP2PTransaction(array $params, ?string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        // Validate required parameters
        $this->validateP2PParams($params);

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_P2P_TRANSFER,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'amount' => number_format($params['amount'], 2, '.', ''),
                    'currency' => $params['currency'] ?? Constants::CURRENCY_PHP,
                    'debtor_account' => $params['debtor_account'],
                    'creditor_account' => $params['creditor_account'],
                    'remittance_information' => $params['remittance_information'] ?? '',
                ],
            ],
        ];

        // Add optional instruction_id if provided
        if (isset($params['instruction_id'])) {
            $data['data']['body']['instruction_id'] = $params['instruction_id'];
        }

        return $this->makeRequest('POST', '/CallGateway', $data);
    }

    /**
     * Generate QR P2P Code
     * 
     * @param array $params QR generation parameters:
     *   - account_number (string): Account number
     *   - account_name (string): Account holder name
     *   - account_type (string): Account type (SA/CA)
     *   - bank_code (string): Bank code
     *   - amount (float, optional): Fixed amount
     *   - city (string, optional): City
     *   - postal_code (string, optional): Postal code
     * @param string|null $gatewayCode Gateway code (optional)
     * @return Response
     * @throws ApiException
     */
    public function generateQRP2P(array $params, string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        $this->validateQRP2PParams($params);

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_QR_P2P_GENERATE,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'account_number' => $params['account_number'],
                    'account_name' => $params['account_name'],
                    'account_type' => $params['account_type'],
                    'bank_code' => $params['bank_code'],
                ],
            ],
        ];

        // Add optional fields
        if (isset($params['amount'])) {
            $data['data']['body']['amount'] = number_format($params['amount'], 2, '.', '');
        }
        if (isset($params['city'])) {
            $data['data']['body']['city'] = $params['city'];
        }
        if (isset($params['postal_code'])) {
            $data['data']['body']['postal_code'] = $params['postal_code'];
        }

        return $this->makeRequest('POST', '/CallGateway', $data);
    }

    /**
     * Generate QR P2M Code
     * 
     * @param array $params QR generation parameters:
     *   - merchant_name (string): Merchant name
     *   - merchant_account (string): Merchant account number
     *   - merchant_id (string): Merchant ID
     *   - amount (float, optional): Fixed amount
     *   - city (string, optional): City
     *   - postal_code (string, optional): Postal code
     *   - reference_label (string, optional): Reference label
     * @param string|null $gatewayCode Gateway code (optional)
     * @return Response
     * @throws ApiException
     */
    public function generateQRP2M(array $params, string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        $this->validateQRP2MParams($params);

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_QR_P2M_GENERATE,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'merchant_name' => $params['merchant_name'],
                    'merchant_account' => $params['merchant_account'],
                    'merchant_id' => $params['merchant_id'],
                ],
            ],
        ];

        // Add optional fields
        if (isset($params['amount'])) {
            $data['data']['body']['amount'] = number_format($params['amount'], 2, '.', '');
        }
        if (isset($params['city'])) {
            $data['data']['body']['city'] = $params['city'];
        }
        if (isset($params['postal_code'])) {
            $data['data']['body']['postal_code'] = $params['postal_code'];
        }
        if (isset($params['reference_label'])) {
            $data['data']['body']['reference_label'] = $params['reference_label'];
        }

        return $this->makeRequest('POST', '/CallGateway', $data);
    }

    /**
     * Get QR P2M Details
     * 
     * @param string $qrString QR code raw string
     * @param string|null $gatewayCode Gateway code (optional)
     * @return Response
     * @throws ApiException
     */
    public function getQRP2MDetails(string $qrString, string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_QR_P2M_DETAILS,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'rawString' => $qrString,
                ],
            ],
        ];

        return $this->makeRequest('POST', '/CallGateway', $data);
    }

    /**
     * Get gateway code based on environment
     * 
     * @return string
     */
    private function getGatewayCode(): string
    {
        $environment = $this->config['environment'] ?? 'uat';
        return $environment === 'production' 
            ? Constants::GATEWAY_CODE_PRODUCTION 
            : Constants::GATEWAY_CODE_UAT;
    }

    /**
     * Validate P2P transaction parameters
     * 
     * @param array $params
     * @throws ApiException
     */
    private function validateP2PParams(array $params): void
    {
        $required = ['amount', 'debtor_account', 'creditor_account'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new ApiException("Missing required parameter: {$key}");
            }
        }

        if (!isset($params['amount']) || $params['amount'] <= 0) {
            throw new ApiException("Invalid amount: must be greater than 0");
        }

        if (!isset($params['debtor_account']['account_number']) || 
            !isset($params['debtor_account']['account_type'])) {
            throw new ApiException("Invalid debtor_account: account_number and account_type are required");
        }

        if (!isset($params['creditor_account']['account_number']) || 
            !isset($params['creditor_account']['account_type']) ||
            !isset($params['creditor_account']['bank_code'])) {
            throw new ApiException("Invalid creditor_account: account_number, account_type, and bank_code are required");
        }
    }

    /**
     * Validate QR P2P parameters
     * 
     * @param array $params
     * @throws ApiException
     */
    private function validateQRP2PParams(array $params): void
    {
        $required = ['account_number', 'account_name', 'account_type', 'bank_code'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new ApiException("Missing required parameter: {$key}");
            }
        }
    }

    /**
     * Validate QR P2M parameters
     * 
     * @param array $params
     * @throws ApiException
     */
    private function validateQRP2MParams(array $params): void
    {
        $required = ['merchant_name', 'merchant_account', 'merchant_id'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new ApiException("Missing required parameter: {$key}");
            }
        }
    }

    /**
     * Set callback URL for transaction notifications
     * 
     * @param string $callbackUrl Callback URL
     * @return bool
     */
    public function setCallbackUrl(string $callbackUrl): bool
    {
        $this->config['callback_url'] = $callbackUrl;
        return true;
    }

    /**
     * Get callback URL
     * 
     * @return string|null
     */
    public function getCallbackUrl(): ?string
    {
        return $this->config['callback_url'] ?? null;
    }

    /**
     * Get API Transactions (Reports)
     * 
     * Fetch a comprehensive list of transactions made by a partner within a specified date range.
     * 
     * @param array $params Query parameters:
     *   - start_date (string): Start date (format: YYYY-MM-DD)
     *   - end_date (string): End date (format: YYYY-MM-DD)
     *   - status (string, optional): Transaction status filter
     *   - page (int, optional): Page number for pagination
     *   - limit (int, optional): Number of records per page
     * @param string|null $gatewayCode Gateway code (optional)
     * @return Response
     * @throws ApiException
     */
    public function getApiTransactions(array $params, string $gatewayCode = null): Response
    {
        $gatewayCode = $gatewayCode ?? $this->getGatewayCode();

        // Validate required parameters
        if (!isset($params['start_date']) || !isset($params['end_date'])) {
            throw new ApiException('Missing required parameters: start_date and end_date are required');
        }

        // Validate date format
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        
        if (!$this->validateDate($startDate) || !$this->validateDate($endDate)) {
            throw new ApiException('Invalid date format. Expected format: YYYY-MM-DD');
        }

        $data = [
            'data' => [
                'partner_id' => $this->config['partner_id'],
                'endpoint' => Constants::ENDPOINT_API_TRANSACTIONS,
                'gateway_code' => $gatewayCode,
                'partner_uuid' => $this->config['partner_uuid'],
                'body' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ];

        // Add optional parameters
        if (isset($params['status'])) {
            $data['data']['body']['status'] = $params['status'];
        }
        if (isset($params['page'])) {
            $data['data']['body']['page'] = (int)$params['page'];
        }
        if (isset($params['limit'])) {
            $data['data']['body']['limit'] = (int)$params['limit'];
        }

        return $this->makeRequest('GET', '/CallGateway', $data);
    }

    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Date format
     * @return bool
     */
    private function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Get Transaction Query helper
     * 
     * @return TransactionQuery
     */
    public function transactions(): TransactionQuery
    {
        return new TransactionQuery($this);
    }
}
