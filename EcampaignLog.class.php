<?php

/**
 * Ecampaign Simple logging
 * @author johna
 *
 */

class EcampaignLog
{
  static $tableName ;
  static $dbVersion = "0.4" ;

  const tSend = 'send' ;
  const tSign = 'sign' ;
  const tVerify = 'verify' ;
  const tVerified = 'verified' ;

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
          target VARCHAR(50) NOT NULL,
          info VARCHAR(1024) NOT NULL,
          postID BIGINT(20) NOT NULL,
          PRIMARY KEY  (id),
	        INDEX postID_visitorEmail (postID, visitorEmail)
        );" ;

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
   * check that
   * @param $state
   * @param $postID
   * @param $target
   */

  function recordExists($state, $visitorEmail, $target,  $postID)
  {
    global $wpdb ;
    $numRows = $wpdb->get_var("SELECT count(*) FROM ". self::$tableName ." WHERE true and " .
      "state        = '". $state .          "' and ".
      "visitorEmail = '". $visitorEmail .   "' and ".
      "postID       =  ". $postID .         " and " .
      "target       = '". $target .         "' ;" );
    return $numRows > 0  ;
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
            'target' =>  self::ensureSet($field->target),
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
    $cols = Ecampaign::sPostID.",".Ecampaign::sVisitorName.",address, UNIX_TIMESTAMP(date) as stamp" ;
    $where = empty($postID) ? "" : " and postID=$postID ";
    $where .= " and (state='". self::tSend . "'" .
             " or   state='". self::tSign . "') " ;

    $drows = $wpdb->get_results("SELECT $cols FROM ".self::$tableName." WHERE 1=1 $where ORDER BY date desc LIMIT $limit", ARRAY_A);
    return $drows ;
  }


  function view()
  {
    global $wpdb ;
    $views = array(
      __("Normal") =>
        array(
          '_from' => self::$tableName,
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
          '_from' => self::$tableName,
          'date' => __('date'),
          'visitorName' => __('name'),
          'visitorEmail' => __('email'),
          'address' => __('address')
      ),
      __("Email & Name") =>
        array(
          '_from' => self::$tableName,
          'visitorEmail' => __('email'),
          'visitorName' => __('name')
      )
    );
    // add special view to include user profiles
    $fieldObject = get_option('scrm_fields');
    if (isset($fieldObject))
    {
      $views['User Profile'] = $this->getUserProfileView($fieldObject);
    }

    $fieldPresentations = array(
      'postID' => new _EcampaignPresentPostID(),
      'checkbox1' => new _EcampaignPresentCheckBox(),
      'checkbox2' => new _EcampaignPresentCheckBox(),
      'visitorEmail' => new _EcampaignPresentVisitorEmail(),
      'info' => new _EcampaignPresentInfo()
    );

    $filterByFields = array('state'=>'select',
                      'checkbox1'=>'select',
                      'checkbox2'=>'select',
                      'postID'=>'select',
                      'visitorEmail'=>'hidden');

    include_once dirname(__FILE__) . '/EcampaignTableView.class.php';
    $tableView = new EcampaignTableView();

    return $tableView->view("Ecampaign log", $views, $fieldPresentations, $filterByFields);
  }

  /**
   * Build a messy SQL string to retrieve all the profile fields
   * stored in wp_usermeta
   *
   * @param unknown_type $fieldObject
   */

  private function getUserProfileView($fieldObject)
  {
    global $wpdb ;
    $umFields = empty($fieldObject) ? array() : unserialize($fieldObject);
    $columns = array(
        'log.visitorEmail' => __('email'),
        'log.visitorName' => __('name'));

    $join = "" ; $where = "" ;  $metaColumns = array();

    $num=0 ; foreach($umFields as $f)
    {
      $join .= " left join {$wpdb->prefix}users as u$num on log.visitorEmail = u$num.user_email
               left join {$wpdb->prefix}usermeta as um$num on u$num.ID = um$num.user_id " ;
      $where .= " and um$num.meta_key='{$f['name']}' ";
      $metaColumns["um$num.meta_value as {$f['name']} "] = $f['title'] ;
      $num++  ;
    }
    $userMetaView = array("_from" => self::$tableName . " as log $join ",
                          "_where" => $where,
                          "_note"=>
    'Use the <a href="http://wordpress.org/extend/plugins/simple-crm/">CRM plugin</a> to
    <a href="options-general.php?page=scrm">edit the user profile fields displayed</a>' );

    return array_merge($columns, $metaColumns, $userMetaView);
  }
}

/**
 * helper classes for presenting field data
 *
 * @author johna
 */

class _EcampaignPresentPostID
{
  static function asString($postID)
  {
    $post = get_post($postID);  // support for pages? er no
    $postName = !empty($post) ? $post->post_name : $postID ;
    return $postName ;
  }
  static function inTable($postID)
  {
    $post = get_post($postID);  // support for pages? er no
    $postName = !empty($post) ? $post->post_name : $postID ;
    $postURL = site_url("?p=$postID");
    return "<a title='Go to $postName' href='$postURL'>$postName</a>";
  }
}


class _EcampaignPresentCheckBox
{
  static function asString($checked)
  {
    if ($checked == null) return '';
    if (empty($checked)) return 'off';
    return "on" ;
  }
  static function inTable($checked)
  {
    if ($checked == null) return '';
    if (empty($checked)) return '';  // so that ons stand out in table
    return "on" ;
  }
}

class _EcampaignPresentInfo
{
  static function asString($info)
  {
    return 'not available';
  }
  static function inTable($info)
  {
    $s1 = preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $info);
    $info = explode("\r\n", $s1); // info expected to be a block
    $s = $info[0] ;    // display the first lines
    if (count($info) > 1)         // display all lines whene user clicks/hovers or whatever
      $s .= "<span class='infoMore'>&nbsp;more</span><div class='infoBlock'>".implode("<br/>", $info)."</div>" ;
    return $s ;
  }
}

class _EcampaignPresentVisitorEmail
{
  static function asString($s)
  {
    return $s ;
  }
  static function inTable($visitor)
  {
    $query = EcampaignTableView::createQuery(array("visitorEmail"=>$visitor,"offset"=>null));
    $uri = $_SERVER['SCRIPT_URL'] . "?$query" ;
    return "<a title='show all records that match $visitor' href='$uri'>$visitor</a>";
  }
}