<?php

// Concise is a very small PHP application framework which follows
// the MVC principle - but not to the edge of a cliff. 

// Copyright 2010 Thomas Boutell. Released under the MIT license.
// Owes a debt of inspiration to Symfony and Apostrophe.

class Site
{
  protected $errors = array();
  protected $db;
  protected $hasLayout = true;
  protected $action = null;
  protected $settings;

  public function __construct($settings)
  {
    $this->settings = $settings;
    if (!isset($this->settings['baseUrl']))
    {
      $host = $this->getServer('HTTP_HOST');
      $port = $this->getServer('SERVER_PORT');
      if ($this->getServer('HTTPS'))
      {
        $site = 'https://' . $host;
        if ($port != 443)
        {
          $site .= ':' . $port;
        }
      }
      else
      {
        $site = 'http://' . $host;
        if ($port != 80)
        {
          $site .= ':' . $port;
        }
      }
      $this->settings['baseUrl'] = $site;
    }
  }

  public function connect()
  {
    $this->db = new Mysql($this->settings['database']);
  }
  
  public function go()
  {
    $this->params = $_REQUEST;
    ob_start();    
    try
    {
      $method = null;
      $pathInfo = $this->getServer('PATH_INFO');
      if (preg_match('/\/(\w+)(\/.*$)?/', $pathInfo, $match))
      {
        $action = $match[1];
        if (count($match) > 2)
        {
          $path = $match[2];
          $cmethod = 'parse' . ucfirst($action);
          if (method_exists($this, $cmethod))
          {
            $this->action = $action;
            $this->params = array_merge($this->$cmethod($path), $this->params);
          }
          else
          {
            $this->notFound();
            throw new ParseException();
          }
        }
        $cmethod = 'execute' . ucfirst($action);
        if (method_exists($this, $cmethod))
        {
          $this->action = $action;
          $method = $cmethod;
        }
        else
        {
          $this->notFound();
          throw new ParseException();
        }
      }
      if (is_null($method))
      {
        $this->action = 'index';
        $method = 'executeIndex';
      }
      $this->$method();
    } catch (ParseException $pe)
    {
    }
    $output = ob_get_clean();
    if ($this->hasLayout)
    {
      $this->layout($output);
    }
    else
    {
      echo($output);
    }
  }
  
  public function executeApi()
  {
    $this->hasLayout = false;
    $method = $this->getParam('method');
    if (is_null($method))
    {
      return $this->notFound();
    }
    if ($method !== 'getChallenge')
    {
      $challenge_id = $this->getParam('auth_challenge_id');
      $id = $this->getParam('auth_id');
      $response = $this->getParam('auth_response');
      $challenge = $this->db->queryOneScalar('SELECT challenge FROM challenge WHERE challenge_id = :challenge_id AND created_at >= :age_limit', array('challenge_id' => $challenge_id, 'age_limit' => $this->db->now('-' . $this->settings['apiChallengeAgeLimit'])));
      if (is_null($challenge))
      {
        // Phbbbt. Challenge bogus or too old
        error_log("Challenge not found for $challenge_id");
        return $this->notFound();
      }
      error_log("Looking up secret for $id");
      $secret = $this->getApiSecret($id);
      // No API in this app or the id is bogus. It's really bad to guess wrong here,
      // so tolerate null, false or the empty string as valid ways of saying "nope,
      // no such id" 
      if (!strlen($secret))
      {
        error_log("Secret not found for $id");
        return $this->notFound();
      }
      error_log("Response was: $response md5 is: " . md5($challenge . $secret) . " challenge is: " . $challenge . " secret is: " . $secret);
      if ($response !== md5($challenge . $secret))
      {
        // Phbbbt. Response is no good
        error_log("md5 of response is wrong");
        return $this->notFound();
      }
    }
    $method = 'api' . ucfirst($method);
    if (method_exists($this, $method))
    {
      echo json_encode($this->$method());
    }
    else
    {
      return $this->notFound();
    }
  }
  
  public function parseApi($path)
  {
    error_log("parseApi $path");
    if (!preg_match('|^/(\w+)$|', $path, $matches))
    {
      return $this->notFound();
    }
    return array('method' => $matches[1]);
  }
  
  public function apiGetChallenge()
  {
    $challenge = Guid::generate();
    $challenge_id = Guid::generate();
    $this->db->query('DELETE from challenge WHERE created_at < :age_limit', array('age_limit' => $this->db->now($this->settings['apiChallengeAgeLimit'])));
    error_log("NOW is " . $this->db->now());
    $this->db->insert('challenge', array('created_at' => $this->db->now(), 'challenge_id' => $challenge_id, 'challenge' => $challenge));
    return array('challenge' => $challenge, 'challenge_id' => $challenge_id);
  }
  
