<?php

require dirname(__FILE__) . '/Concise.php';

class Plog extends Site
{
  protected $loggedIn = false;
  public function go()
  {
    if ($this->getSession('loggedIn'))
    {
      $this->loggedIn = true;
    }
    parent::go();
  }
  
  public function executeIndex()
  {
    $page = min($this->getParam('page', 1), 1);
    $perPage = $this->settings['perPage'];
    // Offset starts at 0 (why did I think it was 1?)
    $offset = ($page - 1) * $perPage;
    if ($this->loggedIn)
    {
      // Remember the id of the newest post we've considered (the newest we've received). This may not be the same thing
      // as the newest post we choose to show (because publication times don't march in lockstep with IDs)
      $newestPostId = $this->getNewestPostId();
      $_SESSION['newest_post_id'] = $newestPostId;
    }
    $q = 'select p.id, p.title, p.body, p.slug, p.published, a.first_name, a.last_name, a.nickname from post p inner join friend a on p.author_id = a.id ';
    if (!$this->loggedIn)
    {
      $q .= 'inner join post_fgroup pg on p.id = pg.post_id inner join fgroup g on pg.fgroup_id = g.id inner join friend_fgroup fg on g.id = fg.fgroup_id inner join friend f on fg.friend_id = f.id and f.nickname = "_public" ';
    }
    $posts = $this->db->query($q . 'order by published desc limit :perPage offset :offset', array('perPage' => $perPage, 'offset' => $offset));
    foreach ($posts as &$post)
    {
      $post['url'] = $this->urlToPost($post);
    }
    return $this->template('index', array('posts' => $posts, 'postUrl' => $this->urlTo('post')));
  }

  public function urlToPost($post)
  {
    list($date, $time) = preg_split('/ /', $post['published']);
    list($year, $month, $day) = preg_split('/-/', $date);
    return $this->urlTo('show', array('year' => $year, 'month' => $month, 'day' => $day, 'slug' => $post['slug']));
  }
  
  public function executeFriends()
  {
    $this->requireLogin();
    $friends = $this->db->query('SELECT * from friend WHERE friend.type = "plog" AND validated = 1 ORDER BY nickname ASC');
    $csrf = $this->getCsrf();
    foreach ($friends as &$friend)
    {
      $friend['deleteUrl'] = $this->urlTo('deleteFriend', array('id' => $friend['id'], 'csrf' => $csrf));
    }
    return $this->template('friends', array('friends' => $friends));
  }
  
  public function executeDeleteFriend()
  {
    $this->requireLogin();
    $this->checkCsrf();
    $id = $this->requireParam('id');
    if (!count($this->errors))
    {
      $this->db->delete('friend', $id);
    }
    return $this->redirectTo('friends');
  }
  
