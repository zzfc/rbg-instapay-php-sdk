<?php

namespace RbgInstapay;

/**
 * API Response Handler
 */
class Response
{
    /**
     * @var array Response data
     */
    private $data;

    /**
     * @var int HTTP status code
     */
    private $statusCode;

    /**
     * Constructor
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    public function __construct(array $data, int $statusCode = 200)
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
    }

    /**
     * Check if response is successful
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        $code = $this->data['code'] ?? null;
        return $code === Constants::RESPONSE_CODE_SUCCESS || $code === '200';
    }

    /**
     * Get response code
     * 
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }

    /**
     * Get response status
     * 
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    /**
     * Get response message
     * 
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? $this->data['data']['message'] ?? null;
    }

    /**
     * Get response data
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data['data'] ?? [];
    }

    /**
     * Get token (for GetToken response)
     * 
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->data['token'] ?? null;
    }

    /**
     * Get instruction ID (for transaction responses)
     * 
     * @return string|null
     */
    public function getInstructionId(): ?string
    {
        $data = $this->getData();
        return $data['instruction_id'] ?? $data['InstructionId'] ?? null;
    }

    /**
     * Get transaction status
     * 
     * @return string|null
     */
    public function getTransactionStatus(): ?string
    {
        $data = $this->getData();
        return $data['status'] ?? $data['TransactionStatus'] ?? null;
    }

    /**
     * Get reason code (for rejected transactions)
     * 
     * @return string|null
     */
    public function getReasonCode(): ?string
    {
        $data = $this->getData();
        return $data['reason_code'] ?? $data['ReasonCode'] ?? null;
    }

    /**
     * Get full response array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get HTTP status code
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