  // If you want an API in your app you must provide a secret (a shared password or apikey) for
  // any given valid id, or return null if the id is bogus
  protected function getApiSecret($id)
  {
    return null;
  }
  
  public function executeIndex()
  {
    return $this->template('index');
  }
  
  public function urlTo($action, $params = array(), $absolute = false)
  {
    $root = $this->getRoot();
    if ($action === 'index')
    {
      $path = $root;
    }
    else
    {
      $path = $root . '/' . $action;
    }
    $method = 'route' . ucfirst($action);
    if (method_exists($this, $method))
    {
      $this->$method($path, $params);
    }
    if (count($params))
    {
      $path .= '?' . http_build_query($params);
    }
    if ($absolute)
    {
      $path = $this->absolute($path);
    }
    return $path;
  }
  
  // $path should already be locally absolute (start with /)
  public function absolute($path)
  {
    return $this->settings['baseUrl'] . $path;
  }
  
  public function notFound()
  {
    $this->status(404, 'Not Found');
    $this->template('notFound');
  }

  public function status($status, $message)
  {
    header($_SERVER["SERVER_PROTOCOL"] . " " . $status . " " . $message);
  }
  
  public function redirectTo($action)
  {
    $this->hasLayout = false;
    $path = $this->absolute($this->urlTo($action));
    header("Location: " . $path . "\r\n\r\n");
  }

  protected function consume(&$array, $key)
  {
    if (!isset($array[$key]))
    {
      throw new Exception("$key missing from array in consume call");
    }
    $v = $array[$key];
    unset($array[$key]);
    return $v;
  }
  
  protected function template($name, $data = array())
  {
    $data['action'] = $this->action;
    $template = new Template($name, $data, $this->errors);
    $template->go();
  }
  
  protected function getRoot()
  {
    return $_SERVER['SCRIPT_NAME'];
  }
  
  protected function getSession($p, $d = null)
  {
    if (isset($_SESSION[$p]))
    {
      return $_SESSION[$p];
    }
    else
    {
      return $d;
    }
  }
  
  protected function setSession($p, $v)
  {
    $_SESSION[$p] = $v;
  }
  
  protected function requireParam($p)
  {
    if ((!isset($_REQUEST[$p])) || (!strlen(trim($_REQUEST[$p]))))
    {
      $this->errors[$p]['required'] = true;
      return null;
    }
    return $_REQUEST[$p];
  }

  protected function uniqueParam($p, $table, $column = null)
  {
    $v = $this->requireParam($p);
    if ($v === null)
    {
      return null;
    }
    if ($column === null)
    {
      $column = $p;
    }
    if (!$this->db->unique($table, $column, $v))
    {
      $this->errors[$p]['unique'] = true;
      return null;
    }
    return $v;
  }
  
  public function createDatabase()
  {
    throw new Exception('createDatabase not implemented');
  }
  
  protected function getParam($p, $d = null)
  {
    return isset($this->params[$p]) ? $this->params[$p] : $d;
  }

  protected function setParam($p, $v)
  {
    $this->params[$p] = $v;
  }
  
  protected function getServer($p, $d = null)
  {
    return isset($_SERVER[$p]) ? $_SERVER[$p] : $d;
  }
  
  protected function slugify($s)
  {
    return preg_replace(array('/[^\w]+/', '/^-/', '/-$/'), array('-', '', ''), $s);
  }
  
  protected function layout($output)
  {
    $t = new Template('layout', array('content' => $output, 'action' => $this->action));
    $t->go();
  }

  // Call a validated API in another site. In Plog an $auth_id parameter is only needed on the first
  // call (ever), since after that the other side knows our root URL (or other agreed identifier)
  // and will accept that as the auth_id
  public function call($url, $secret, $method, $data = array(), $auth_id = null)
  {
    if (is_null($auth_id))
    {
      $auth_id = $this->absolute($this->getRoot());
    }
    $response = file_get_contents($url . '/api/getChallenge?' . http_build_query(array('auth_id' => $auth_id)));
    $challenge = $this->unpackJSON($response);
    if (is_null($challenge))
    {
      return null;
    }
    $challenge_id = $challenge['challenge_id'];
    $challenge = $challenge['challenge'];
    if (!strlen($challenge))
    {
      return null;
    }
    $data['auth_challenge_id'] = $challenge_id;
    $data['auth_id'] = $auth_id;
    $data['auth_response'] = md5($challenge . $secret);
    $ctx = stream_context_create(array(
      'http' => array( 
        'method'  => 'POST', 
        'header'  => 'Content-type: application/x-www-form-urlencoded', 
        'content' => http_build_query($data), 
        'timeout' => 10 
      )
    ));
    $u = $url . '/api/' . $method;
    $response = @file_get_contents($u, false, $ctx);
    return $this->unpackJSON($response);
  }