  public function executePostSubmit()
  {
    $this->requireLogin();
    
    if ($this->getServer('REQUEST_METHOD') === 'POST')
    {
      $csrfGood = $this->checkCsrf();
      $post['title'] = $this->requireParam('title');
      $post['body'] = $this->requireParam('body');
      $specific_fgroup_ids = $this->getParam('fgroups');
      if (!is_array($specific_fgroup_ids))
      {
        $specific_fgroup_ids = array();
      }
      $fgroups_add = $this->getParam('fgroups_add');
      // The add feature doesn't have re-presentation support which poses a problem
      // for validation. However we can just add them before validating everything else, 
      // dodging the problem. We do need to verify the CSRF check though
      if ($csrfGood)
      {
        if (is_array($fgroups_add) && count($fgroups_add))
        {
          foreach ($fgroups_add as $value)
          {
            $value = trim($value);
            if (!strlen($value))
            {
              continue;
            }
            if (substr($value, 0, 1) === '_')
            {
              $this->errors['fgroups_add'][] = 'Leading underscores not allowed';
              continue;
            }
            if ($this->db->queryOneScalar('SELECT type FROM fgroup WHERE name = :name', array('name' => $value)))
            {
              continue;
            }
            $specific_fgroup_ids[] = $this->db->insert('fgroup', array('name' => $value, 'type' => 'regular'));
          }
        }
      }
      // In case of another validation pass present all of these as
      // normal selections
      $this->setParam('fgroups', $specific_fgroup_ids);
      if (!count($this->errors))
      {
        $post['slug'] = $this->db->uniqueify('post', 'slug', $this->slugify($post['title']));
        $post['published'] = $this->db->now();
        $me_id = $this->db->queryOneScalar('SELECT id FROM friend WHERE nickname = "_me"');
        $post['author_id'] = $me_id;
        $post_id = $this->db->insert('post', $post);
        $fgroup_ids = array();
        $me_fgroup_id = $this->db->queryOneScalar('SELECT id FROM fgroup WHERE name = "_me"');
        $fgroup_ids[] = $me_fgroup_id;
        $privacy = $this->getParam('publicity');
        if ($privacy === 'public')
        {
          $friends_fgroup_id = $this->db->queryOneScalar('SELECT id FROM fgroup WHERE name = "_friends"');
          $fgroup_ids[] = $friends_fgroup_id;
          $public_fgroup_id = $this->db->queryOneScalar('SELECT id FROM fgroup WHERE name = "_public"');
          $fgroup_ids[] = $public_fgroup_id;
        }
        elseif ($privacy === 'friends')
        {
          $friends_fgroup_id = $this->db->queryOneScalar('SELECT id FROM fgroup WHERE name = "_friends"');
          $fgroup_ids[] = $friends_fgroup_id;
        }
        elseif ($privacy === 'some')
        {
          foreach ($specific_fgroup_ids as $fgroup_id)
          {
            $fgroup_ids[] = $fgroup_id;
          }
        }
        else
        {
          // Private - we already added the _me group - don't add any more
        }
        foreach ($fgroup_ids as $fgroup_id)
        {
          $this->db->insert('post_fgroup', array('post_id' => $post_id, 'fgroup_id' => $fgroup_id));
        }
        $friend_ids = $this->db->queryScalar('SELECT f.id FROM friend_fgroup fg INNER JOIN friend f ON f.id = fg.friend_id WHERE f.validated = 1 AND f.type = "plog" AND fg.fgroup_id IN :fgroup_ids', array('fgroup_ids' => $fgroup_ids));
        $this->addDeliveries(array($post_id), $friend_ids);
        return $this->redirectTo('index');
      }
    }
    return $this->executePost();
  }

  protected function addDeliveries($post_ids, $friend_ids)
  {
    $now = $this->db->now();
    foreach ($post_ids as $post_id)
    {
      foreach ($friend_ids as $friend_id)
      {
        $this->db->insert('post_friend_pending', array('post_id' => $post_id, 'friend_id' => $friend_id, 'next_attempt' => $now, 'created_at' => $now));
      }
    }
  }
  
  public function executePost()
  {
    $this->requireLogin();
    
    $csrf = $this->getCsrf();
    $title = $this->getParam('title', '');
    $body = $this->getParam('body', '');
    $fgroups = $this->getParam('fgroups', array());
    // TODO: think about a way to refresh the new friends fgroups (fgroups_add) if
    // validation fails. aMultipleSelect currently can't do that
    $fgroupInfos = $this->db->query('select id, name from fgroup where type = "regular"');
    $fgroupOptions = array();
    foreach ($fgroupInfos as $info)
    {
      $fgroupOptions[$info['id']] = $info['name'];
    }
    return $this->template('post', array('csrf' => $csrf, 'title' => $title, 'body' => $body, 'actionUrl' => $this->urlTo('postSubmit'), 'cancelUrl' => $this->urlTo('index'), 'publicity' => 'public', 'fgroups' => $fgroups, 'fgroupOptions' => $fgroupOptions));
  }

  public function executeAddFriend()
  {
    $this->requireLogin();
    
    $info['csrf'] = $this->getCsrf();
    $info['first_name'] = $this->getParam('first_name', '');
    $info['last_name'] = $this->getParam('last_name', '');
    $info['nickname'] = $this->getParam('nickname', '');
    $info['actionUrl'] = $this->urlTo('addFriendSubmit', array(), true);
    $info['cancelUrl'] = $this->urlTo('index');
    return $this->template('addFriend', $info);
  }
      
