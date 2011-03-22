<?php
/*
 Plugin Name: Ecampaign
 Plugin URI: http://wordpress.org/extend/plugins/ecampaign/
 Description: Allows a simple email based campaign action to be embedded into any wordpress page or post.
 Version: 0.73
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

function ecampaign_post() {
  include_once dirname(__FILE__) . '/ecampaign.class.php';  // load only for posts
  if ($ecampaign == null)
    $ecampaign = new Ecampaign();
  return $ecampaign->ajaxPost();
}


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


function ecampaign_unset_options()
{
  delete_option('campaignEmail' );
  delete_option('layout' );
  delete_option('checkbox1' );
  delete_option('checkbox2' );
  delete_option('thankYouText' );
  delete_option('inviteSubscriptionText' );
  delete_option('inviteFriendsText' );
  delete_option('testMode' );
  delete_option('mailer' );
  delete_option('checkdnsrr' );

}
register_uninstall_hook(__FILE__, 'ecampaign_unset_options');


if (is_admin())
{
  add_action('wp_ajax_ecampaign_post', 'ecampaign_post');

  add_action('admin_menu', 'ecampaign_menu');
  add_action('wp_ajax_nopriv_ecampaign_post', 'ecampaign_post');
}
else
{

}


/**
 * administrator settings which get put under (general) settings on the admin page
 * @return nothing
 */

function ecampaign_menu() {

  include_once dirname(__FILE__) . '/ecampaign.class.php';  // load only for pages using shortcode
  add_options_page('Ecampaign Options', 'Ecampaign', 'manage_options', 'ecampaign', 'ecampaign_options');

  //call register settings function
  add_action( 'admin_init', 'ecampaign_registerOptions' );
}



// not sure how to make the methods below load dynamically


function ecampaign_registerOptions() {
  //register our settings
  register_setting( 'ecampaign-settings-group', 'campaignEmail' );
  register_setting( 'ecampaign-settings-group', 'layout' );
  register_setting( 'ecampaign-settings-group', 'checkbox1' );
  register_setting( 'ecampaign-settings-group', 'checkbox2' );
  register_setting( 'ecampaign-settings-group', 'thankYouText' );
  register_setting( 'ecampaign-settings-group', 'inviteSubscriptionText' );
  register_setting( 'ecampaign-settings-group', 'inviteFriendsText' );
  register_setting( 'ecampaign-settings-group', 'testMode' );
  register_setting( 'ecampaign-settings-group', 'mailer' );
  register_setting( 'ecampaign-settings-group', 'checkdnsrr' );
}



function ecampaign_options() {

  // copies of emails are sent here
  add_option('campaignEmail', '', null, 'no');

  // setting up the default form layout
  add_option('layout', '
  %to
  %subject
  %body'
  . __("Your name and address as entered below will be added. Do not sign your name above.")
  . __("All fields are needed.") .
 '%name
  %zipcode
  %email
  %checkbox1
  %checkbox2
  %send
  <div class="clear advisory">'
  . __("Please contact %campaignEmail if you have any difficulties or queries")
  . '</div>', null, true);

  add_option('checkbox1', __('Check if you want to receive updates about this campaign.'), null, 'no');

  add_option('checkbox2', __('Check if you want to receive alerts about related campaigns.'), null, 'no');

  add_option('thankYouText',
  '<p style="font-weight:bold;">'
  . __('Your email has been sent.')
  . '</p><p>'
  . _('You should receive a copy in your mailbox. Thank you for taking part in this alert.</p>'), null, 'no');

  add_option('inviteFriendsText',
  '<h2>' . __('Tell your friends') . '</h2><p>' .
  __('You can also send an email to friends asking them to take part in the email alert.
  See below for the text that will be sent.') . '</p>', null, 'no');

  // transport used by PHPMailer to send mail
  add_option('mailer', 'mail', null, 'no');

  add_option('checkdnsrr', '', null, 'no');


?>
<div class="wrap">
<h2>Ecampaign settings</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'ecampaign-settings-group' ); ?> <!--  Output nonce, action, and option_page fields -->
    <table class="form-table">

        <tr valign="top">
        <th scope="row"><?php echo(__("Test Mode: Changes the <strong>To:</strong> field of the outgoing email
        from 'targetEmail' to 'campaignEmail'") .
        __("This feature prevents emails being sent to the target organisation accidentally.")) ?> </th>
        <td><input type="checkbox" name="testMode" <?php if (strlen(get_option('testMode')) > 0 ) echo " checked=true ";  ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("Campaign mailbox address 'campaignEmail'; copies of activists emails are sent here.
        You can override it on individual email alerts.")  ?> </th>
        <td><input type="text" name="campaignEmail" size=50 value="<?php echo get_option('campaignEmail'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo(__("Form layout. Add and remove fields that you want to collect. Valid fields are: ") .
        "%to %subject %body %name %address1 %address2 %city %postcode %zipcode %state %country " .
        "%email %send %checkbox1 %checkbox2. ") .
        __("You have to include the %send button somewhere in the form.") ; ?> </th>
        <td><textarea name="layout" rows='10' cols='30'><?php echo get_option('layout'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("Text for %checkbox1 which can be included in the form above.") ?> </th>
        <td><textarea name="checkbox1" rows='4' cols='50'><?php echo get_option('checkbox1'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("Text for %checkbox2 which can be included in the form above.")  ?> </th>
        <td><textarea name="checkbox2" rows='4' cols='50'><?php echo get_option('checkbox2'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("Text sent after successfully sending the email") ?> </th>
        <td><textarea name="thankYouText" rows='4' cols='50'><?php echo get_option('thankYouText');?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("Text to prompt the visitor to invite friends") ?> </th>
        <td><textarea name="inviteFriendsText" rows='4' cols='50'><?php echo get_option('inviteFriendsText'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e("'Mailer' transport used by PHPmailer (mail, sendmail, smtp)") ?> </th>
        <td><input type="text" name="mailer" size=10 value="<?php echo get_option('mailer'); ?>" /></td>
        </tr>


        <tr valign="top">
        <th scope="row"><?php _e("Enable checking DNS records for the domain name when checking for a valid E-mail address. ")  ?> </th>
        <td><input type="checkbox" name="checkdnsrr" <?php if (strlen(get_option('checkdnsrr')) > 0 ) echo " checked=true ";  ?>" /></td>
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
