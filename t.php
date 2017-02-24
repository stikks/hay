<?php
require __DIR__ . '/vendor/autoload.php';
require 'php-resque-scheduler/resque-scheduler.php';

$time = 1332067214;
ResqueScheduler::enqueueAt($time, 'email', 'SendFollowUpEmail', $args);

$datetime = new DateTime('2012-03-18 13:21:49');
ResqueScheduler::enqueueAt($datetime, 'email', 'SendFollowUpEmail', $args);