  public function executeAddFriendSubmit()
  {
    $this->requireLogin();
    
    if ($this->getServer('REQUEST_METHOD') === 'POST')
    {
      $this->checkCsrf();
      $first_name = $this->getParam('first_name', '');
      $last_name = $this->getParam('last_name', '');
      $nickname = $this->requireParam('nickname');
      // Nicknames have to be Unix names, and leading _ is reserved for special-purpose friends like _me and _public
      $nickname = preg_replace('/[^\w]+/', '_', $nickname);
      $nickname = preg_replace('/^_/', '', $nickname);
      if (!strlen($nickname))
      {
        $this->errors['nickname']['required'] = true;
      }
      // Check for a prior not-yet-accepted invitation, quietly allow reuse of it. Don't allow
      // reuse of a nickname for a validated friend of course.
      $existing = $this->db->queryOne('SELECT * FROM friend WHERE nickname = :nickname AND validated = 0 AND type = "plog"', array('nickname' => $this->getParam('nickname')));
      if (!$existing)
      {
        $this->uniqueParam('nickname', 'friend');
      }
      if (!count($this->errors))
      {
        if ($existing)
        {
          $info = $existing;
        }
        else
        {
          $info = array('first_name' => $first_name, 'last_name' => $last_name, 'nickname' => $nickname, 'validated' => 0, 'secret' => Guid::generate(), 'type' => 'plog', 'validate' => ':' . Guid::generate());
        }
        
        if ($existing)
        {
          $this->db->update('friend', $info['id'], $info);
        }
        else
        {
          $friend_id = $this->db->insert('friend', $info);
          // A new friend can immediately see messages intended for all friends
          $this->addFriendToFriends($friend_id);
        }

        // base64-encoded so people understand it's opaque, json_encoded so it's extensible. Of course base64 is not secure.
        // The user needs to get this information to the other person safely by the means of their choice.
        
        // The information the other site needs is our base URL, their one-time validation code (until we know *their* URL), 
        // and the shared secret. We also pass our first name, last name and nickname as defaults the other person can edit
        // if they wish to (or there is a duplicate nickname)
        
        $info['code'] = base64_encode(json_encode(array('first_name' => $this->settings['first_name'], 'last_name' => $this->settings['last_name'], 'nickname' => $this->settings['nickname'], 'url' => $this->absolute($this->getRoot()), 'validate' => $info['validate'], 'secret' => $info['secret'])));

        return $this->template('friendRequest', $info);
      }
    }
    return $this->executeAddFriend();
  }
  
  public function apiAcceptFriendRequest()
  {
    // We know the auth_id is good because we passed the challenge. It'll be a validation code since
    // this is the initial handshake before we know the peer's URL. Set the url column but don't mark
    // them validated yet, we wait for the newFriendReady request for that so that we don't start 
    // blasting them with messages before we know they are ready
    $this->db->query('UPDATE friend SET url = :url WHERE validate = :validate', array('url' => $this->getParam('url'), 'validate' => $this->getParam('auth_id')));
    // Note that the peer was authenticated by the api challenge/request mechanism, so we don't really have
    // anything further to do here. We don't start sending posts until we receive a newFriendReady call
    return array('status' => 'ok');
  }

  public function apiNewFriendReady()
  {
    // Note that auth_id was validated by the api challenge/request mechanism, so we can trust it now
    $this->db->query('UPDATE friend SET validated = 1, validate = null WHERE url = :url', array('url' => $this->getParam('auth_id')));
    
    $friend_id = $this->getRemoteFriendId();
    // Now both sides have marked the other as validated and we can start blasting posts over without
    // race conditions
    $this->sendPostsToNewFriend($friend_id);
    return array('status' => 'ok');
  }
  
  public function apiRemotePost()
  {
    // TODO: length limits, and aHtml::simplify for body (at a minimum we can't let phishing attacks through)
    $data['title'] = $this->requireParam('title');
    $data['body'] = $this->getParam('body');
    $data['published'] = $this->requireParam('published');
    $data['slug'] = $this->db->uniqueify('post', 'slug', $this->slugify($data['title']));
    if (count($this->errors))
    {
      return array('status' => 'error', 'errors' => $this->errors);
    }
    $friend_id = $this->getRemoteFriendId();
    $data['author_id'] = $friend_id;
    $this->db->insert('post', $data);
    return array('status' => 'ok');
  }

  protected function getRemoteFriendId()
  {
    // For use in api methods. Once validation has taken place we know the auth_id is the peer's URL. Use that
    // to get the friend id. TODO: should really cache this to limit queries but it's not used all that much
    return $this->db->queryOneScalar('SELECT id FROM friend WHERE url = :url', array('url' => $this->getParam('auth_id')));
  }
  
