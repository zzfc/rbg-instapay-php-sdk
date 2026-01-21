<?php

namespace RbgInstapay;

/**
 * Transaction Query Helper
 * 
 * 交易查询辅助类，用于处理交易报告和查询
 */
class TransactionQuery
{
    /**
     * @var RbgInstapayClient
     */
    private $client;

    /**
     * Constructor
     * 
     * @param RbgInstapayClient $client
     */
    public function __construct(RbgInstapayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get transactions by date range
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param array $options Additional options:
     *   - status (string): Filter by status
     *   - page (int): Page number
     *   - limit (int): Records per page
     * @return Response
     * @throws Exception\ApiException
     */
    public function getByDateRange(string $startDate, string $endDate, array $options = []): Response
    {
        $params = array_merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $options);

        return $this->client->getApiTransactions($params);
    }

    /**
     * Get transactions by status
     * 
     * @param string $status Transaction status (ACTC, RJCT, Accepted, etc.)
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return Response
     * @throws Exception\ApiException
     */
    public function getByStatus(string $status, string $startDate, string $endDate): Response
    {
        return $this->client->getApiTransactions([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    /**
     * Get successful transactions
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return Response
     * @throws Exception\ApiException
     */
    public function getSuccessful(string $startDate, string $endDate): Response
    {
        return $this->getByStatus(Constants::STATUS_ACTC, $startDate, $endDate);
    }

    /**
     * Get rejected transactions
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return Response
     * @throws Exception\ApiException
     */
    public function getRejected(string $startDate, string $endDate): Response
    {
        return $this->getByStatus(Constants::STATUS_RJCT, $startDate, $endDate);
    }

    /**
     * Get transactions with pagination
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int $page Page number
     * @param int $limit Records per page
     * @return Response
     * @throws Exception\ApiException
     */
    public function getPaginated(string $startDate, string $endDate, int $page = 1, int $limit = 50): Response
    {
        return $this->client->getApiTransactions([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
