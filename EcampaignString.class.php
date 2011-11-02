<?php
/**
 * Ecampaign String utilities
 * Intended to make the code slightly more readable.
 * But not sure how efficient this is,
 * @author johna
 *
 */

class EcampaignString
{
  protected $sb ;
  function __construct($s = null)
  {
    if (isset($s))
      $this->set($s);
    else
      $this->sb = array();
  }

  function set($item)
  {
    if (is_string($item))
      $this->sb = array($item) ;
    else
      if (is_array($item))
        $this->sb = $item;
      else throw new Exception("Unable to set item");
    return $this ;
  }

  function add($item)
  {
    if (is_string($item))
      $this->sb[] = $item ;
    else
      if (is_array($item))
        $this->sb = array_merge($this->sb, $item);
      else
        if (is_a($item, get_class()))
          $this->sb = array_merge($this->sb, $item->sb);
        else
        {
          throw new Exception("Unable to add item to " . $this->toString());
        }
    return $this ;
  }

  function addTo($another)
  {
    $another->sb[] = implode($this->sb);
    $this->sb = array();
    return $this ;
  }

  /**
   * WRAP the whole string buffer
   * @param unknown_type $tag
   * @param unknown_type $attributes
   */

  function wrap($tag, $attributes = null)
  {
    array_unshift($this->sb, isset($attributes) ? "<$tag $attributes>" : "<$tag>" );
    array_push($this->sb, "</$tag>");
    return $this ;
  }

  function wrapAll($tag, $attributes = null)
  {
    $another = array();
    foreach($this->sb as $s)
    {
      array_push($another, isset($attributes) ? "<$tag $attributes>$s</$tag>" :  "<$tag>$s</$tag>" );
    }
    $this->sb = $another ;
    return $this ;
  }

  function implode($glue)
  {
    $this->sb = array(implode($glue, $this->sb));
    return $this ;
  }

  function removeEmptyFields()
  {
    $another = array();
    foreach($this->sb as $s)
    {
      if (!empty($s))
        $another[] = $s ;
    }
    $this->sb = $another;
    return $this ;
  }

  function asHtml()
  {
    return implode("\r\n", $this->sb);
  }

  function asBlock()
  {
    return implode("\r\n", $this->sb);
  }

  function toString()
  {
    return implode(", ", $this->sb);
  }
}