  protected function getNewestPostId()
  {
    // Find the newest post that is not our own
    return $this->db->queryOneScalar('select p.id FROM post p INNER JOIN friend f WHERE p.author_id = f.id AND f.nickname <> "_me" ORDER BY p.id DESC LIMIT 1');
  }

  protected function countUnread()
  {
    // Count posts not our own since the last time we peeked at the index page
    $latest = 0;
    if (isset($_SESSION['newest_post_id']))
    {
      $latest = $_SESSION['newest_post_id'];
    }
    return $this->db->queryOneScalar('select count(p.id) FROM post p INNER JOIN friend f WHERE p.author_id = f.id AND f.nickname <> "_me" AND p.id > :latest ORDER BY p.id DESC LIMIT 1', array('latest' => $latest));
  }
  
  protected function sendPostsToNewFriend($friend_id)
  {
    // Send them recent posts they are allowed to see
     $post_ids = $this->db->queryScalar('SELECT p.id FROM post p INNER JOIN friend author ON author.nickname = "_me" AND p.author_id = author.id INNER JOIN post_fgroup pg ON pg.post_id = p.id INNER JOIN friend_fgroup fg ON fg.fgroup_id = pg.fgroup_id AND fg.friend_id = :friend_id ORDER BY published desc LIMIT :limit', array('limit' => $this->settings['postsToNewFriend'], 'friend_id' => $friend_id));
    $this->addDeliveries($post_ids, array($friend_id));
    return array('status' => 'ok');
  }

  // Public RSS feed
  
  public function executeRssFeed()
  {    
    // 100 posts is pretty much standard for an RSS feed - more than that
    // and the major aggregators start rejecting your feed.
    $posts = $this->db->query('select * from post p ' . $this->getJoinForPublic() . 'order by published desc limit :rssFeedMax', array('rssFeedMax' => 100));
    foreach ($posts as $post)
    {
      $post['url'] = $this->urlToPost($post);
      // Didja know that PHP 5.1+ has a nice DATE_RSS constant?
      $post['published'] = date(DATE_RSS, strtotime($post['published']));
    }
    $this->hasLayout = false;
    $this->template(array('title' => $this->settings['title'], 'link' => $this->getRoot(), 'posts' => $posts));
  }
  
  // Attempts to deliver some outgoing plog posts to your peers. Invoked periodically on an AJAX basis.
  // It'll also be possible to do that from a cron job if you have a clue about such things
  
  public function executeDeliver()
  {
    $this->requireLogin();
    
    $this->hasLayout = false;
    $delivered = 0;
    $total = $this->countPendingDeliveries();
    $deliveries = $this->db->query('select * FROM post_friend_pending pfp INNER JOIN post p ON p.id = pfp.post_id INNER JOIN friend f on f.id = pfp.friend_id WHERE next_attempt <= NOW() ORDER BY next_attempt ASC LIMIT :limit', array('limit' => $this->settings['deliveriesPerAttempt']));
    foreach ($deliveries as $delivery)
    {
      $delete = false;
      $data = array('title' => $delivery['title'], 'body' => $delivery['body'], 'published' => $delivery['published']);
      $response = $this->call($delivery['url'], $delivery['secret'], 'remotePost', $data);
      if (is_array($response) && $response['status'] === 'ok')
      {
        $delete = true;
        $delivered++;
      }
      if (!$delete)
      {
        // 2^0 = 1, so 5 minutes until the second try, 10 minutes until the third, 20 minutes until the fourth etc.
        // That's in addition to any discrepancy between the current promised attempt time and when we really did it
        // (that is, we always err on the side of giving the server a break). When we reach maxDeliveryTime we
        // give up (TODO: flag a problem with this peer)
        $attempts = $delivery['attempts'];
        $interval = '+' . (5 * pow(2, $attempts)) . ' minutes';
        $next_attempt = $this->db->now($interval);
        if ($next_attempt <= $this->db->now('-' . $this->settings['maxDeliveryTime']))
        {
          $delete = true;
          $delivered++;
        }
        $attempts++;
      }
      if ($delete)
      {
        $this->db->query('DELETE FROM post_friend_pending WHERE post_id = :post_id AND friend_id = :friend_id', $delivery);
      }
      else
      {
        $this->db->query('UPDATE post_friend_pending SET next_attempt = :next_attempt, attempts = :attempts WHERE post_id = :post_id AND friend_id = :friend_id', array('attempts' => $attempts, 'next_attempt' => $next_attempt, 'post_id' => $delivery['post_id'], 'friend_id' => $delivery['friend_id']));
      }
    }
    // JSON response for jQuery.post to look at
    echo(json_encode(array('pending' => ($total - $delivered))));
  }
  
