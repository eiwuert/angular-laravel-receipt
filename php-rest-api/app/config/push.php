<?php

return array(
    'pushServer' => array(
        'host'      => $_ENV['PUSH_SERVER_HOST'],
        'port'      => $_ENV['PUSH_SERVER_PORT'],
        'web'       => array(
            'api'   => $_ENV['PUSH_WEB_API'],
        ),
        'mobile'    => array(
            'api'   => $_ENV['PUSH_MOBILE_API']
        ),
        'key'       => $_ENV['PUSH_API_KEY']
    ),
    'uploadServer'  => array(
        'host'      => $_ENV['PUSH_SIPHON_HOST'],
        'port'      => $_ENV['PUSH_SIPHON_PORT'],
        'ocrApi'    => $_ENV['PUSH_SIPHON_OCR_API'],
        'key'       => $_ENV['PUSH_SIPHON_KEY']
    ),
    'gcm' => array(
        'apiKey' => $_ENV['PUSH_GCM_KEY'],
    ),
);
