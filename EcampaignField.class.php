<?php

/**
 * Create a field as an HTML element
 * and handle $_POSTed data
 *
 * @author johna
 *
 */

class EcampaignField
{
  private static $nextIDForEachPage = array();  /// must maintain separate IDs for each page
  public $label, $attribMap, $attributes, $mandatory ;
  public $wrapper = null ;

  const tTextArea = 'textArea' ;

  function __construct($name = null)
  {
    $this->name = $name ;
    if (isset($name))
      $this->mandatory = true ;
  }

  function writeLabelledWrappedField()
  {
    $label = $this->label.":" ;

    if ($this->mandatory)
    {
      $this->attribMap['class'] .= ' mandatory' ;
      $label .= "&nbsp;*" ;
    }
    // note that $label can contain apostrophes.
    // input text is wrapped by div so error can be attached
    $this->attribMap['type'] = 'text' ;   // for jquery to read
    $content = !empty($this->label) ? "<label id='lab-$id' for='$id' >".$label."</label>" : "" ;
    $content .="<span class='ecinputwrap'>".$this->writeField()."</span>";
    return !isset($this->wrapper) ? $content : "<div class='$this->wrapper'>\r\n".$content."</div>\r\n" ;
  }

  /**
   * label follows field
   */
  function writeCheckBox()
  {
    return $this->writeField() . "<label for='$id'>$this->label</label>";
  }

  function writeWrappedField()
  {
    $content = $this->writeField();
    return !isset($this->wrapper) ? $content : "<div class='$this->wrapper'>\r\n".$content."</div>\r\n" ;
  }

  function writeField()
  {
    $id = self::nextID();
    $this->attributes = self::serializeAttributes($this->attribMap);
    unset($this->attribMap);  // save only the serialized attributes in the form
    $content = "<input id='$id' ".$this->attributes."/>";
    return $content ;
  }

  function writeTextArea()
  {
    $this->type = null ;
    $this->attributes = self::serializeAttributes($this->attribMap);
    unset($this->attribMap);
    return "<textarea ".$this->attributes.">".$this->value."</textarea>" ;
  }

  /**
   * float the label over the text field  (needs more work)
   *
  static function renderFieldFloatLabel($name, $label, $class)
  {
    $replace = "<p><input type='$type' name='$name' size='$width' class=' $class' onkeydown='ecam.removeLabel(this);' />
    <label class='label' for='$name'>$label</label></p>\n";

    return $replace ;
  }
   */
  /**
   * Read posted text fields
   * sanitize input
   * check length of field
   *
   * It does not return array of EcampaignField because it's too verbose to
   * access the value.
   *
   * @param unknown_type $fieldsDesired array of fields we want processing
   * @return an object where each member represents a posted value
   */

  static function requestMap($fieldsDesired)
  {
    return self::requestPartialMap(array_keys($fieldsDesired), $fieldsDesired);
  }

  static function requestPartialMap($fieldsDesired, $allFields)
  {
    $fieldSet = new stdClass ;
    foreach($fieldsDesired as $fieldName)
    {
      $field = $allFields[$fieldName];
      if (!($field instanceof EcampaignField))
      {
        throw new Exception("Field $fieldName not specified");
      }
      $field->request();
      $fname = $field->name ;
      $fieldSet->$fname = $field->value ;
    }
    return $fieldSet ;
  }

  function request()
  {
    // if value not posted, use any existing default value
    if (isset($_POST[$this->name]))
    {
      $value = $_POST[$this->name];

      if ($this->type = self::tTextArea)
      {
        $value = trim(html_entity_decode($value, ENT_QUOTES));
      }
      if (get_magic_quotes_gpc())
      {
        $value = stripslashes($value);
      }
      $this->value = $value;
    }
    $this->attribMap = self::parseAttributes($this->attributes);
    $this->assertLength()->assertCleanString();
    return $this ;
  }


  /**
   * check length meets any minimum specified in fieldReference
   * (derived from layout and default field list);_
   *
   * @returns the field so functions can be used inline
   */

  function assertLength()
  {
    if (empty($this->value))
      if (!$this->mandatory)
        return $this ;

    if (empty($this->attribMap['data-min']))
      return $this ;    // no min specified

    $this->min = $ths->attribMap['data-min'];
    if (is_numeric($this->min))
    {
      if (strlen($this->value) < $this->min)
      {
        throw new Exception($this->min . __(" or more characters expected in") . " $this->name: $this->value");
      }
    }
    return $this ;
  }


  /**
   * check for field injection in headers.  Think that any injection has
   * to be terminated with a \n character for it to influence the email header.
   * Note : filter_var DISABLED right now
   *
   * @returns the field so functions can be used inline
   */

  function assertCleanString($use_filter_var = false)
  {
    if ($use_filter_var && (function_exists('filter_var')))
    {
      $ftarget = filter_var($target, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      if ($ftarget != $target)
        throw new Exception(__("invalid character in") . " $this->name: ". $this->value);
      return ;
    }
//    if (preg_match("/[\\000-\\037<>]/", $target))  // probably inadequate
//      throw new Exception(__("unexpected character in"). " $fieldName: ". $target);
    return ($field);
  }

  /**
   * Parse a string of attributes and return map of key value pairs
   * (cloned from EcampaignField)
   *
   * Attributes can be single or double quoted.
   * regexp test string: ab-c_d=%abc123 winpath="C:\john smith" upath=/home/john/web cdef='ede dddew'
   * @param $attributes
   */

  static function parseAttributes($attributes)
  {
    $map = array();
    $count = preg_match_all("!(\w[\w-]+)=([\w\\\/\.%]+|([\'\"])(.+?)\\3)!", $attributes, $matches);
    for ($j = 0  ; $j < $count ; $j++)
    {
      list($key, $value, $unquotedValue) = array($matches[1][$j],$matches[2][$j],$matches[4][$j]);
      $map[$key] = !empty($unquotedValue) ? $unquotedValue : $value ;
    }
    return $map ;
  }

  /**
   * convert attribute map into string (reverses parseAttributes)
   *
   * Attributes are requoted
   *
   * @param $attributes
   */

  static function serializeAttributes($map)
  {
    $s = "" ; foreach($map as $key=>$val)   // implode map.
    {
      if (!empty($val))
        $s.= $key."=\"".$val."\" " ;          // requote attributes
    }
    return $s ;
  }

  /**
   * provide unique ids for elements on each page. Notes
   *
   * 1. that wordpress can present multiple full length posts on its front page so IDs
   * must be maintained for each postID
   * 2. only expecting to create an ID 'inside the wordpress loop'
   */
  static function nextID()
  {
    $postID = get_the_ID();
    if (!isset(self::$nextIDForEachPage[$postID]))
      self::$nextIDForEachPage[$postID] = 0 ;

    $ab = self::$nextIDForEachPage;
    return ('ec'.self::$nextIDForEachPage[$postID]++);
  }
}