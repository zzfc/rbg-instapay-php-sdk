<?php

/**
 * RBG Instapay Configuration Example
 * 
 * Copy this file to config.php and fill in your credentials
 */

return [
    // Environment: 'uat' or 'production'
    'environment' => 'uat',

    // API Base URL (optional, will use default if not set)
    // 'base_url' => 'https://public-uat-partners.rbsoftech.online:7443/api/uat/v1',

    // Authentication credentials (provided by RBG via email)
    'username' => 'your_username',
    'password' => 'your_password',
    'partner_uuid' => 'your_partner_uuid',
    'partner_id' => 12345,

    // Callback URL for receiving transaction notifications
    'callback_url' => 'https://your-domain.com/ips-payments/service-responses',

    // Secret key for JWT token generation in callbacks (HS256)
    'callback_secret_key' => 'your_secret_key_for_jwt_generation',
];