  public function executeUnread()
  {
    $this->requireLogin();
    
    $this->hasLayout = false;
    echo(json_encode(array('unread' => $this->countUnread())));
  }
    
  public function executeAcceptFriendRequest()
  {
    $this->requireLogin();

    $data['code'] = $this->getParam('code');
    $data['csrf'] = $this->getCsrf();
    $data['actionUrl'] = $this->urlTo('acceptFriendRequestSubmit');
    $this->template('acceptFriendRequest', $data);
  }

  public function executeAcceptFriendRequestSubmit()
  {
    $this->requireLogin();
    $info = $this->unpackFriendRequestCode($this->getParam('code'));
    $this->checkCsrf();
    if (is_null($info))
    {
      $this->errors['code']['required'] = true;
    }
    if (count($this->errors))
    {
      return $this->executeAcceptFriendRequest();
    }
    $this->setParam('first_name', $info['first_name']);
    $this->setParam('last_name', $info['last_name']);
    $this->setParam('nickname', $info['nickname']);
    return $this->executeAcceptFriendRequest2();
  }

  public function executeAcceptFriendRequest2()
  {
    $this->requireLogin();
    $this->checkCsrf();
    $data['csrf'] = $this->getCsrf();
    $data['first_name'] = $this->getParam('first_name');
    $data['last_name'] = $this->getParam('last_name');
    $data['nickname'] = $this->getParam('nickname');
    $data['code'] = $this->getParam('code');
    $data['actionUrl'] = $this->urlTo('acceptFriendRequest2Submit');
    $this->template('acceptFriendRequest2', $data);
  }

  protected function unpackFriendRequestCode($code)
  {
    // Ignores non-base64 characters. Some versions of PHP
    // don't manage this on their own
    $code = preg_replace('|[^A-Za-z0-9\+/]|', '', $code);
    $code = base64_decode($code);
    $info = json_decode($code, true);
    return $info;
  }
  
  public function executeAcceptFriendRequest2Submit()
  {
    $this->requireLogin();
    $this->checkCsrf();
    $info = $this->unpackFriendRequestCode($this->requireParam('code'));
    $first_name = $this->requireParam('first_name');
    $last_name = $this->requireParam('last_name');
    $nickname = $this->uniqueParam('nickname', 'friend');
    if (is_null($info))
    {
      $this->errors['code']['invalid'] = true;
    }
    if (count($this->errors))
    {
      return $this->executeAcceptFriendRequest2();
    }
    
    $url = $info['url'];
    $validate = $info['validate'];
    $secret = $info['secret'];

    $response = $this->call($url, $secret, 'acceptFriendRequest', array('url' => $this->absolute($this->getRoot())), $validate);
    if (is_null($response))
    {
      $this->errors['code']['invalidOrOffline'] = true;
      return $this->executeAcceptFriendRequest();
    }
    
    if ($response['status'] !== 'ok')
    {
      $this->errors['code']['rejected'] = true;
      return $this->executeAcceptFriendRequest();
    }    

    // It's OK to reestablish friendship with someone the system thinks is already your friend -
    // maybe you know the secret has gone stale. TODO: a confirmation prompt would be good though
    $existing = true;
    $friend = $this->db->queryOne('select * from friend where url = :url', array('url' => $url));
    if (!$friend)
    {
      $friend = array();
      $existing = false;
    }
    $friend['nickname'] = $nickname;
    $friend['first_name'] = $first_name;
    $friend['last_name'] = $last_name;
    $friend['url'] = $url;
    $friend['secret'] = $secret;
    $friend['type'] = 'plog';
    $friend['validated'] = 1;
    
    if ($existing)
    {
      $this->db->update('friend', $friend['id'], $friend);
      $friend_id = $friend['id'];
    }
    else
    {
      $friend_id = $this->db->insert('friend', $friend);
      $this->addFriendToFriends($friend_id);
    }

    // TODO probably need some retries on this, error handling
    $this->call($url, $secret, 'newFriendReady');
    
    $this->sendPostsToNewFriend($friend_id);
    
    // Then redirect to... the new guy's profile? The index? 
    // Index for now
    return $this->redirectTo('index');
  }
  
