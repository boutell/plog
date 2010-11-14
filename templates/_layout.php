<html>
<head>
<title>my plog</title>
<script src="/jquery-1.4.2.min.js"></script>
<script src="/aControls.js"></script>
<link href="/reset.css" rel="stylesheet" type="text/css" /> 
<link href="/<?php echo $data['style'] ?>.css" rel="stylesheet" type="text/css" /> 
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="login">
        <?php if ($data['loggedIn']): ?>
          <a href="<?php echo $data['logoutUrl'] ?>">Log Out</a>
        <?php else: ?>
          <a href="<?php echo $data['loginUrl'] ?>">Log In</a>
        <?php endif ?>
      </div>
      <h1><a href="<?php echo $data['indexUrl'] ?>"><?php echo $data['name'] ?></a></h1>
      <?php if ($data['loggedIn']): ?>
        <ul class="counters">
          <li class="delivering">
            Delivering <span class="value"><?php echo $data['delivering'] ?></span>
          </li>
          <li class="unread">
            Unread <span class="value"><?php echo $data['unread'] ?></span>
          </li>
        </ul>
        <ul class="tabs">
          <?php echo $this->tab('index', 'indexUrl', 'Home') ?>
          <?php echo $this->tab('post', 'postUrl', 'Post') ?>
          <?php echo $this->tab('/friends/', 'friendsUrl', 'Friends') ?>
          <?php echo $this->tab('/^addFriend/', 'addFriendUrl', 'Add Friend') ?>
          <?php echo $this->tab('/^acceptFriendRequest/', 'acceptFriendRequestUrl', 'Accept Friend Request') ?>
        </ul>
        <script type="text/javascript" charset="utf-8">
          // Deliver new plog posts to appropriate friends
          $(function() {
            setInterval(function() {
              $.post(<?php echo $this->getJson('deliverUrl') ?>, {}, function(data) {
                $('.delivering .value').html(data['pending']);
              }, 'json');
            }, 5000);
            setInterval(function() {
              $.post(<?php echo $this->getJson('unreadUrl') ?>, {}, function(data) {
                $('.unread .value').html(data['unread']);
              }, 'json');
            }, 5000);
          });
        </script>
      <?php endif ?>
    </div>
    <div class="clearfix"></div>
    <?php // The template already escaped it, so don't escape it twice ?>
    <?php echo $this->getDirty('content') ?>
  </div>
</body>
</html>
