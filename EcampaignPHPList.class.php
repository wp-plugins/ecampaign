<?php

/**
 * Subscribe ecampaign site vistors to PHPList by updating
 * the PHPList database.
 *
 * This class is likely to break if the PHPList database scheme changes.
 * PHPList does not appear to have any API (which would be far better to use.
 */

class EcampaignPHPList
{
  private static $db ;
  private $args ;

  function checkConfiguration($connectDB=false)
  {
    $this->args = EcampaignField::parseAttributes(get_option('ec_subscriptionParams'), true);
    $configFile = $this->args['configFile'];
    if (empty($configFile))
      return __("Configuration error: configFile not set.");

    if (!file_exists($configFile))
      return __("Configuration error: Cannot access configFile:$configFile");

    include_once $configFile;


    if (empty($database_host))
      return('$database_host not defined');
    if (empty($database_name))
      return('$database_name not defined');
    if (empty($database_user))
      return('$database_user not defined');
    if (empty($database_password))
      return('$database_password not defined');

    $this->table_prefix = $table_prefix ;
    $this->usertable_prefix = $usertable_prefix ;

    if (!isset($this->args['optin']) || !isset($this->args['listID']))
      return (__("Configuration error: optin=checkbox1 or listID=xxx expected where xx is the PHPList id"));

    if ($connectDB && empty(self::$db))
    {
      self::$db = mysql_connect($database_host, $database_user, $database_password);
      if (!self::$db) {
        return('Could not connect to PHPList database: ' . mysql_error());
      }
      mysql_select_db($database_name, self::$db);
    }
    return "" ;
  }
  /**
   *
   * @param unknown_type $templateFields  full set of fields and attributes
   * @param unknown_type $fieldSet        easy to access set of fields.
   */
  function subscribe($templateFields, $fieldSet)
  {
    $msg = self::checkConfiguration(true);
    if (!empty($msg))
      throw new Exception($msg);

    $optin = $this->args['optin'];
    $optinb = $optin=='true' || $fieldSet->$optin == 'on' || $fieldSet->$optin == 1 ;

    if ($optin=='true')
      $optinb = true ;
    else
    {
      if (!isset($templateFields[$optin]))
        throw new Exception("Configuration error: Unable to test $optin because it is not a valid field");

      $optinb = $templateFields[$optin]->value == 'on' || $templateFields[$optin]->value == 1 ;
    }

    $listID = $this->args['listID'];
    if ($optinb && is_numeric($listID))
      self::addEmailToList($fieldSet->visitorEmail, $listID);

    mysql_close(self::$db);
  }

  /**
   * the next functions have been lifted (and fixed) from phplist.php
   */
  function addUserToList($userid,$listid) {
    $result = mysql_query(sprintf('replace into %s (userid,listid,entered) values(%d,%d,now())',
      $this->table_prefix . "listuser", $userid, $listid), self::$db);
    if (!$result)
        throw new Exception(__("Failed to subscribe $email to PHPList list ".$listid));
  }

  function addEmail($email, $password = "") {
    $result = mysql_query($ab = sprintf('insert into %s set email = "%s",
      entered = now(),password = "%s",
      passwordchanged = now(),disabled = 0,
      uniqid = "%s",htmlemail = 1',
      $this->usertable_prefix . "user",
      $email, $password, "getUniqid()"), self::$db);
    if (!$result)
        throw new Exception(__("Failed to add $email to PHPList"));
    $id = mysql_insert_id(self::$db);
    /*
     * not sure what this does
     *
    if (is_array($_SESSION["userdata"])) {
      saveUserByID($id,$_SESSION["userdata"]);
    }
    $_SESSION["userid"] = $id;
    */
    return $id;
  }
//      throw new Exception(mysql_error(self::$db));
  function addEmailToList($email, $listid)
  {
    $userid = null ;
    if (empty($email) || empty($listid)) return 0;

    $email = mysql_real_escape_string($email);

    $result = mysql_query(sprintf('select * from %s where email = "%s"', $this->usertable_prefix."user", $email), self::$db);
    if ($result)
    {
      $row = mysql_fetch_array($result, MYSQL_ASSOC);
      $userid = $row['id'];
    }

    if (empty($userid))
    {
      $userid = $this->addEmail($email);
      if (empty($userid))
        throw new Exception(__("Failed to get PHPList userid for $email"));
    }
    return $this->addUserToList($userid, $listid);
  }
}