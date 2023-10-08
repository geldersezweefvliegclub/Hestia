<?php

$helios_settings = array(
    'url' => 'http://helios_url',
    'username' => 'gebruiker',
    'password' => 'wachtwoors',
    'bypassToken' => '437dhsgnnm1@(dqappo):@'
);

$app_settings = array(
    'Debug' => true,				// Debug informatie naar logfile, uitzetten voor productie
    'DbLogging' => true,			// Log database queries naar logfile
    'DbError' => true,				// Log errors naar logfile
    'LogDir' => '/var/www/html/log/',	        // Locatie waar log bestanden geschreven worden
);

$db_info = array(
    'dbType' => 'mysql',
    'dbHost' => 'mariadb',
    'dbName' => 'hestia',
    'dbUser' => 'db gebruiker',
    'dbPassword' => 'db wachtwoord'
);
