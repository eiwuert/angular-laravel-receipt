<?php

class PushBackground
{
    public static function send ($uid, $app, $action, $itemIds) 
    {
        $command = 'php ../scripts/push_sync.php ' . $uid . ' ' . $app .  ' "' . $action . '" "' . $itemIds . '"';
        if ($_ENV['STAGE'] != STAGE_DEV) {
            $command .= ' > /dev/null 2>/dev/null &';
        }

        shell_exec($command);
    }
}
