<?php

require 'Predis/Autoloader.php';

Predis\Autoloader::register();

require_once 'RedisSession.php';

$redisHandle    =    new Predis\Client();
assert($redisHandle);

RedisSession::init ($redisHandle);

$_SESSION['id'] = 1234;

print_r($_SESSION);

