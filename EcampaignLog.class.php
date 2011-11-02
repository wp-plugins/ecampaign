<?php

/**
 * Ecampaign Simple logging
 * @author johna
 *
 */

class EcampaignLog
{
  static $tableName ;
  static $dbVersion = "0.2" ;

  function __construct()
  {
    global $wpdb ;
    $wpdb->show_errors();
    self::$tableName = $wpdb->prefix . "ecampaign_log";
  }

  function install()
  {
    global $wpdb ;
    if ($wpdb->get_var("show tables like '{self::$tableName}'") != self::$tableName)
    {
      $sql = "CREATE TABLE " . self::$tableName . " (
          id bigint(20) NOT NULL AUTO_INCREMENT,
          date timestamp(14) NOT NULL,
          state char(20) NOT NULL,
          visitorName VARCHAR(50) NOT NULL,
          visitorEmail VARCHAR(50) NOT NULL,
          address VARCHAR(100) NOT NULL,
          checkbox1 bool NOT NULL,
          checkbox2 bool NOT NULL,
          info VARCHAR(1024) NOT NULL,
          postID BIGINT(20) NOT NULL,
          PRIMARY KEY  (id)
        );";

      require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
      dbDelta($sql);

      add_option("ecampaign_log", self::$dbVersion);
    }
  }

  function uninstall()
  {
    global $wpdb ;
    $drop = $wpdb->get_var("drop table " . self::$tableName );
  }


  /**
   * write log record to database
   * @param state
   * @param $fields
   */

  function write($state, $field, $info = null)
  {
    $infoString = is_array($info) ? implode("\r\n", $info) : $info ;

    global $wpdb;
    $wpdb->insert(self::$tableName, array(
            'state' => $state,
            'visitorName' =>  self::ensureSet($field->visitorName),
            'visitorEmail' => self::ensureSet($field->visitorEmail),
            'address' => isset($field->postalAddress) ? $field->postalAddress->toString() : "",
            'checkbox1' => $field->checkbox1 ? 1 : 0, //very nasty
            'checkbox2' => $field->checkbox2 ? 1 : 0,
            'info' =>  isset($infoString) ? $infoString : $field->subject,
            'postID' => (int) self::ensureSet($field->postID)  ));
  }

  static function ensureSet($string)
  {
    return isset($string) ? $string : "" ;
  }


  function getRecentActivists($postID, $limit)
  {
    global $wpdb ;
    $cols = Ecampaign::sVisitorName.",address, UNIX_TIMESTAMP(date) as stamp" ;
    $where = empty($postID) ? "" : " and postID=$postID ";
    $where .= " and (state='". Ecampaign::tSent . "'" .
             " or   state='". Ecampaign::tSigned . "') " ;

    $drows = $wpdb->get_results("SELECT $cols FROM ".self::$tableName." WHERE 1=1 $where ORDER BY date desc LIMIT $limit", ARRAY_A);
    return $drows ;
  }


  function view()
  {
    $views = array(
      __("Normal") =>
        array(
          'id' => __('ID'),
          'date' => __('date'),
          'state' => __('action'),
          'visitorName' => __('name'),
          'visitorEmail' => __('email'),
          'postID' => __('post'),
          'checkbox1' => __('c1'),
          'checkbox2' => __('c2'),
//          'if(checkbox1,"yes","no") as checkbox1' => __('c1'),  //does not work with filters
//          'if(checkbox2,"yes","no") as checkbox2' => __('c2'),
          'info' => __('info')
      ),
      __("Petition") =>
        array(
//          '@rownum:=@rownum+1' => __('num'),
          'date' => __('date'),
          'visitorName' => __('name'),
          'visitorEmail' => __('email'),
          'address' => __('address')
      ),
      __("Email & Name") =>
        array(
//          '@rownum:=@rownum+1' => __('num'),
          'visitorEmail' => __('email'),
          'visitorName' => __('name')
      )

    );
    $filterByFields = array('state', 'checkbox1', 'checkbox2', 'postID');

    include_once dirname(__FILE__) . '/EcampaignTableView.class.php';
    $tableView = new EcampaignTableView();

    return $tableView->view("Ecampaign log", self::$tableName, $views, $filterByFields);
  }
}


