<?php
/*
 Plugin Name: Ecampaign
 Plugin URI: http://wordpress.org/extend/plugins/ecampaign/
 Description: Allows a simple email based campaign action to be embedded into any wordpress page or post.
 Version: 0.77
 Author: John Ackers
 Author URI: john.ackers ymail.com

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

function ecampaign_short_code($atts, $body) {
  include_once dirname(__FILE__) . '/ecampaign.class.php';  // load only for pages using shortcode
  if ($ecampaign == null)
    $ecampaign = new Ecampaign();
  return $ecampaign->createPage($atts, $body);
}

/**
 * runs when site visitor clicks on send and initiates ajax post
 * @return unknown_type
 */

function ecampaign_ajax_post() {
  include_once dirname(__FILE__) . '/ecampaign.class.php';  // load only for posts
  if ($ecampaign == null)
    $ecampaign = new Ecampaign();
  return $ecampaign->ajaxPost();
}

/**
 * Runs on every page, perhaps this can be avoided
 * @return unknown_type
 */

function ecampaign_load() {
  wp_enqueue_style('ecampaign-style', plugin_dir_url( __FILE__ ) . 'ecampaign.css');

  wp_enqueue_script( 'ecampaign-ajax-request', plugin_dir_url( __FILE__ ) . 'ecampaign.js', array('jquery'));
  wp_localize_script('ecampaign-ajax-request', 'ecampaign', array( 'ajaxurl' => admin_url('admin-ajax.php')));

  if (function_exists('load_plugin_textdomain')) {
    load_plugin_textdomain('ecampaign', false, plugin_dir_url( __FILE__ ).'languages' );
  }
}


add_action('init', 'ecampaign_load', 1);

add_shortcode('ecampaign', 'ecampaign_short_code');


/**
 * bug fix added in version 0.76
 * In version 0.75, a counter was added to wp_postmeta. However the counter was
 * not specified as being 'unique' and as a result one row was added to the
 * database everytime the counter was incremented.
 *
 * So recreate the counter for each post with unique to to true.
 */


function ecampaign_activate()
{
  define ("counter", "ecCounter");

  $posts = get_posts(array('meta_key' => counter));
  $log = "Updated: ";

  foreach($posts as $post)
  {
    $val = get_post_meta($post->ID, counter, true);

    if ($val !== false)
    {
      delete_post_meta($post->ID,  counter);
      add_post_meta($post->ID,  counter,  $val,  true);
      $log .= $post->ID . ":" . $val . " " ;
    }
  }
  return $log ;
}



register_activation_hook(plugin_basename(__FILE__), 'ecampaign_activate');


function ecampaign_unset_options()
{
  delete_option('ec_campaignEmail' );
  delete_option('ec_layout' );
  delete_option('ec_checkbox1' );
  delete_option('ec_checkbox2' );
  delete_option('ec_thankYouText' );
  delete_option('ec_friendsLayout' );
  delete_option('ec_testMode' );
  delete_option('ec_mailer' );
  delete_option('ec_checkdnsrr' );
}
register_uninstall_hook(__FILE__, 'ecampaign_unset_options');


if (is_admin())
{
  add_action('wp_ajax_ec_sendToTarget',        'ecampaign_ajax_post');
  add_action('wp_ajax_ec_sendToFriend',        'ecampaign_ajax_post');

  add_action('wp_ajax_nopriv_ec_sendToTarget', 'ecampaign_ajax_post');
  add_action('wp_ajax_nopriv_ec_sendToFriend', 'ecampaign_ajax_post');

  add_action('admin_menu', 'ecampaign_menu');
}
else
{

}


/**
 * administrator settings which get put under (general) settings on the admin page
 * @return nothing
 */

function ecampaign_menu() {

//  include_once dirname(__FILE__) . '/ecampaign-admin.php';  // load only for admin pages
  include_once dirname(__FILE__) . '/ecampaign.class.php';  //
  add_options_page('Ecampaign Options', 'Ecampaign', 'manage_options', 'ecampaign', 'ecampaign_options');

  //call register settings function
  add_action( 'admin_init', 'ecampaign_registerOptions' );
}


/**
 * Bind these options to settings page which will make
 * wordpress save them.
 *
 * @return unknown_type
 */