  protected function unpackJSON($response)
  {
    if (!strlen($response))
    {
      return null;
    }
    $response = @json_decode($response, true);
    if (!is_array($response))
    {
      return null;
    }
    return $response;
  }
  
  public function csrfNext()
  {
    $csrf = Guid::generate();
    $_SESSION['csrf'][$csrf] = true;
    return $csrf;
  }

  public function csrfCheck($value = null)
  {
    if ($value === null)
    {
      $value = $this->getParam('csrf');
    }
    $result = isset($_SESSION['csrf'][$value]);
    if (!$result)
    {
      $this->errors['csrf']['required'] = true;
      return false;
    }
    return true;
  }
}

// Strict templating. If you need URLs built, they can be passed in
// as data; that's the controller's job

class Template
{
  protected $data;
  protected $name;
  protected $errors;
  public function __construct($name, $data = array(), $errors = array())
  {
    $this->data = $data;
    $this->name = $name;
    $this->errors = $errors;
  }
  public function getDirty($v, $d = null)
  {
    if (isset($this->data[$v]))
    {
      return $this->data[$v];
    }
    else
    {
      return $d;
    }
  }
  
  public function get($v, $d = null)
  {
    $v = $this->getDirty($v, $d);
    return $this->clean($v);
  }
  
  public function getJson($v, $d = null)
  {
    return json_encode($this->getDirty($v, $d));
  }
  public function clean($v)
  {
    if (is_array($v))
    {
      $nv = array();
      foreach ($v as $key => $val)
      {
        $nv[$key] = $this->clean($val);
      }
      return $nv;
    }
    else
    {
      return htmlentities($v, ENT_COMPAT, 'UTF-8');
    }
  }
  
  public function errors($item)
  {
    if (isset($this->errors[$item]))
    {
      return $this->errors[$item];
    }
    else
    {
      return array();
    }
  }

  public function error($item, $error)
  {
    if (isset($this->errors[$item][$error]))
    {
      return $this->errors[$item][$error];
    }
    else
    {
      return null;
    }
  }
  
  public function go()
  {
    $data = $this->clean($this->data);
    require dirname(__FILE__) . '/../../templates/_' . $this->name . '.php';
  }
  
  // Tag helpers. Note that labels are NOT escaped unless otherwise noted. Markup is allowed in label tags and that is handy
  
  // One radio button in a set
  public function radio($label, $name, $value)
  {
    $id = Id::next();
    return $this->tag('input', array('id' => $id, 'type' => 'radio', 'name' => $name, 'value' => $value, 'checked' => ($this->data['publicity'] === $value) ? 'checked' : null)) . $this->tag('label', array('for' => $id), $label);
  }
  
  // This takes advantage of a nice progressively enhanced multiple select borrowed from Apostrophe
  public function multipleSelect($label, $name, $options)
  {
    $id = Id::next();
    $o = '';
    foreach ($options as $value => $value_label)
    {
      // "Why not clean the label?" Because we generally call this from a template, where it is already escaped.
      $o .= $this->tag('option', array('value' => $value, 'selected' => ($this->data[$name] === $value) ? 1 : null), $value_label);
    }
    return $this->tag('select', array('id' => $id, 'multiple' => 1, 'size' => 1, 'name' => $name), $o) . $this->tag('label', array('for' => $id), $label);
  }
  
  public function tag($name, $attributes, $content = null)
  {
    $t = '<' . $name;
    foreach ($attributes as $k => $v)
    {
      // Allows easy skipping
      if (is_null($v))
      {
        continue;
      }
      $t .= ' ' . $this->clean($k) . '="' . $this->clean($v) . '"';
    }
    if (is_null($content))
    {
      $t .= ' />';
      return $t;
    }
    $t .= '>' . $content . '</' . $name . '>';
    return $t;
  }
}

class Id
{
  private static $n = 1;
  static public function next()
  {
    return 'p_auto_id' . self::$n++;
  }
}

// A simple, safe, awesome wrapper for MySQL. Offers useful tools
// to check for existing columns and tables as well as a simple and
// clean way to make queries fairly painlessly

