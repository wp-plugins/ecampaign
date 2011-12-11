<?php
/*
 Plugin Name: Ecampaign
 Plugin URI: http://wordpress.org/extend/plugins/ecampaign/
 Description: Allows a simple email based campaign action to be embedded into any wordpress page or post.
 Version: 0.83
 Author: John Ackers
 Author URI: john.ackers HATT ymail.com

 Copyright 2011  John Ackers

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License, version 2, as
 published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/


/**
 * runs when page is first loaded
 * @param $atts
 * @return unknown_type
 */

$ecampaign = null ;

function ecampaign_short_code($attributes, $body)
{
  $pageAttributes = (object) shortcode_atts_case_sensitive(array(
    'to'             => '',
    'targetEmail'    => '',
    'subject'        => '',
    'targetSubject'  => '',
    'friendSubject'  => '',
    'campaignEmail'  =>  get_option('ec_campaignEmail'),
    'class' => 'EcampaignTarget',
    'onlyIn' => null,             // restricts campaign to particular area
    'notIn' => null,
    'hidden' => null,             // form initially hidden
    'testMode' => EcampaignTestMode::sNormal
    ), $attributes);

  try {
//    $pageAttributes = (object) $attributes ;
    $ecampaign = _createFromClassPath($pageAttributes->class);
    return $ecampaign->createPage($pageAttributes, $body);
  }
  catch (Exception $e)  // exception does not get thrown
  {
    die(__("Unable to load {$pageAttributes->class}. Error:" . $e->getMessage()));
  }
}


/**
 * Runs when site visitor clicks on lookup/sign/send and initiates ajax post
 * convert exceptions thrown by handlers into structured responses encoded as json.
 * @return it does not, it always exits.
 */

