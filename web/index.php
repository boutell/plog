<?php

session_start();

require dirname(__FILE__) . '/../src/site/startup.php';

$plog->connect();
$plog->go();

