<?php
use GuzzleHttp\Client as GuzzleClient;

class Push {

    /**
     * Push message to web clients
     *
     * @param $message  string   Message content
     * @param $event    string   Event name
     * @param $userID   int
     */
    public static function toWeb ($message, $event, $userID)
    {
        $gzclient   = new GuzzleClient();
        $configPush = Config::get('push.pushServer');
        $api        = $configPush['host'] . $configPush['web']['api'];

        //Prepare authentication token
        $uploadInfo = UploadInfo::find(intval($userID));

        //Call api push
        if ($uploadInfo) {
            $response = $gzclient->post($api, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $configPush['key']
                ],
                'json' => [
                    'connectionIdentifier' => $uploadInfo->SocketID,
                    'payload' => [
                        'event'   => $event,
                        'content' => $message
                    ],
                ],
                'future' => false,
                'verify' => false
            ]);
        }
    }

    /**
     * Push message to mobile devices
     *
     * @param $message  string   Message content
     * @param $userID   int
     */
    public static function toMobile ($message, $userID)
    {
        $gzclient   = new GuzzleClient();
        $configPush = Config::get('push.pushServer');
        $api        = $configPush['host'] . $configPush['mobile']['api'];

        //Prepare list of device tokens
        $androidTokens = User::getAndroidDeviceTokens($userID);

        //Call api push
        if (is_array($androidTokens) && count($androidTokens) > 0) {
            $gzclient->post($api, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $configPush['key']
                ],
                'json' => [
                    'deviceTokens' => $androidTokens,
                    'payload'      => ['message' => $message]
                ],
                'future' => false,
                'verify' => false
            ]);
        }
    }
}