class Mysql
{
  protected $conn;
  protected $commandsRun;
  
  public function __construct($settings)
  {
    if (!isset($settings['name']))
    {
      $dsn = 'mysql:host=' . $settings['host'];
    }
    else
    {
      $dsn = 'mysql:dbname=' . $settings['name'] . ';host=' . $settings['host'];
    }
    $this->conn = new PDO($dsn, $settings['user'], $settings['password']);
    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  // Used to run a series of queries where you don't need parameters or results
  public function sql($commands)
  {
    foreach ($commands as $command)
    {
      $this->conn->query($command);
      $this->commandsRun++;
    }
  }
  
  // Runs a single query, with parameters. If :foo appears in the query it gets
  // substituted correctly (via PDO) with $params['foo']. Extra stuff in
  // $params is allowed, which is very helpful with toArray(). The return value,
  // as is standard with PDO, is an associative array by column name as well as being a 
  // numerically indexed array in column order.
  
  // If $params['foo'] is an array, then :foo is replaced by a correctly parenthesized and quoted
  // array for use in a WHERE foo IN (a, b, c) clause. 
  
  public function query($s, $params = array())
  {
    $pdo = $this->conn;
    $nparams = array();
    // I like to use this with toArray() while not always setting everything,
    // so I tolerate extra stuff. Also I don't like having to put a : in front 
    // of everything
    foreach ($params as $key => $value)
    {
      // Tolerate numeric keys, which allows us to use the results of a 
      // previous PDO query
      if (is_numeric($key))
      {
        continue;
      }
      $regexp = '/:' . preg_quote($key, '/') . '\b/';
      if (preg_match($regexp, $s) > 0)
      {
        // Arrays are turned into IN clauses (comma separated lists enclosed in parens)
        if (is_array($value))
        {
          $s = preg_replace($regexp, '(' . implode(',', array_map(array($this, 'quote'), $value)) . ')', $s); 
        }
        else
        {
          $nparams[":$key"] = $value;
        }
      }
    }
    
    $statement = $pdo->prepare($s);

    // PDO has brain damage and can't figure out when to bind things as literals with
    // PDO::PARAM_INT. This breaks offset and limit queries if you just bind naively with
    // an array argument to execute()

    foreach ($nparams as $key => $value)
    {
      if (is_int($value) || preg_match('/^-?\d+$/', $value))
      {
        $statement->bindValue($key, $value, PDO::PARAM_INT);
      }
      else
      {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
      }
    }
    
    try
    {
      $statement->execute();
    }
    catch (Exception $e)
    {
      throw new Exception("PDO exception on query: " . $s . " arguments: " . json_encode($params) . " bound arguments: " . json_encode($nparams) . "\n\n" . $e);
    }
    $result = true;
    try
    {
      $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e)
    {
      // Oh no, we tried to fetchAll on a DELETE statement, everybody panic!
      // Seriously PDO, you need to relax
    }
    $this->commandsRun++;
    return $result;
  }

  public function quote($item)
  {
    return $this->conn->quote($item);
  }
  
  public function queryOne($query, $params = array())
  {
    $results = $this->query($query, $params);
    if (count($results))
    {
      return $results[0];
    }
    return null;
  }

  // Handy for getting just the ids, just the names, etc.
  public function queryScalar($query, $params = array())
  {
    $results = $this->query($query, $params);
    $nresults = array();
    foreach ($results as $result)
    {
      $nresults[] = reset($result);
    }
    return $nresults;
  }

  // Note: returns null if there are no results, or no columns in the results (is that possible?)
  public function queryOneScalar($query, $params = array())
  {
    $results = $this->query($query, $params);
    if (!count($results))
    {
      return null;
    }
    $result = $results[0];
    if (!count($result))
    {
      return null;
    }
    return reset($result);
  }

  public function lastInsertId()
  {
    return $this->conn->lastInsertId();
  }
  
  public function colonPrefix($s)
  {
    return ':' . $s;
  }
  
  // Useful for simple inserts. The id of the last added row is returned
  // (ignore the return value if the table does not have an autoincrementing id column).
  public function insert($table, $params = array())
  {
    $columns = array_keys($params);
    $this->query('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(array($this, 'colonPrefix'), $columns)) . ')', $params);
    return $this->lastInsertId();
  }
  
  // Useful for simple inserts where you'd like the resulting row returned to you.
  // Not for use with tables that don't have an autoincrementing integer id
  // named 'id', so just use query or plain insert() as you see fit. Makes an extra query to get what
  // was really inserted since otherwise you won't get back values for the defaulted fields. 
  // This is just a timesaver, use it where apropos
  public function insertAndSelect($table, $params = array())
  {
    $columns = array_keys($params);
    $this->query('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(array($this, 'colonPrefix'), $columns)) . ')', $params);
    $id = $this->lastInsertId();
    return $this->query('select * from ' . $table . ' where id = ?', array('id' => $id));
  }
  
  // Handy for simple deletes where there is an 'id' column
  public function delete($table, $id)
  {
    $this->query('DELETE FROM ' . $table . ' WHERE id = :id', array('id' => $id));
  }

  // Good for updating a record with a simple id column
  public function update($table, $id, $params = array())
  {
    $q = 'UPDATE ' . $table . ' ';
    foreach ($params as $k => $v)
    {
      $q .= 'SET ' . $k . ' = :' . $k . ' ';
    }
    $q .= 'WHERE id = :id';
    return $this->query($q, $params);
  }

  // $relative can be -30 minutes, +30 days, etc.
  public function now($relative = '+0 seconds')
  {
    return date('Y-m-d H:i:s', strtotime($relative, time()));
  }
  
  public function getCommandsRun()
  {
    return $this->commandsRun;
  }
  
  public function tableExists($tableName)
  {
    if (!preg_match('/^\w+$/', $tableName))
    {
      throw new Exception("Bad table name in tableExists: $tableName\n");
    }
    $data = array();
    try
    {
      $data = $this->conn->query("SHOW CREATE TABLE $tableName")->fetchAll();
    } catch (Exception $e)
    {
    }
    return (isset($data[0]['Create Table']));    
  }
  
  public function columnExists($tableName, $columnName)
  {
    if (!preg_match('/^\w+$/', $tableName))
    {
      die("Bad table name in columnExists: $tableName\n");
    }
    if (!preg_match('/^\w+$/', $columnName))
    {
      die("Bad table name in columnExists: $columnName\n");
    }
    $data = array();
    try
    {
      $data = $this->conn->query("SHOW COLUMNS FROM $tableName LIKE '$columnName'")->fetchAll();
    } catch (Exception $e)
    {
    }
    return (isset($data[0]['Field']));
  }

  // Handy if you have p.title, p.body, etc. and you just want title, body, etc.
  public function getPrefixed($results, $prefix)
  {
    $nresults = array();
    foreach ($results as $result)
    {
      $values = array();
      foreach ($result as $key => $val)
      {
        $len = strlen($prefix);
        if (substr($key, 0, $len) === $prefix)
        {
          $values[substr($key, $len)] = $val;
        }
      }
      $nvalues[] = $values;
    }
    return $nvalues;
  }
  
  // Return a value that will be unique for the column (assuming no race condition of course;
  // you should still use UNIQUE INDEX). If the input passes slugify, the output will too.
  // Trusts table and column (you would never let users enter metadata like that, right?)
  
  public function uniqueify($table, $column, $value)
  {
    $cvalue = $value;
    $n = 1;
    while (!$this->unique($table, $column, $cvalue))
    {
      $n++;
      // Compatible with slugify
      $cvalue = $value . '-' . $n;
    }
    return $cvalue;
  }

  // Just check for uniqueness
  public function unique($table, $column, $value)
  {
    if (count($this->query('select * from ' . $table . ' where ' . $column . ' = :value', array('value' => $value))))
    {
      return false;
    }
    return true;
  }
}

class Guid
{
  static public function generate()
  {
    $guid = "";
    // 32 byte hex string, a lazy representation of a 16-byte integer,
    // eg way more than enough to guarantee there is no plausible chance of
    // duplicate GUIDs. Use a high quality random source if we can. This
    // code works on both Linux and MacOS and perhaps some other Unixes
    // as well. Elsewhere we use mt_rand which is not as cryptographically
    // awesome. If you have a Windows fix that doesn't break non-Windows
    // platforms or make this code super-hard to install, feel free to
    // contribute it
    if (file_exists('/dev/urandom'))
    {
      $in = fopen('/dev/urandom', 'rb');
      if ($in)
      {
        $data = fread($in, 8);
        fclose($in);
        for ($i = 0; ($i < strlen($data)); $i++)
        {
          $guid .= sprintf('%02x', ord(substr($data, $i, 1)));
        }
        return $guid;
      }
    }
    for ($i = 0; ($i < 16); $i++) {
      $guid .= sprintf("%02x", mt_rand(0, 255));
    }
    return $guid;
  }
}

class ParseException extends Exception
{
}
