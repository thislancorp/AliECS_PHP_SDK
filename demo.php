<?php
require_once "ecs.sdk.class.php";

$accessKeyId = '';
$accessKeySecret = '';
$ecs=new ECS($accessKeyId,$accessKeySecret);
print_r($ecs->describeInstanceAttribute('AY120723082752d383204'));
print_r($ecs->describeInstanceTypes());
?>