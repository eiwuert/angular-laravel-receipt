<?php

//use ElephantIO\Client as ElephantClient;
//use Sly\NotificationPusher\Adapter\Gcm as GcmAdapter;
use Aws\Common\Aws;
/*
App::bind('pushService', function($app)
{
    $host = Config::get('push.pushServer.host');
    $port = Config::get('push.pushServer.port');
    $mode = Config::get('push.mode');
    $checkSslPeer = $mode === PushService::MODE_DEV ? false : true;
    
    $socketClient = new ElephantClient($host . ':' . $port, 'socket.io', 1, false, $checkSslPeer, true);
    $gcmAdapter = new GcmAdapter(array(
        'apiKey' => Config::get('push.gcm.apiKey'),
    ));

    return new PushService($socketClient, $gcmAdapter, $mode);
});
*/

App::bind('awsWebClient', function($app)
{
    // Create a service builder using a configuration file
    $aws = Aws::factory(array(
        'key'    => Config::get('aws::config.clientAccessKey'),
        'secret' => Config::get('aws::config.clientSecretKey'),
    ));

    return $aws;
});
