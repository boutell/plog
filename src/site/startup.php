<?php

require dirname(__FILE__) . '/../plog/Plog.php';

// This silly hack is how I make demo plogs. Don't do this - set the db credentials,
// first name, last name, nickname etc. in the settings array when creating the plog
// object (see below).

// BEGIN SILLY DEMO HACK

if (isset($_SERVER['HTTP_HOST']))
{
  // Via web
  $dbname = $_SERVER['HTTP_HOST'];
}
else
{
  // Via command line
  $dbinfo = pathinfo(dirname(dirname($_SERVER['PWD'])));
  $dbname = $dbinfo['filename'];
}

$name = $dbname;
$nickname = $dbname;
$style = $dbname;
$password = $dbname;

if (preg_match('/^(\w+)plog$/', $dbname, $matches))
{
  $first_name = $matches[1];
  $last_name = 'plog';
}

// END SILLY DEMO HACK

// Make your plog, and pass it your settings. You can edit the settings without
// deeply understanding PHP, don't worry. Just don't use ' in your name etc. without 
// escaping it with a \ in front

$plog = new Plog(
  array(
    // Title of your plog
    'name' => $name,
    // Your preferred nickname on other plogs (friends may change it to avoid conflicts).
    // Should consist of letters, digits and underscores and be shortish
    'nickname' => $nickname,
    // Your first name
    'first_name' => $first_name,
    // Your last name
    'last_name' => $last_name,
    // Your password for logging into your own plog
    'password' => $password,
    // Determines which stylesheet is loaded. Go with just 'main' for the default styles
    'style' => 'main',
    // Database settings
    'database' => array(
      'host' => 'localhost',
      'name' => $dbname,
      // Please tell me this is not your production mysql username and password
      'user' => 'root',
      'password' => 'root'
    ),
    'perPage' => 10,
    // Delivery attempts get less and less frequent until at this point we give up
    // on delivering that post to that friend
    'maxDeliveryTime' => '2 days',
    // Max time between a request for an API challenge code and the actual use of it. Should be
    // short but not so short that a biggish POST fails
    'apiChallengeAgeLimit' => '10 minutes',
    // Number of past posts to immediately send to a new friend to bring them up to speed.
    // Going berzerk here will just annoy them
    'postsToNewFriend' => 10,
    // How many posts we try to deliver on each update of the "Delivering:" box
    // (delivery attempts are made every 5 seconds)
    'deliveriesPerAttempt' => 5
  ));
