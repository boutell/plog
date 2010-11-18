<?php

class HtmlSimplifyNotHtmlException extends Exception
{
  
}

class HtmlSimplify
{
  // The default list of allowed tags for $this->simplify().
  // These work well for user-generated content made with FCK.
  // You can now alter this list by passing a similar list as the second
  // argument to $this->simplify(). An array of tag names without braces is also allowed.
  
  // Reserving h1 and h2 for the site layout's use is generally a good idea
  
  protected $defaultAllowedTags =
    '<h3><h4><h5><h6><blockquote><p><a><ul><ol><nl><li><b><i><strong><em><strike><code><hr><br><div><table><thead><caption><tbody><tr><th><td><pre>';

  // The default list of allowed attributes for $this->simplify().
  // You can now alter this list by passing a similar array as the fourth
  // argument to $this->simplify().

  protected $defaultAllowedAttributes = array(
    "a" => array("href", "name", "target"),
    "img" => array("src")
  );
  
  // Subtle control of the style attribute is possible, but we don't allow
  // any styles by default. See the allowedStyles argument to simplify()
  
  protected $defaultAllowedStyles = array();

  // allowedTags can be an array of tag names, without < and > delimiters, 
  // or a continuous string of tag names bracketed by < and > (as strip_tags 
  // expects). 
  
  // By default, if the 'a' tag is in allowedTags, then we allow the href attribute on 
  // that (but not JavaScript links). If the 'img' tag is in allowedTags, 
  // then we allow the src attribute on that (but no JavaScript there either).
  // You can alter this by passing a different array of allowed attributes.

  // If $complete is true, the returned string will be a complete
  // HTML 4.x document with a doctype and html and body elements.
  // otherwise, it will be a fragment without those things
  // (which is what you almost certainly want).
  
  // If $allowedAttributes is not false, it should contain an array in which the
  // keys are tag names and the values are arrays of attribute names to be permitted.
  // Note that javascript: is forbidden at the start of any attribute, so attributes
  // that act as URLs should be safe to permit (we now check for leading space and
  // mixed case variations of javascript: as well).
  
  // If $allowedStyles is not false, it should contain an array in which the keys
  // are tag names and the values are arrays of CSS style property names to be permitted.
  // This is a much better idea than just allowing the style attribute, which is one
  // of the best ways to kill the layout of an entire page.
  //
  // An example:
  //
  // array("table" => array("width", "height"),
  //   "td" => array("width", "height"),
  //   "th" => array("width", "height"))
  //
  // Note that rich text editors vary in how they handle table width and height; 
  // Safari sets the width and height attributes of the tags rather than going
  // the CSS route. The simplest workaround is to allow that too.

  public function simplify($value, $allowedTags = false, $complete = false, $allowedAttributes = false, $allowedStyles = false)
  {
    if ($allowedTags === false)
    {
      $allowedTags = $this->defaultAllowedTags;
    }
    if ($allowedAttributes === false)
    {
      // See above
      $allowedAttributes = $this->defaultAllowedAttributes;
    }
    if ($allowedStyles === false)
    {
      // See above
      $allowedStyles = $this->defaultAllowedStyles;
    }
    $value = trim($value);
    if (!strlen($value))
    {
      // An empty string is NOT something to panic
      // and generate warnings about
      return '';
    }
    if (is_array($allowedTags))
    {
      $tags = "";
      foreach ($allowedTags as $tag)
      {
        $tags .= "<$tag>";
      }
      $allowedTags = $tags;
    }
    $value = strip_tags($value, $allowedTags);

    // Now we use DOMDocument to strip attributes. In principle of course
    // we could do the whole job with DOMDocument. But in practice it is quite
    // awkward to hoist subtags correctly when a parent tag is not on the
    // allowed list with DOMDocument, and strip_tags takes care of that
    // task just fine.

    // At first I used matt@lvi.org's function from the strip_tags 
    // documentation wiki. Unfortunately preg_replace tends to return null
    // on some of his regexps for nontrivial documents which is pretty
    // disastrous. He seems to have some greedy regexps where he should
    // have ungreedy regexps. Let's do it right rather than trying to
    // make regular expressions do what they shouldn't.

    // We also get rid of javascript: links here, a good idea from 
    // Matt's script.
    
    $oldHandler = set_error_handler('HtmlSimplify::warningsHandler', E_WARNING);
    
    // If we do not have a properly formed <html><head></head><body></body></html> document then
    // UTF-8 encoded content will be trashed. This is important because we support fragments
    // of HTML containing UTF-8
    if (!preg_match("/<head>/i", $value))
    {
      $value = '
      <html>
      <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
      </head>
      <body>
      ' . $value . '
      </body>
      </html>
      ';
    }
    try 
    {
      // Specify UTF-8 or UTF-8 encoded stuff passed in will turn into sushi.
      $doc = new DOMDocument('1.0', 'UTF-8');
      $doc->strictErrorChecking = true;
      $doc->loadHTML($value);
      $this->stripAttributesNode($doc, $allowedAttributes, $allowedStyles);
      // Per user contributed notes at 
      // http://us2.php.net/manual/en/domdocument.savehtml.php
      // saveHTML forces a doctype and container tags on us; get
      // rid of those as we only want a fragment here
      $result = $doc->saveHTML();
    } catch (HtmlSimplifyNotHtmlException $e)
    {
      // The user thought they were entering text and used & accordingly (as they so often do)
      $result = htmlspecialchars($value);
    }

    if ($oldHandler)
    {
      set_error_handler($oldHandler);
    }
      
    if ($complete)
    {
      // Don't allow whitespace to balloon
      return trim($result);
    }

    $result = $this->documentToFragment($result);
		return $result;
  }

  public function documentToFragment($s)
  {
    // Added trim call because otherwise size begins to balloon indefinitely
    return trim(preg_replace(array('/^<!DOCTYPE.+?>/', '/<head>.*?<\/head>/i'), '', 
      str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $s)));
  }
  
  static public function warningsHandler($errno, $errstr, $errfile, $errline) 
  {
    // Most warnings should be ignored as DOMDocument cleans up the HTML in exactly
    // the way we want. However "no name in entity" usually means the user thought they
    // were entering plaintext, so we should throw an exception signaling that
    
    if (strstr("no name in Entity", $errstr))
    {
      throw new HtmlSimplifyNotHtmlException();
    }
    return;
  }
  
  protected function stripAttributesNode($node, $allowedAttributes, $allowedStyles)
  {
    if ($node->hasChildNodes())
    {
      foreach ($node->childNodes as $child)
      {
        $this->stripAttributesNode($child, $allowedAttributes, $allowedStyles);
      }
    }
    if ($node->hasAttributes())
    {
      $removeList = array();
      foreach ($node->attributes as $index => $attr)
      {
        $good = false;

        // I snipped out support for allowing certain styles here.
        // You can find it in aHtml::simplify in Apostrophe but I am
        // trying to keep this lean and mean
        
        if (!$good)
        {
          if (isset($allowedAttributes[$node->nodeName]))
          {
            foreach ($allowedAttributes[$node->nodeName] as $attrName)
            {
              // Be more careful about this: leading space is tolerated by the browser,
              // so is mixed case in the protocol name (at least in Firefox and Safari, 
              // which is plenty bad enough)
              if (($attr->name === $attrName) && (!preg_match('/^\s*javascript:/i', $attr->value)))
              {
                // We keep this one
                $good = true;
              }
            }
          }
        }
        if (!$good)
        {
          // Off with its head
          $removeList[] = $attr->name; 
        }
      }
      foreach ($removeList as $name)
      {
        $node->removeAttribute($name);
      }
    }
  }
}
