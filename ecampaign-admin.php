<?php


/**
 * administrator settings which get put under (general) settings on the admin page
 * function is invoked when you enter wp-admin
 * @return nothing
 */


function ec_admin_menu()
{
  include_once dirname(__FILE__) . '/Ecampaign.class.php';  //
  add_options_page('Ecampaign options', 'Ecampaign', 'manage_options', 'ecampaign', 'ec_options');
  add_management_page('Ecampaign log', 'Ecampaign log', 'manage_options', 'ecampaign-log', 'ec_log');

  //call register settings function
  add_action( 'admin_init', 'ec_registerOptions' );
}


/**
 * Bind these options to settings page which will make
 * wordpress save them. Not declared, not saved!
 *
 * @return unknown_type
 */

function ec_registerOptions()
{
  // ecampaign_unset_options();
  register_setting( 'ec-settings', 'ec_testMode' );
  register_setting( 'ec-settings', 'ec_campaignEmail' );
  register_setting( 'ec-settings', 'ec_layout' );
  register_setting( 'ec-settings', 'ec_petitionLayout' );
  register_setting( 'ec-settings', 'ec_friendsLayout' );

  register_setting( 'ec-settings', 'ec_mailer' );
  register_setting( 'ec-settings', 'ec_checkdnsrr' );
  register_setting( 'ec-settings', 'ec_captchadir' );
}


function ec_uninstall()
{
  delete_option('ec_campaignEmail' );
  delete_option('ec_layout' );
  delete_option('ec_checkbox1' );
  delete_option('ec_checkbox2' );
  delete_option('ec_thankYouText' );
  delete_option('ec_petitionLayout' );
  delete_option('ec_friendsLayout' );
  delete_option('ec_testMode' );
  delete_option('ec_mailer' );
  delete_option('ec_checkdnsrr' );
  delete_option('ec_captchadir' );
  delete_option('ecampaign_log' );  // holds table version
  $log = new EcampaignLog;
  $log->uninstall();
}

/**
 * bug fix added in version 0.76
 * In version 0.75, a counter was added to wp_postmeta. However the counter was
 * not specified as being 'unique' and as a result one row was added to the
 * database everytime the counter was incremented.
 *
 * So recreate the counter for each post with unique to to true.
 */


function ec_activation()
{
  $log = new EcampaignLog;
  $log->install();

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


function ec_log()
{
  $log = new EcampaignLog();
  echo $log->view();
}


/**
 * add options but put prefix in front of all names.
 * To provide forward, compatability with old versions,
 * use values of old, unprefixed names if they exist
 */

function ec_add_option($name, $prompt, $defaultValue, &$keys, $autoload)
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
  $keys[0][$name] = $prompt;    // save prompts
  $keys[1][$name] = $defaultValue;  // and default values
}

static $ecampaign = null ;

