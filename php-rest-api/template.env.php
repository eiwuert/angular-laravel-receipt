<?php
define('STAGE_PRD', 'production');
define('STAGE_DEV', 'development');

return array(
    /*
    |--------------------------------------------------------------------------
    | Configuration stage
    |--------------------------------------------------------------------------
    | Use STAGE_DEV for local development
    | Use STAGE_PRD otherwise
    |
    */
    'STAGE' => STAGE_DEV,

    /*
    |--------------------------------------------------------------------------
    | Configuration for Database connection
    |--------------------------------------------------------------------------
    |
    */
    'MYSQL_HOST'     => 'localhost',
    'MYSQL_DB'       => 'spa',
    'MYSQL_USER'     => 'root',
    'MYSQL_PASSWORD' => '',

    /*
    |--------------------------------------------------------------------------
    | Configuration for App
    |--------------------------------------------------------------------------
    |
    */
    'APP_CLIENT_URL'          => '',
    'APP_UNIVERSAL_MAIL_ADDR' => '',
    'APP_UNIVERSAL_MAIL_PASS' => '',

    /*
    |--------------------------------------------------------------------------
    | Configuration for Siphon and PushGun server
    |--------------------------------------------------------------------------
    | GCM key is currently not in use
    |
    */
    'PUSH_SERVER_HOST' => 'https://push.receiptclub.com',
    'PUSH_SERVER_PORT' => '',
    'PUSH_WEB_API'     => '/sock-message',
    'PUSH_MOBILE_API'  => '/gcm-message',
    'PUSH_API_KEY'     => '',

    'PUSH_SIPHON_HOST'    => 'https://siphon.receiptclub.com',
    'PUSH_SIPHON_PORT'    => '',
    'PUSH_SIPHON_OCR_API' => '/processed-receipt',
    'PUSH_SIPHON_KEY'     => '',

    'PUSH_GCM_KEY' => '',

    /*
    |--------------------------------------------------------------------------
    | Configuration for OCR and IMAP server
    |--------------------------------------------------------------------------
    |
    */
    'GLB_OCR_SERVER_URL'   => 'internal-Pro-Ocr-Internal-1738793434.us-west-2.elb.amazonaws.com',
    'GLB_OCR_SERVER_PORT'  => 4737,
    'GLB_IMAP_SERVER_URL'  => '127.0.0.1',
    'GLB_IMAP_SERVER_PORT' => 4730,
    'GLB_LOCAL_GEARMAN_URL'    => '127.0.0.1',
    'GLB_LOCAL_GEARMAN_PORT'   => 4730,

    /*
    |--------------------------------------------------------------------------
    | Configuration for AWS
    |--------------------------------------------------------------------------
    | - AWS bucket naming
    | - Default server role account
    | - Default client role account
    | - Addition set of clients' keys for shuffling
    | - AWS region and subnet of OCR for merchant synchronization
    */
    'AWS_BUCKET_MANUAL'   => 'manualReceipt',
    'AWS_BUCKET_RECEIPT'  => 'processed-receipts',
    'AWS_BUCKET_FILE'     => 'filesStorage',
    'AWS_BUCKET_INCOMING' => 'incomingBucket',
    'AWS_BUCKET_MERCHANT' => 'rci-us-west-2-merchant-logos',

    'AWS_USER_SERVER_ID'  => 'AKIAJHMA7DHCVUBM2BYA',
    'AWS_USER_SERVER_KEY' => 'JpwlshMYeD4M0jFpCczMlYU735vnHjbeMJKyHSJp',

    'AWS_USER_CLIENT_ID'  => 'AKIAI3QRT7QGIFFQPM4A',
    'AWS_USER_CLIENT_KEY' => 'Gv2Bv6Dn537pYW7QY70iQQNEhq01PJw6lEVBpB+h',

    'AWS_EXT_CLIENT_NUM'  => 0,

    'AWS_REGION'          => 'us-east-1',
    'AWS_VPC_REGION'      => 'us-west-2',
    'AWS_OCR_SUBNET_ID'   => 'subnet-c7d711b0',

);
