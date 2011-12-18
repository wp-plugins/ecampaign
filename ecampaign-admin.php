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
  $fields = _getAdminFields();
  foreach($fields as $field)
    register_setting('ec-settings', $field[0]);
}

function ec_uninstall()
{
  $fields = _getAdminFields();
  foreach($fields as $field)
    delete_option('ec-settings', $field[0]);

  delete_option('ecampaign_log' );  // holds db table version
  $log = new EcampaignLog;
  $log->uninstall();
}

function ec_activation()
{
  $log = new EcampaignLog;
  $log->install();

  // remove formList from all posts
  // it will be recreated when page first accessed

  foreach(get_posts(array('meta_key' => 'formList')) as $post)
  {
    $val = get_post_meta($post->ID, 'formList', true);
    if ($val !== false)
      delete_post_meta($post->ID,  'formList');
  }
  return "" ;
}

function ec_log()
{
  $log = new EcampaignLog();
  echo $log->view();
}


static $adminFields = array();

function _getAdminFields()
{
  if (!empty($adminFields))
    return ($adminFields);

  $adminFields[] = array('ec_testMode',
  __("Test Modes: Divert or suppress outgoing emails that would normally be sent to the
     target email address. Divert changes the <strong>To:</strong> field of the outgoing email'. ") .
  __("This feature prevents emails being sent to the target organisation accidentally.
     Emails normally sent to campaign mailbox and friends are still sent."), '');

  // copies of emails are sent here

  $adminFields[] = array('ec_campaignEmail',
  __("Campaign mailbox address 'campaignEmail'; copies of activists emails are sent here. You can override it on
      individual email alerts."), 'campaign.email@not.set.up');

  // setting up the default form layout
  $fieldsHelp = Ecampaign::help('#fields');
  $adminFields[] = array('ec_layout',

  __("<p>Target Form template. Add and remove fields that you want to collect. $fieldsHelp.</p>"),
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
 <div class="ecOk">'.__('You should receive a copy in your mailbox. Thank you for taking part in this action.').'</div>}');

  $adminFields[] = array('ec_petitionLayout',

  __("<p>Petition Form template. Add and remove fields that you want to collect. $fieldsHelp.</p>"),
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
 <div class="ecOk">'.__('Thank you for taking part in this action.').'</div>}');

  $adminFields[] = array('ec_friendsLayout',
  "<p>".__("Friend Form template. Add and remove fields that you want to collect. $fieldsHelp.")."</p>",
  '<h4 id="text-friends">' . __('Share with friends') . '</h4>
{subject}
{body}
{friendEmail}
{friendSend}');

  $adminFields[] = array('ec_confirmationEmail',
  __("Text of email sent to site visitor to confirm name has been added to petition."),
  __("Thank you for signing the petition."));


  // transport used by PHPMailer to send mail
  $adminFields[] = array('ec_mailer',
  __("'Mailer' transport used by PHPmailer (mail, sendmail, smtp)"), 'mail');

  $adminFields[] = array('ec_checkdnsrr',
  __("Enable checking DNS records for the domain name when checking for a valid E-mail address. "), 'yes');

  $captchaPresent = file_exists(WP_PLUGIN_DIR . get_option('ec_captchadir') . '/securimage.php') ;

  $adminFields[] = array('ec_captchadir',
  __("Relative path of optional CAPTCHA library in plugins directory").
  " <a href='http://www.phpcaptcha.org/'>securimage</a>.  ".
  ($captchaPresent ? __("Library is present.") : __("Library is not present."))
  , '/ecampaign/securimage', '');

  $adminFields[] = array('ec_subscriptionClass',
  __("Subscribe site visitors who opt-in using a checkbox to external email list using this class e.g. EcampaignPHPList"),'');

  $adminFields[] = array('ec_subscriptionParams',
  __("Parameters passed to instance of class above e.g. for PHPList 'checkbox2=6 configFile=/home/web/phplist/lists/config/config.php' ") .
  Ecampaign::help('#subscription'),'');

  $adminFields[] = array('ec_thirdPartyKey', __("Optional third party API Key used to lookup elected representatives. ") .
  Ecampaign::help('#lookup'), '');

  return $adminFields;
}

static $ecampaign = null ;


function ec_options()
{
  $testMode = new EcampaignTestMode();   // to pick up available test modes
  if ($ecampaign == null)
    $ecampaign = new Ecampaign();

  $prompt = array(); $default = array();
  $adminFields = _getAdminFields();
  foreach($adminFields as $field)
  {
    list($name, $promptValue, $defaultValue) = $field ;
    add_option($name, $defaultValue, '', $autoload);
    $prompt[$name] = $promptValue;    // save prompts
    $default[$name] = $defaultValue;  // and default values
  }

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
        <td><input type="text" name="ec_campaignEmail" size='35' value="<?php echo get_option('ec_campaignEmail'); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_layout"] ?></th>
        </tr>
        <tr valign="top">
        <td>default:<br/><textarea name="ec_91" rows='27' cols='40' readonly='readonly'><?php echo  $default['ec_layout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_layout" rows='27' cols='40'><?php echo get_option('ec_layout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_layout')) ?></td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_petitionLayout"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_93" rows='21' cols='40' readonly='readonly'><?php echo $default['ec_petitionLayout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_petitionLayout" rows='21' cols='40'><?php echo get_option('ec_petitionLayout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_petitionLayout')) ?> </td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_friendsLayout"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_95" rows='6' cols='40' readonly='readonly'><?php echo $default['ec_friendsLayout']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_friendsLayout" rows='6' cols='40'><?php echo get_option('ec_friendsLayout'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_friendsLayout')) ?> </td>
        </tr>

        <tr valign="top">
        <th scope="row" colspan="2"><?php echo $prompt["ec_confirmationEmail"]  ?> </th>
        </tr>
        <tr valign="top">

        <td>default:<br/><textarea name="ec_97" rows='6' cols='40' readonly='readonly'><?php echo $default['ec_confirmationEmail']; ?></textarea></td>
        <td>current:<br/><textarea name="ec_confirmationEmail" rows='6' cols='40'><?php echo get_option('ec_confirmationEmail'); ?></textarea>
        <?php echo _analyzeTemplate(get_option('ec_confirmationEmail'), 0) ?> </td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_mailer"] ?></th>
        <td><input type="text" name="ec_mailer" size='10' value="<?php echo get_option('ec_mailer'); ?>" />
        </td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_checkdnsrr"] ?> </th>
        <td><input type="checkbox" name="ec_checkdnsrr" value='1' <?php checked(get_option('ec_checkdnsrr'), 1); ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_captchadir"] ?></th>
        <td><input type="text" name="ec_captchadir" size='40' value="<?php echo get_option('ec_captchadir'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_subscriptionClass"] ?></th>
        <td><input type="text" name="ec_subscriptionClass" size='40' value="<?php echo get_option('ec_subscriptionClass'); ?>" />
        <?php
        $listClassPath = get_option('ec_subscriptionClass');
        if (!empty($listClassPath))
        {
          try {
            $list = _createFromClassPath($listClassPath);
          }
          catch (Exception $e)
          {
            echo "<br/><span style='color : red'>" . $e->getMessage() . "</span>" ;
          }
        }
        ?></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_subscriptionParams"] ?></th>
        <td><textarea rows=4 cols=40 name="ec_subscriptionParams"><?php echo get_option('ec_subscriptionParams'); ?></textarea>
        <?php if (!empty($list))
        {
          try {
            echo "<br/><span style='color : red'>" . $list->checkConfiguration() . "</span>" ;
          }
          catch (Exception $e)
          {
            echo "<br/><span style='color : red'>" . $e->getMessage() . "</span>" ;
          }
        }
        ?></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php echo $prompt["ec_thirdPartyKey"] ?></th>
        <td><input type="text" name="ec_thirdPartyKey" size='40' value="<?php echo get_option('ec_thirdPartyKey'); ?>" />
        </td>
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

function _analyzeTemplate($template, $minimum=3)
{
  $ecampaign = new Ecampaign;
  $ecampaign->initializeCannedFields();
  $fields = $ecampaign->parseTemplate($template, array());
  $numCustom = $numStandard = 0 ;
  foreach($fields as $f)
  {
    if ($f->isCustom)
      $numCustom++ ;
    else
      $numStandard++ ;
  }
  $text = "$numStandard preconfigured fields, $numCustom other fields in template.";
  if ($numStandard >= $minimum)
    return "<span style='color : blue'><br/>$text</span>" ;
  return "<span style='color : red'><br/>Warning: $text fields declared. At least $minimum standard fields expected. If you cannot see the
  error above cut and paste the fields from the left hand pane to the right hand pane. </span>" ;
}

