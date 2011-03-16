<?php
/*
 Plugin Name: Ecampaign
 Plugin URI: http://wordpress.org/extend/plugins/ecampaign/
 Description: Allows a simple email based campaign action to be embedded into any wordpress page or post.
 Version: 0.7
 Author: John Ackers
 Author URI:
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
}


add_action('init', 'ecampaign_load', 1);


add_shortcode('ecampaign', 'ecampaign_short_code');


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
  add_option('campaignEmail', '', null, true);

  // setting up the default form layout
  add_option('layout', '
  %to
  %subject
  %body
  Your name and address as entered below will be added. Do not sign your name above.

  All fields are needed.
  %name
  %postcode
  %email
  %checkbox1
  %checkbox2
  %send
  <div class="clear advisory">Please contact %campaignEmail if you have any difficulties or queries</div>
  ', null, true);

  add_option('checkbox1', 'Check if you want to receive updates about this campaign.', null, true);

  add_option('checkbox2', 'Check if you want to receive alerts about related campaigns.', null, true);

  add_option('thankYouText',  '<p style="font-weight:bold;">Your email has been sent.</p>
  <p>You should receive a copy in your mailbox. Thank you for taking part in this alert.</p>', null, true);

  add_option('inviteFriendsText',
  '<h2>Tell your friends</h2>
  <p>You can also send an email to friends asking them to take part
  in the email alert. See below for the text that will be sent.</p>', null, true);

  // transport used by PHPMailer to send mail
  add_option('mailer', 'mail', null, true);

  add_option('checkdnsrr', '', null, true);


?>
<div class="wrap">
<h2>Ecampaign settings</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'ecampaign-settings-group' ); ?>
    <table class="form-table">

        <tr valign="top">
        <th scope="row">Test Mode: Changes the <strong>To:</strong> field of the outgoing email
        from 'targetEmail' to 'campaignEmail'.
        This prevents emails being sent to the target organisation accidentally. </th>
        <td><input type="checkbox" name="testMode" <?php if (strlen(get_option('testMode')) > 0 ) echo " checked=true ";  ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Campaign mailbox address 'campaignEmail'; copies of activists emails are sent here.
        You can override it on individual email alerts.</th>
        <td><input type="text" name="campaignEmail" size=50 value="<?php echo get_option('campaignEmail'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Form layout. Add and remove fields that you want to collect.
        Valid tags are %to %subject %body %name %address1 %address2 %postcode %country %email %send %checkbox1 %checkbox2.
        You have to include the %send button somewhere in the form.</th>
        <td><textarea name="layout" rows='10' cols='30'><?php echo get_option('layout'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">Text for %checkbox1 which can be included in the form above.</th>
        <td><textarea name="checkbox1" rows='4' cols='50'><?php echo get_option('checkbox1'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">Text for %checkbox2 which can be included in the form above.</th>
        <td><textarea name="checkbox2" rows='4' cols='50'><?php echo get_option('checkbox2'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">Text sent after successfully sending the email</th>
        <td><textarea name="thankYouText" rows='4' cols='50'><?php echo get_option('thankYouText');?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">Text to prompt the visitor to invite friends</th>
        <td><textarea name="inviteFriendsText" rows='4' cols='50'><?php echo get_option('inviteFriendsText'); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">'Mailer' transport used by PHPmailer (mail, sendmail, smtp)</th>
        <td><input type="text" name="mailer" size=10 value="<?php echo get_option('mailer'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Enable checking DNS records for the domain name when checking for a valid E-mail address.</th>
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