function ec_options()
{
  $prompt = array(); $default = array();
  $keys =  array(&$prompt, &$default);
  $testMode = new EcampaignTestMode();   // to pick up available test modes
  if ($ecampaign == null)
    $ecampaign = new Ecampaign();

  ec_add_option('ec_testMode',
  __("Test Modes: Divert or suppress outgoing emails that would normally be sent to the
     target email address. Divert changes the <strong>To:</strong> field of the outgoing email'. ") .
  __("This feature prevents emails being sent to the target organisation accidentally.
     Emails normally sent to campaign mailbox and friends are still sent."), '', $keys, 'no');

  // copies of emails are sent here

  ec_add_option('ec_campaignEmail',
  __("Campaign mailbox address 'campaignEmail'; copies of activists emails are sent here. You can override it on
      individual email alerts."), 'campaign.email@not.set.up', $keys, 'no');

  // setting up the default form layout
  $fieldsHelp = $ecampaign->help('#fields');
  ec_add_option('ec_layout',

  __("<p>Target Form template. Add and remove fields that you want to collect. More detail on the $fieldsHelp.</p>"),
'{to}
{subject*}
{body*}
  <div class="text-guidance">'
. __("Your name and address as entered below will be added. You do not need to add your name above. ")
.  '</div>
{name*}
{email*}
{address1}
{state} {zipcode*}
{captcha}
{verificationCode}
{checkbox1 checked="checked" Check if you want to receive alerts about this campaign.}
{checkbox2   Check if you want to receive alerts about related campaigns.}
{send}
<div class="text-contact">'.
__("{counter} people have taken part in this action. ").
__("Please contact {campaignEmail} if you have any difficulties or queries. "). '</div>
{success <div class="ecOk bolder">'.__('Your email has been sent.').'</div>
 <div class="ecOk">'.__('You should receive a copy in your mailbox. Thank you for taking part in this action.').'</div>}',
  $keys, 'no');

  ec_add_option('ec_petitionLayout',

  __("<p>Petition Form template. Add and remove fields that you want to collect. More detail on the $fieldsHelp.</p>"),
'The petition:
{body*}
<div class="text-guidance">'. __("Please add your name and address.").  '</div>
{name*}
{email*}
{address1}
{state} {zipcode*}
{verificationCode}
{checkbox1 checked="checked" Check if you want to receive alerts about this campaign.}
{sign}
<div class="text-contact">'.
__("{counter} people have taken part in this action. ").
__("Please contact {campaignEmail} if you have any difficulties or queries. "). '</div>
{success <div class="ecOk bolder">'.__('Your name has been added to the petition.').'</div>
 <div class="ecOk">'.__('Thank you for taking part in this action.').'</div>}',
  $keys, 'no');

  ec_add_option('ec_friendsLayout',
  "<p>".__("Friend Form template. Add and remove fields that you want to collect. More detail on the $fieldsHelp.")."</p>",
  '<h4 id="text-friends">' . __('Share with friends') . '</h4>
{subject}
{body}
{friendEmail}
{friendSend}',$keys, 'no');

  // transport used by PHPMailer to send mail
  ec_add_option('ec_mailer',
  __("'Mailer' transport used by PHPmailer (mail, sendmail, smtp)"), 'mail', $keys, 'no');

  ec_add_option('ec_checkdnsrr',
  __("Enable checking DNS records for the domain name when checking for a valid E-mail address. "), 'yes', $keys, 'no');

  $captchaPresent = file_exists(WP_PLUGIN_DIR . get_option('ec_captchadir') . '/securimage.php') ;

  ec_add_option('ec_captchadir',
  __("Location of optional CAPTCHA library in plugins directory").
  " <a href='http://www.phpcaptcha.org/'>securimage</a>.  ".
  ($captchaPresent ? __("Library is present.") : __("Library is not present."))
  , '/ecampaign/securimage', $keys, 'no');

  ?>
<div class="wrap">
<h2>Ecampaign settings</h2>

<form method="post" action="options.php">
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    <?php settings_fields( 'ec-settings' ); ?> <!--  Output nonce, action, and option_page fields -->
    <table class="form-table" id="ec-settings">

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_testMode"] ?> </th>
        <td>
        <?php
        foreach ($testMode->ar as $val => $translation)
        {
          echo("<p><input type='radio' name='ec_testMode' value='$val' ");
          checked(get_option('ec_testMode'), $val); //  add checked='checked'
          echo(">$translation</>");
        }
        ?>
        </td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_campaignEmail"] ?> </th>
        <td><input type="text" name="ec_campaignEmail" size=35 value="<?php echo get_option('ec_campaignEmail'); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_layout"] ?></th>
        </tr>
        <tr valign="top">
        <td>default:<br/><textarea name="ec_91" rows='27' cols='35' readonly='readonly'><?php echo  $default['ec_layout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_layout" rows='27' cols='35'><?php echo get_option('ec_layout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_layout')) ?></td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_petitionLayout"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_93" rows='21' cols='35' readonly='readonly'><?php echo $default['ec_petitionLayout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_petitionLayout" rows='21' cols='35'><?php echo get_option('ec_petitionLayout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_petitionLayout')) ?> </td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_friendsLayout"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_93" rows='6' cols='35' readonly='readonly'><?php echo $default['ec_friendsLayout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_friendsLayout" rows='6' cols='35'><?php echo get_option('ec_friendsLayout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_friendsLayout')) ?> </td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_mailer"] ?></th>
        <td><input type="text" name="ec_mailer" size=10 value="<?php echo get_option('ec_mailer'); ?>" />
        </td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_checkdnsrr"] ?> </th>
        <td><input type="checkbox" name="ec_checkdnsrr" value=1 <?php checked(get_option('ec_checkdnsrr'), 1); ?> /></td>
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
    echo "<hr/>";
    echo $ecampaign->help('#admin');             // print the help information
    ?>

</div>
<?php
}

function _analyzeTemplate($template)
{
  $ecampaign = new Ecampaign;
  $fields = $ecampaign->parseTemplate($template, array());
  $num = count($fields);
  $text = "<br/>$num fields in template.";
  if ($num > 3)
    return "<span style='color : blue'>$text</span>" ;
  return "<span style='color : red'>$text. If you cannot see the error above cut and paste the
  fields from the left hand pane to the right hand pane. </span>" ;
}

