<?php

class LogDebug
{
    public static function write ($content)
    {
        $path     = base_path();
        $file 	  = $path . '/scripts/log_debug.txt';
        $content  = $content . "\n" ;
        file_put_contents($file, $content, FILE_APPEND);
    }
}