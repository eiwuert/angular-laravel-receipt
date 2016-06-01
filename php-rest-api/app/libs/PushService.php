<?php

use ElephantIO\Client as ElephantClient;
use Sly\NotificationPusher\PushManager;
use Sly\NotificationPusher\Adapter\Gcm as GcmAdapter;
use Sly\NotificationPusher\Collection\DeviceCollection;
use Sly\NotificationPusher\Model\Device;
use Sly\NotificationPusher\Model\Message;
use Sly\NotificationPusher\Model\Push;

/**
 * Service to push messages.
 * 
 */
class PushService
{
    const MODE_DEV = 'dev';
    const MODE_PROD = 'prod';
    
    /**
     * Push event.
     */
    const EVENT = 'sendMessage';
    
    /**
     * Push client object.
     * 
     * @var \ElephantIO\Client
     */
    protected $pushClient;
    
    /**
     * Mode.
     * 
     * @param string
     */
    protected $mode;
    
    /**
     * Mobile push manager
     * 
     * @var PushManager
     */
    protected $pushManager;
    
    /**
     * GCM adapter to push notification.
     * 
     * @var \Sly\NotificationPusher\Adapter\Gcm
     */
    protected $gcmAdapter;
    
    /**
     * Initialize object.
     * 
     * @param ElephantClient $pushClient Websocket push client
     * @param GcmAdapter $gcmAdapter
     * @param string $mode Default is dev
     * @return void
     */
    public function __construct(ElephantClient $pushClient, GcmAdapter $gcmAdapter, $mode = null)
    {
        $this->pushClient = $pushClient;
        $this->gcmAdapter = $gcmAdapter;
        
        if (null === $mode) {
            $mode = static::MODE_DEV;
        }
        
        $this->setMode($mode);
    }
    
    /**
     * Set mode.
     * 
     * @param string $mode
     * @return PushService
     */
    public function setMode($mode)
    {
        $this->mode = (string) $mode;
        
        return $this;
    }
    
    /**
     * Get push manager.
     * 
     * @return PushManager
     */
    public function getPushManager()
    {
        if (null === $this->pushManager) {
            $mode = (static::MODE_DEV === $this->mode) ? PushManager::ENVIRONMENT_DEV : PushManager::ENVIRONMENT_PROD;
            $this->pushManager = new PushManager($mode);
        }
        
        return $this->pushManager;
    }
    
    /**
     * Push to all clients.
     * 
     * @param string|array $message
     * @param string $event
     * @param User $user
     * @return PushService
     */
    public function push($message, $event, User $user, $pushTo = 'ALL')
    {
        if ($pushTo == 'ALL' || $pushTo == 'WEB') {
            $this->pushToWebSocket($message, $event, $user);
        }
        if ($pushTo == 'ALL' || $pushTo == 'MOBILE' || $pushTo == 'ANDROID') {
            $this->pushToAndroid($message, $event, $user);
        }
        return $this;
    }
    
    /**
     * Push a message to web socket.
     * 
     * @param string|array $message
     * @param string $event
     * @param User $user
     * @return PushService
     */
    public function pushToWebSocket($message, $event, User $user)
    {
        if ( ! is_string($message) && ! is_array($message)) {
            throw new \InvalidArgumentException(sprintf(
                'Message must be a string or an array, %s provided',
                (is_object($message) ? get_class($message) : gettype($message))
            ));
        }
        
        if (is_string($message)) {
            $message = array('message' => $message);
        }
        
        $message['event'] = $event;
        $message['user_id'] = $user->UserID;
        
        $this->pushClient->init();
        $this->pushClient->send(
            ElephantClient::TYPE_EVENT,
            null,
            null,
            json_encode(array('name' => static::EVENT, 'args' => $message))
        );
        $this->pushClient->close();
        
        return $this;
    }
    
    /**
     * Push a message to android devices.
     * 
     * @param string|array $message
     * @param string $event
     * @param User $user
     * @return PushService
     */
    public function pushToAndroid($message, $event, User $user, $excepts = array())
    {
        $deviceToken = $user->getAndroidDeviceTokens();
        
        $devices = array();
        foreach ($user->getAndroidDeviceTokens() as $token) {
            $devices[] = new Device($token);
        }
        $devices = new DeviceCollection($devices);

        // define message
        if (is_string($message)) {
            $messageContent = $message;
            $options = array();
        } else {
            $messageContent = $message['message'];
            $options = $message;
            unset($options['message']);
        }
        
        $message = new Message($messageContent, $options);
        
        $pushManager = $this->getPushManager();
        $gcmAdapter = $this->gcmAdapter;
        $gcmAdapter->setAdapterParameters(array('sslverifypeer' => false));
        
        $gcmPush = new Push($gcmAdapter, $devices, $message);
        $pushManager->add($gcmPush);
        $pushManager->push()->clear();;
        
        return $this;
    }
}