function ecampaign_registerOptions()
{
//  ecampaign_unset_options();
  register_setting( 'ec-settings', 'ec_testMode' );
  register_setting( 'ec-settings', 'ec_campaignEmail' );
  register_setting( 'ec-settings', 'ec_layout' );
  register_setting( 'ec-settings', 'ec_checkbox1' );
  register_setting( 'ec-settings', 'ec_checkbox2' );
  register_setting( 'ec-settings', 'ec_thankYouText' );
  register_setting( 'ec-settings', 'ec_friendsLayout' );
  register_setting( 'ec-settings', 'ec_testMode' );
  register_setting( 'ec-settings', 'ec_mailer' );
  register_setting( 'ec-settings', 'ec_captchadir' );
}


/**
 * add options but put prefix in front of all names.
 * To provide forward, compatability with old versions,
 * use values of old, unprefixed names if they exist
 */

function ecampaign_add_option($name, $prompt, $defaultValue, &$keys, $autoload)
{
  $val = get_option($name);
  if (empty($val))
  {
    $oldName = substr($name,3); // strip off ec_
    $val = get_option($oldName);
    if (!empty($val))
    {
      add_option($name, $val,'', 'no');
      delete_option($oldName);
      return ;
    }
  }
  add_option($name, $defaultValue,'', $autoload);
  $keys[0][$name] = $prompt;		// save prompts
  $keys[1][$name] = $defaultValue;	// and default values
}