  public function routeShow(&$path, &$params)
  {
    $path .= '/' . $this->consume($params, 'year') . '/' . $this->consume($params, 'month') . '/' . $this->consume($params, 'day') . '/' . $this->consume($params, 'slug');
  }
  
  public function parseShow($path)
  {
    if (preg_match('|^/(\d+)/(\d+)/(\d+)/([\w\-]+)$|', $path, $matches))
    {
      return array('year' => $matches[1], 'month' => $matches[2], 'day' => $matches[3], 'slug' => $matches[4]);
    }
    return $this->notFound();
  }
  
  public function executeShow()
  {
    $q = 'SELECT * FROM post p ';
    if (!$this->loggedIn)
    {
      $q .= $this->getJoinForPublic();
    }
    $q .= 'WHERE p.slug = :slug ';
    $post = $this->db->queryOne($q, array('slug' => $this->getParam('slug')));
    if (!$post)
    {
      return $this->redirectTo('index');
    }
    $this->template('show', array('post' => $post, 'homeUrl' => $this->urlTo('index')));
  }

  public function executeLogin()
  {
    $csrf = $this->getCsrf();
    $this->template('login', array('actionUrl' => $this->urlTo('loginSubmit'), 'cancelUrl' => $this->urlTo('index'), 'csrf' => $csrf));
  }
  
  public function executeLoginSubmit()
  {
    $this->checkCsrf();
    $password = trim($this->requireParam('password'));
    if ($password !== $this->settings['password'])
    {
      $this->errors['password']['incorrect'] = true;
    }
    if (count($this->errors))
    {
      return $this->executeLogin();
    }
    $this->setSession('loggedIn', true);
    $this->redirectTo('index');
  }
  
  public function executeLogout()
  {
    $this->requireLogin();
    $this->clearSession();
    return $this->redirectTo('index');
  }
  
  public function recreateDatabase()
  {
    $settings = $this->settings['database'];
    unset($settings['name']);
    $this->db = new Mysql($settings);
    try
    {
      $this->db->query('drop database ' . $this->settings['database']['name']);
    } catch (Exception $e)
    {
      // It's OK if the db doesn't yet exist
    }
    $this->db->query('create database ' . $this->settings['database']['name']);
    
    // Reconnect with a database name
    $this->db = new Mysql($this->settings['database']);
    $this->createTables();
  }
  
