<?php

namespace RbgInstapay;

use Exception;
use RbgInstapay\Exception\RbgInstapayException;
use RbgInstapay\InwardTransactionHandler;

/**
 * RBG Instapay Callback Handler
 * 
 * Handles incoming callbacks from RBG Instapay service
 */
class CallbackHandler
{
    /**
     * @var string Secret key for JWT token generation
     */
    private $secretKey;

    /**
     * @var callable Callback function for processing service responses
     */
    private $serviceResponseHandler;

    /**
     * @var callable Callback function for processing service requests
     */
    private $serviceRequestHandler;

    /**
     * @var InwardTransactionHandler Inward transaction handler
     */
    private $inwardTransactionHandler;

    /**
     * Constructor
     * 
     * @param string $secretKey Secret key for JWT token generation (HS256)
     */
    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Handle GetToken callback request
     * 
     * This endpoint is called first for authentication.
     * Partner must respond with a token generated using HS256 Algorithm.
     * 
     * @param array $requestData Request data from RBG
     * @return array Response with token
     * @throws RbgInstapayException
     */
    public function handleGetToken(array $requestData): array
    {
        // Generate JWT token using HS256 algorithm
        // Include timestamp and expiration in payload
        $payload = [
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600, // 1 hour expiration
            'jti' => uniqid('', true),
            'identity' => $requestData['partner_uuid'] ?? uniqid('', true),
            'fresh' => false,
            'type' => 'access',
        ];

        $token = $this->generateJWTToken($payload);

        return [
            'code' => Constants::RESPONSE_CODE_SUCCESS,
            'status' => 'Success',
            'token' => $token,
            'data' => [
                'message' => 'Approved',
            ],
        ];
    }

    /**
     * Verify JWT token
     * 
     * @param string $token JWT token
     * @return bool
     */
    public function verifyToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secretKey, true);
        $expectedSignature = $this->base64UrlEncode($signature);

        return hash_equals($expectedSignature, $signatureEncoded);
    }

    /**
     * Generate JWT token using HS256 algorithm
     * 
     * @param array $payload Token payload
     * @return string JWT token
     * @throws RbgInstapayException
     */
    private function generateJWTToken(array $payload): string
    {
        if (!function_exists('hash_hmac')) {
            throw new RbgInstapayException('hash_hmac function is required for JWT token generation');
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secretKey, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     * 
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Handle Service Response callback
     * 
     * This endpoint receives transaction status updates for outward transactions.
     * Partner must verify if the instruction ID is valid.
     * 
     * @param array $requestData Request data from RBG
     * @return array Response
     * @throws RbgInstapayException
     */
    public function handleServiceResponse(array $requestData): array
    {
        if (!$this->serviceResponseHandler) {
            throw new RbgInstapayException('Service response handler not set');
        }

        // Verify instruction ID
        $instructionId = $requestData['instruction_id'] ?? 
                        $requestData['InstructionId'] ?? 
                        $requestData['data']['instruction_id'] ?? null;
        
        if (!$instructionId) {
            return [
                'code' => Constants::RESPONSE_CODE_ERROR,
                'status' => 'Error',
                'message' => 'Missing instruction_id',
            ];
        }

        // Call custom handler
        try {
            $result = call_user_func($this->serviceResponseHandler, $requestData);
        } catch (Exception $e) {
            return [
                'code' => Constants::RESPONSE_CODE_INTERNAL_ERROR,
                'status' => 'Error',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'code' => '200',
            'status' => 'Success',
            'data' => $result,
        ];
    }

    /**
     * Handle Service Request callback
     * 
     * This endpoint receives incoming transaction requests (inward transactions).
     * Partner must verify transaction details and respond accordingly.
     * 
     * @param array $requestData Request data from RBG
     * @return array Response
     * @throws RbgInstapayException
     */
    public function handleServiceRequest(array $requestData): array
    {
        // 如果设置了 InwardTransactionHandler，使用它来处理
        if ($this->inwardTransactionHandler) {
            try {
                $result = $this->inwardTransactionHandler->processTransaction($requestData);
                
                if (isset($result['reject']) && $result['reject']) {
                    return [
                        'code' => Constants::STATUS_RJCT,
                        'status' => 'Rejected',
                        'reason_code' => $result['reason_code'] ?? Constants::REASON_CODE_DS04,
                        'reason_description' => $result['reason_description'] ?? 'OrderRejected',
                        'message' => $result['message'] ?? null,
                    ];
                }

                return [
                    'code' => Constants::STATUS_ACTC,
                    'status' => 'Accepted',
                    'data' => $result['data'] ?? [],
                ];
            } catch (Exception $e) {
                return [
                    'code' => Constants::STATUS_RJCT,
                    'status' => 'Rejected',
                    'reason_code' => Constants::REASON_CODE_DS04,
                    'reason_description' => 'OrderRejected',
                    'message' => $e->getMessage(),
                ];
            }
        }

        // 如果没有设置 InwardTransactionHandler，使用自定义处理器
        if (!$this->serviceRequestHandler) {
            throw new RbgInstapayException('Service request handler or InwardTransactionHandler must be set');
        }

        // Call custom handler
        try {
            $result = call_user_func($this->serviceRequestHandler, $requestData);
        } catch (Exception $e) {
            return [
                'code' => Constants::STATUS_RJCT,
                'status' => 'Rejected',
                'reason_code' => Constants::REASON_CODE_DS04,
                'reason_description' => 'OrderRejected',
                'message' => $e->getMessage(),
            ];
        }

        // Return appropriate response based on handler result
        if (isset($result['reject']) && $result['reject']) {
            return [
                'code' => Constants::STATUS_RJCT,
                'status' => 'Rejected',
                'reason_code' => $result['reason_code'] ?? Constants::REASON_CODE_DS04,
                'reason_description' => $result['reason_description'] ?? 'OrderRejected',
            ];
        }

        return [
            'code' => Constants::STATUS_ACTC,
            'status' => 'Accepted',
            'data' => $result,
        ];
    }

    /**
     * Set service response handler
     * 
     * @param callable $handler
     */
    public function setServiceResponseHandler(callable $handler): void
    {
        $this->serviceResponseHandler = $handler;
    }

    /**
     * Set service request handler
     * 
     * @param callable $handler
     */
    public function setServiceRequestHandler(callable $handler): void
    {
        $this->serviceRequestHandler = $handler;
    }

    /**
     * Set inward transaction handler
     * 
     * @param InwardTransactionHandler $handler
     */
    public function setInwardTransactionHandler(InwardTransactionHandler $handler): void
    {
        $this->inwardTransactionHandler = $handler;
    }

    /**
     * Get inward transaction handler
     * 
     * @return InwardTransactionHandler|null
     */
    public function getInwardTransactionHandler(): ?InwardTransactionHandler
    {
        return $this->inwardTransactionHandler;
    }

    /**
     * Process incoming callback request
     * 
     * This method routes the request to the appropriate handler based on the endpoint.
     * 
     * @param string $endpoint Endpoint path
     * @param array $requestData Request data
     * @return array Response
     * @throws Exception
     */
    public function processCallback(string $endpoint, array $requestData): array
    {
        switch ($endpoint) {
            case '/ips-payments/service-responses/GetToken':
            case 'GetToken':
                return $this->handleGetToken($requestData);

            case '/ips-payments/service-responses':
            case 'service-responses':
                return $this->handleServiceResponse($requestData);

            case '/ips-payments/service-requests':
            case 'service-requests':
                return $this->handleServiceRequest($requestData);

            default:
                throw new RbgInstapayException("Unknown callback endpoint: {$endpoint}");
        }
    }
}
