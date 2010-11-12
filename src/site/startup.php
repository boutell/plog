<?php

require dirname(__FILE__) . '/../plog/Plog.php';

// Don't do this - set the database name directly in the settings. (I use this
// to test scenarios with lots of symlinks to the same folder to
// act as separate plogs)

if (isset($_SERVER['HTTP_HOST']))
{
  // Via web
  $dbname = $_SERVER['HTTP_HOST'];
  $name = $dbname;
  $style = $dbname;
  $password = $dbname;
}
else
{
  // Via command line
  $dbinfo = pathinfo(dirname(dirname($_SERVER['PWD'])));
  $dbname = $dbinfo['filename'];
  $name = $dbname;
  $style = $dbname;
  $password = $dbname;
}

$plog = new Plog(
  array(
    'name' => $name,
    'password' => $password,
    'style' => $style,
    'database' => array(
      'host' => 'localhost',
      'name' => $dbname,
      // Please tell me this is not your production mysql username and password
      'user' => 'root',
      'password' => 'root'
    ),
    'perPage' => 10,
    // Delivery attempts get less and less frequent until at this point we give up
    // on delivering that post to that peer
    'maxDeliveryTime' => '2 days',
    // Max time between a request for an API challenge code and the actual use of it. Should be
    // short but not so short that a biggish POST fails
    'apiChallengeAgeLimit' => '10 minutes',
    // Number of past posts to immediately send to a new friend to bring them up to speed.
    // Going berzerk here will just annoy them
    'postsToNewFriend' => 10,
    'deliveriesPerAttempt' => 5
  ));