function ecampaign_ajax_post() {

    $ecampaign = null;
    try {

      $ecampaign = _createFromClassPath($class=$_POST['class']);
      $method = $_POST['method'];
      if (empty($method) || !in_array($method, $ecampaign->validAjaxMethods))
        throw new Exception(__("handler not accessible: $class->$method"));

      if (false == check_ajax_referer('ecampaign', false, false))
      {
        if (!isset($_REQUEST['_ajax_nonce']))
          throw new Exception(__("request rejected, missing security token (nonce value)."));
        else
          throw new Exception(__("request rejected, mismatching security token (nonce value),
          try clicking again. If that fails try refreshing page (F5) and reentering data."));
      }
      $ecampaign->restoreForm($_POST['postID'],$_POST['formID']);
      $response = $ecampaign->$method();
      $response = is_array($response) ? $response : array("success"=> true, "msg" => $response);
    }
    catch (Exception $e)
    {
      $log = new EcampaignLog();
      $log->write("exception", $ecampaign->field, $e->getMessage());
      $response = array("success" => false, "_ajax_nonce" => wp_create_nonce('ecampaign'),
      "msg" => "Error: " . $e->getMessage() . ". " . __("This error has been logged! Sorry.")) ;
    }
    header( "Content-Type: application/json" );
    echo json_encode($response);
    exit ;
}


function _createFromClassPath($classPath)
{
  $classPath = isset($classPath) ? $classPath : "Ecampaign" ;
  $classFile = dirname(__FILE__). "/" . $classPath . ".class.php" ;
  if (!file_exists($classFile))
    throw new Exception (__("Cannot open:$classFile"));
  include_once $classFile ;
  $classPath = split('/',$classPath); // loose the directory
  $class = $classPath[count($classPath)-1] ;
  return new $class ;
}


/**
 * Runs on every page, perhaps this can be avoided
 * @return unknown_type
 */

function ecampaign_load() {
  wp_enqueue_style('ecampaign-style', plugin_dir_url( __FILE__ ) . 'public.css');

  wp_enqueue_script( 'ecampaign-ajax-request', plugin_dir_url( __FILE__ ) . 'public.js', array('jquery'));
  wp_localize_script('ecampaign-ajax-request', 'ecampaign', array( 'ajaxurl' => admin_url('admin-ajax.php')));

  if (function_exists('load_plugin_textdomain')) {
    load_plugin_textdomain('ecampaign', false, plugin_dir_url( __FILE__ ).'languages' );
  }
}




// todo add lookup !!!!  class , method.  0 code handling


function ecampaign_activation()
{
  include_once dirname(__FILE__) . '/Ecampaign.class.php';  // load log class
  include_once dirname(__FILE__) . '/ecampaign-admin.php';  // load only for admin pages
  ec_activation();
}



function ecampaign_uninstall()
{
  include_once dirname(__FILE__) . '/ecampaign.class.php';  // load log class
  include_once dirname(__FILE__) . '/ecampaign-admin.php';  // load only for admin pages
  ec_uninstall();
}


// invoked when in wp-admin and only when plugin is active

function ecampaign_admin_enqueue_scripts()
{
  wp_enqueue_style('ecampaign-admin-style', plugin_dir_url( __FILE__ ) . 'admin.css');
  wp_enqueue_script( 'ecampaign-admin-js', plugin_dir_url( __FILE__ ) . 'admin.js', array('jquery'));
}


function ecampaign_admin_menu()
{
  include_once dirname(__FILE__) . '/ecampaign-admin.php';  // load only for admin pages
  ec_admin_menu();
}


function ecampaign_add_settings_link($links, $file)
{
  if ($file == plugin_basename(__FILE__))
  {
    $link = '<a href="options-general.php?page=ecampaign">'.__("Settings").'</a>';
    array_unshift($links, $link);
  }
  return $links;
}


if (is_admin())
{
  add_action('wp_ajax_ecampaign',        'ecampaign_ajax_post');
  add_action('wp_ajax_nopriv_ecampaign', 'ecampaign_ajax_post');
  add_action('admin_menu',               'ecampaign_admin_menu');
  add_action('admin_enqueue_scripts',    'ecampaign_admin_enqueue_scripts');
  add_filter('plugin_action_links',      'ecampaign_add_settings_link', 10, 2 );

  register_activation_hook(plugin_basename(__FILE__), 'ecampaign_activation');
  register_uninstall_hook(__FILE__, 'ecampaign_uninstall');
}
else
{
  add_action('init', 'ecampaign_load', 1);
  add_shortcode('ecampaign', 'ecampaign_short_code');
}

// modified version of wordpress function that allows the
// code to be case sensitive yet user can use any case

function shortcode_atts_case_sensitive($pairs, $atts) {
  $atts = (array)$atts;
  $out = array();
  foreach($pairs as $name => $default) {
    $lcname = strtolower($name);
    if ( array_key_exists($lcname, $atts) )
      $out[$name] = $atts[$lcname];
    else
      $out[$name] = $default;
  }
  return $out;
}

include_once dirname(__FILE__). "/EcampaignWidget.class.php" ;
add_action( 'widgets_init', create_function( '', 'register_widget("EcampaignWidget");' ) );


/**
 * This class is overkill but it is to make sure that the plugin
 * always operates in the correct test mode and doesn't accidentally
 * send emails because of a careless bug.
 *
 * @author johna
 *
 */


class EcampaignTestMode
{
  public $mode ;
  const sNormal = 'normal' ;
  const sDiverted = 'diverted' ;
  const sSuppressed = 'suppressed' ;

  function __construct($setting = self::sSuppressed)
  {
    $this->ar = array (
      self::sNormal     => __('normal mode'),
      self::sDiverted   => __('emails diverted to campaign mailbox'),
      self::sSuppressed => __('email delivery suppressed'));

    if (isset($setting))
      $this->is($setting); // throw exception if not valid mode
    // the logic here is that if testMode set on the local page
    // takes precedence over the site wide testMode setting
    $this->mode = isset($setting) && $setting != self::sNormal
      ? $this->mode = $setting : get_option("ec_testMode");

    if ($this->mode == false)
      $this->mode = self::sSuppressed ; //set initial stored value
    else
    {
      if (!array_key_exists($this->mode, $this->ar))
        $this->mode = self::sSuppressed; // override stored value
    }
  }

  function isNormal()
  {
    return($this->is(self::sNormal));
  }

  function isDiverted()
  {
    return($this->is(self::sDiverted));
  }

  function isSuppressed()
  {
    return($this->is(self::sSuppressed));
  }

  function is($testValue)
  {
    if (!array_key_exists($testValue, $this->ar))
      throw new Exception("Testing for unknown mode: $testValue");
    return $testValue == $this->mode ;
  }

  function toString()
  {
    return $this->ar[$this->mode];
  }
}


?>
