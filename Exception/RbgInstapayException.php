<?php

namespace RbgInstapay\Exception;

use Exception;

/**
 * Base exception for RBG Instapay SDK
 */
class RbgInstapayException extends Exception
{
    /**
     * @var array Additional error data
     */
    private $errorData;

    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @param array $errorData Additional error data
     */
    public function __construct(string $message = "", int $code = 0, Exception $previous = null, array $errorData = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
    }

    /**
     * Get error data
     * 
     * @return array
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
