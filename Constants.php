<?php

namespace RbgInstapay;

/**
 * RBG Instapay Constants
 */
class Constants
{
    // Gateway Codes
    const GATEWAY_CODE_UAT = 'INSTAPAYISOUAT';
    const GATEWAY_CODE_PRODUCTION = 'INSTAPAYISOPROD';

    // Endpoints
    const ENDPOINT_P2P_QR_DETAILS = 'p2p-qrDetails';
    const ENDPOINT_P2P_TRANSFER = 'p2p-transfer';
    const ENDPOINT_QR_P2P_GENERATE = 'qr-p2p-generate';
    const ENDPOINT_QR_P2M_GENERATE = 'qr-p2m-generate';
    const ENDPOINT_QR_P2M_DETAILS = 'qr-p2m-details';
    
    // Other Endpoints
    const ENDPOINT_API_TRANSACTIONS = 'api-transactions';

    // Transaction Status
    const STATUS_ACCEPTED = 'Accepted';
    const STATUS_ACTC = 'ACTC';
    const STATUS_RJCT = 'RJCT';

    // Account Types
    const ACCOUNT_TYPE_SA = 'SA'; // Savings Account
    const ACCOUNT_TYPE_CA = 'CA'; // Current Account

    // Currency
    const CURRENCY_PHP = 'PHP';

    // Reason Codes (Common rejection codes)
    const REASON_CODE_AC01 = 'AC01'; // IncorrectAccountNumber
    const REASON_CODE_AC03 = 'AC03'; // InvalidCreditorAccountNumber
    const REASON_CODE_AC04 = 'AC04'; // ClosedAccountNumber
    const REASON_CODE_AM02 = 'AM02'; // NotAllowedAmount
    const REASON_CODE_AM04 = 'AM04'; // InsufficientFunds
    const REASON_CODE_AM09 = 'AM09'; // WrongAmount
    const REASON_CODE_AM11 = 'AM11'; // InvalidTransactionCurrency
    const REASON_CODE_AM12 = 'AM12'; // InvalidAmount
    const REASON_CODE_DU03 = 'DU03'; // DuplicateTransaction
    const REASON_CODE_DS04 = 'DS04'; // OrderRejected

    // Response Codes
    const RESPONSE_CODE_SUCCESS = '201';
    const RESPONSE_CODE_ERROR = '400';
    const RESPONSE_CODE_UNAUTHORIZED = '401';
    const RESPONSE_CODE_NOT_FOUND = '404';
    const RESPONSE_CODE_METHOD_NOT_ALLOWED = '405';
    const RESPONSE_CODE_INTERNAL_ERROR = '500';
}