  public function createTables()
  {
    $sql = array(
      'create table friend (id BIGINT AUTO_INCREMENT, first_name varchar(100), last_name varchar(100), nickname varchar(100), url varchar(255), secret varchar(100), validate varchar(100), type varchar(20), validated tinyint default 0, UNIQUE INDEX url_idx(url), UNIQUE INDEX nickname_idx(nickname), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table post (id BIGINT AUTO_INCREMENT, author_id BIGINT NOT NULL, url varchar(1000), title varchar(140), slug varchar(140), body text, published datetime, INDEX published_idx (published), UNIQUE INDEX slug_idx(slug), PRIMARY KEY(id), INDEX author_id_idx(author_id), CONSTRAINT post_friend_author_id FOREIGN KEY (author_id) REFERENCES friend (id) ON DELETE CASCADE) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table post_friend_pending (post_id BIGINT NOT NULL, friend_id BIGINT NOT NULL, created_at DATETIME, next_attempt DATETIME, attempts INT default 0, PRIMARY KEY (post_id, friend_id), CONSTRAINT post_friend_delivered_fgroup_post_id FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE, CONSTRAINT post_friend_delivered_friend_id FOREIGN KEY (friend_id) REFERENCES friend (id) ON DELETE CASCADE) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table fgroup (id BIGINT AUTO_INCREMENT, name varchar(100), type varchar(20), UNIQUE INDEX name_idx(name), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table post_fgroup (post_id BIGINT NOT NULL, fgroup_id BIGINT NOT NULL, PRIMARY KEY(post_id, fgroup_id), CONSTRAINT post_fgroup_post_id FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE, CONSTRAINT post_fgroup_fgroup_id FOREIGN KEY (fgroup_id) REFERENCES fgroup (id) ON DELETE CASCADE) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table friend_fgroup (friend_id BIGINT NOT NULL, fgroup_id BIGINT NOT NULL, PRIMARY KEY(friend_id, fgroup_id), CONSTRAINT friend_fgroup_friend_id FOREIGN KEY (friend_id) REFERENCES friend (id) ON DELETE CASCADE, CONSTRAINT friend_fgroup_fgroup_id FOREIGN KEY (fgroup_id) REFERENCES fgroup (id) ON DELETE CASCADE) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB',
      'create table challenge (challenge_id varchar(100), challenge varchar(100), created_at datetime NOT NULL, PRIMARY KEY challenge_id_idx(challenge_id)) DEFAULT CHARACTER SET UTF8 ENGINE = INNODB'
    );
    $this->db->sql($sql);
    // Nulls make queries inelegant and hard to understand. The clean way to determine which posts are private
    // is to link *all* posts to the '_me' fgroup, and include only the '_me' friend in the '_me' fgroup. Some posts
    // get other fgroups, others do not. Public posts get the '_public' fgroup, which every friend and the public
    // RSS/web feed gets. Friends-only posts get the '_friends' group, which every friend gets
    $meId = $this->db->insert('friend', array('nickname' => '_me', 'type' => 'internal'));
    $info['friend_id'] = $meId;
    $info['fgroup_id'] = $this->db->insert('fgroup', array('name' => '_me', 'type' => 'internal'));
    $this->db->insert('friend_fgroup', $info);

    // Similarly we have a '_public' friend who is a member of the '_public' fgroup.
    // '_me' is also a member and all regular friends will be too
    $info['friend_id'] = $this->db->insert('friend', array('nickname' => '_public', 'type' => 'internal'));
    $info['fgroup_id'] = $this->db->insert('fgroup', array('name' => '_public', 'type' => 'internal'));
    $this->db->insert('friend_fgroup', $info);
    $info['friend_id'] = $meId;
    $this->db->insert('friend_fgroup', $info);
    
    // Add '_me' to a '_friends' group, which will contain all friends
    $info['fgroup_id'] = $this->db->insert('fgroup', array('name' => '_friends', 'type' => 'internal'));
    $this->db->insert('friend_fgroup', $info);
  }

  protected function layout($output)
  {    
    $t = new Template('layout', array('content' => $output, 'postUrl' => $this->urlTo('post'), 'addFriendUrl' => $this->urlTo('addFriend'), 'indexUrl' => $this->urlTo('index'), 'acceptFriendRequestUrl' => $this->urlTo('acceptFriendRequest'), 'friendsUrl' => $this->urlTo('friends'), 'action' => $this->action, 'delivering' => $this->countPendingDeliveries(), 'deliverUrl' => $this->urlTo('deliver'), 'unread' => $this->countUnread(), 'unreadUrl' => $this->urlTo('unread'), 'name' => $this->settings['name'], 'style' => $this->settings['style'], 'loggedIn' => $this->loggedIn, 'loginUrl' => $this->urlTo('login'), 'logoutUrl' => $this->urlTo('logout')));
    $t->go();
  }
  
  protected function getApiSecret($id)
  {
    // Watch out for friends with null secrets (like _me and _public) which are not for use via the API
    
    if (substr($id, 0, 1) === ':')
    {
      // First time validation code
      $field = 'validate';
    }
    else
    {
      $field = 'url';
    }
    return $this->db->queryOneScalar("SELECT secret FROM friend WHERE ($field = :id) AND secret IS NOT NULL", array('id' => $id));
  }
  
  protected function addFriendToFriends($friend_id)
  {
    $friends_id = $this->db->queryOneScalar('select id from fgroup where name = "_friends"');
    $this->db->insert('friend_fgroup', array('friend_id' => $friend_id, 'fgroup_id' => $friends_id));
  }
  
  protected function countPendingDeliveries()
  {
    return $this->db->queryOneScalar('select count(*) FROM post_friend_pending pfp INNER JOIN post p ON p.id = pfp.post_id INNER JOIN friend f on f.id = pfp.friend_id');
  }
  
  protected function requireLogin()
  {
    if (!$this->loggedIn)
    {
      return $this->redirectTo('index');
    }
  }
  
  protected function getJoinForPublic()
  {
    return 'inner join post_fgroup pg on p.id = pg.post_id inner join fgroup g on pg.fgroup_id = g.id inner join friend_fgroup fg on g.id = fg.fgroup_id inner join friend f on fg.friend_id = f.id and f.nickname = "_public" ';
  }
}

