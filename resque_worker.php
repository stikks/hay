<?php

$settings = require_once 'settings.php';
foreach ($settings['redis'] as $key => $value) {
    putenv(sprintf('%s=%s', $key, $value));
}
require_once 'Job.php';
require_once 'vendor/chrisboulton/php-resque/resque.php';