function ecampaign_options()
{
  $prompt = array(); $default = array();
  $keys =  array(&$prompt, &$default);

  ecampaign_add_option('ec_testMode',
  __("Test Mode: Changes the <strong>To:</strong> field of the outgoing email from 'targetEmail' to 'campaignEmail'. ") .
  __("This feature prevents emails being sent to the target organisation accidentally."), '', $keys, 'no');

  // copies of emails are sent here

  ecampaign_add_option('ec_campaignEmail',
  __("Campaign mailbox address 'campaignEmail'; copies of activists emails are sent here. You can override it on
      individual email alerts."), 'campaign.email@not.set.up', $keys, 'no');

  // setting up the default form layout

  ecampaign_add_option('ec_layout',

  __("<p>Target Form template. Add and remove fields that you want to collect. Valid fields are: ") .
     "%to %subject %body %name %address1 %address2 %address3 %city %ukpostcode %postcode %zipcode %state %country " .
     "%email %send %checkbox1 %checkbox2 %verificationCode %captcha %counter. </p><p>" .
  __("Use %captcha to include a standard captcha mechanism in the form. </p><p>").
  __("Use %verificationCode to send an email to the site visitor's email address containing a verification code. It should
  not be necessary to deploy both captcha and verificationCode on the same site.</p><p>").
  __("Adjust size of body field using %44.55body where 44 represents number of columns and 55 represents number of rows. ").
  __("Adjust size of other fields using %33.10city where 33 represents field size and 10 is minimum number of characters required. ").
  __("Use %.0 to make a field optional e.g. %.0city .").
    "<p/><p>".
  __("You may have to tweak the style sheet ecampaign.css if you make significant changes.</p>"),
  '%to
  %subject
  %body
    <div id="text-guidance">'
  . __("Your name and address as entered below will be added. You do not need to add your name above. ")
  .  '</div>
  %name
  %zipcode
  %email
  %verificationCode
  %captcha
  %checkbox1
  %checkbox2
  %send
  <div id = "text-contact">'.
  __("%counter people have taken part in this action. ").
  __("Please contact %campaignEmail if you have any difficulties or queries. "). '</div>',
  $keys, 'no');

  ecampaign_add_option('ec_checkbox1',
    __("Prompt for %checkbox1 which can be included in the form above."),
    __('Check if you want to receive updates about this campaign.'), $keys, 'no');

  ecampaign_add_option('ec_checkbox2',
    __("Prompt for %checkbox2 which can be included in the form above."),
    __('Check if you want to receive alerts about related campaigns.'), $keys, 'no');

  ecampaign_add_option('ec_thankYouText',
   __("Text sent after successfully sending the email. The class and id attrubutes included
       in default values on this page are only used by the default style sheet ecampaign.css."),

  '<div id="text-sent-1" class="ecOk">'
  . __('Your email has been sent.')
  . '</div><div id="text-sent-2" class="ecOk">'
  . _('You should receive a copy in your mailbox. Thank you for taking part in this alert.</div>'), $keys, 'no');

  ecampaign_add_option('ec_friendsLayout',
  "<p>".
   __("Friends Form template. Add and remove fields that you want to collect. Valid fields are:").
      "%friendSubject %friendBody %friendEmail %friendSend. " .
   __("%verificationCode %captcha are not supported in this form. To prevent abuse of this
   form, %verificationCode or %captcha should be included in the Target Form template above. ").
   __("Adjust field length the same as for Form template above. ").
   __("You have to include the %friendSend button somewhere in the form.</p>"),
  '<h3 id="text-friends">' . __('Share with friends') . '</h3>
   %friendSubject
   %friendBody
   %friendEmail
   %friendSend',$keys, 'no');

  // transport used by PHPMailer to send mail
  ecampaign_add_option('ec_mailer',
  __("'Mailer' transport used by PHPmailer (mail, sendmail, smtp)"), 'mail', $keys, 'no');

  ecampaign_add_option('ec_checkdnsrr',
  __("Enable checking DNS records for the domain name when checking for a valid E-mail address. "), 'yes', $keys, 'no');

  $captchaPresent = file_exists(WP_PLUGIN_DIR . get_option('ec_captchadir') . '/securimage.php') ;

  ecampaign_add_option('ec_captchadir',
  __("Location of optional CAPTCHA library in plugins directory").
  " <a href='http://www.phpcaptcha.org/'>securimage</a>.  ".
  ($captchaPresent ? __("Library is present.") : __("Library is not present."))
  , '/ecampaign/securimage', $keys, 'no');

?>
<div class="wrap">
<h2>Ecampaign settings</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'ec-settings' ); ?> <!--  Output nonce, action, and option_page fields -->
    <table class="form-table" id="ec-settings">

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_testMode"] ?> </th>
        <td><input type="checkbox" name="ec_testMode" value='1' <?php  checked(get_option('ec_testMode'), 1); ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_campaignEmail"] ?> </th>
        <td><input type="text" name="ec_campaignEmail" size=35 value="<?php echo get_option('ec_campaignEmail'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_layout"] ?></th>
        </tr>
        <tr valign="top">
        <td>default:<br/><textarea name="ec_91" rows='20' cols='35' readonly='readonly'><?php echo  $default['ec_layout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_layout" rows='20' cols='35'><?php echo get_option('ec_layout'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_checkbox1"] ?> </th>
        <td><textarea name="ec_checkbox1" rows='4' cols='35'><?php echo get_option('ec_checkbox1'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_checkbox1"]  ?> </th>
        <td><textarea name="ec_checkbox2" rows='4' cols='35'><?php echo get_option('ec_checkbox2'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_thankYouText"] ?></th>
        </tr>
        <tr valign="top">
        <td>default:<br/><textarea name="ec_92" rows='6' cols='35' readonly='readonly'><?php echo  $default['ec_thankYouText']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_thankYouText" rows='6' cols='35'><?php echo get_option('ec_thankYouText');?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_friendsLayout"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_93" rows='8' cols='35' readonly='readonly'><?php echo $default['ec_friendsLayout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_friendsLayout" rows='8' cols='35'><?php echo get_option('ec_friendsLayout'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_mailer"] ?></th>
        <td><input type="text" name="ec_mailer" size=10 value="<?php echo get_option('ec_mailer'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_checkdnsrr"] ?> </th>
        <td><input type="checkbox" name="ec_checkdnsrr" value='1' <?php checked(get_option('ec_checkdnsrr'), 1); ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_captchadir"] ?></th>
        <td><input type="text" name="ec_captchadir" size=40 value="<?php echo get_option('ec_captchadir'); ?>" /></td>
        </tr>

    </table>

    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
</form>

    <?php
    if ($ecampaign == null)
      $ecampaign = new Ecampaign();
    echo "<hr/>";
    echo $ecampaign->help();             // print the help information
    ?>

</div>
<?php

}


?>
