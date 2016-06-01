<?php
	$files = scandir('styles/img/ads');
	$id = 1;
	foreach($files as $file) 
    { 
        if($file === '.' || $file === '..') {			
			continue;
		} 
		
		echo "<img id=\"advert-{$id}\" src=\"styles/img/ads/{$file}\" width=\"728\" height=\"90\" class=\"hide\">\n";
		$id++;
	}