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
 * wordpress save them. Not declared, settings cannot saved!
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

  $adminFields[] = array('ec_preventDuplicateActions',
  __("Prevent site visitor from sending an email or signing a petition more than once.
  It is useful to disable when testing. "), 1);

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

  $tokenHelp = "Use %fieldname to include the value of a captured field. $fieldsHelp";

  $adminFields[] = array('ec_verificationEmailSubject',
  __("Subject of email sent to site visitor with verification code. $tokenHelp"),
  __("Verification code for ecampaign action."));


  $adminFields[] = array('ec_verificationEmailBody',
  __("Body of email sent to site visitor with verification code. You should include %code and can include %permalink
      (but the %permalink cannot be used to complete verification). $tokenHelp"),
  __("Dear %visitorName,

Thank you for verifying your email address. Please enter %code in the original web page in order to send your email.

You have received this email from this webpage

%subject at
%permalink

If you believe that you have received this email in error please contact %campaignEmail."));

  $adminFields[] = array('ec_confirmationEmailSubject',
  __("Subject of email sent to site visitor to confirm name has been added to petition. $tokenHelp"),
  __("Your name has been added to the petition"));


  $adminFields[] = array('ec_confirmationEmailBody',
  __("Text of email sent to site visitor to confirm name has been added to petition. You can include %permalink. $tokenHelp"),
  __("Dear %visitorName,

Thank you for signing the petition

%subject at
%permalink

If you believe that you have received this email in error please contact %campaignEmail."));


  // transport used by PHPMailer to send mail
  $adminFields[] = array('ec_mailer',
  __("'Mailer' transport used by PHPmailer (mail, sendmail, smtp)"), 'mail');

  $adminFields[] = array('ec_checkdnsrr',
  __("Enable checking DNS records for the domain name when checking for a valid E-mail address. "), 1);

  $captchaPresent = file_exists(WP_PLUGIN_DIR . get_option('ec_captchadir') . '/securimage.php') ;

  $adminFields[] = array('ec_captchadir',
  __("Relative path of optional CAPTCHA library in plugins directory").
  " <a href='http://www.phpcaptcha.org/'>securimage</a>.  ".
  ($captchaPresent ? __("Library is present.") : __("Library is not present."))
  , '/ecampaign/securimage', '');

  $adminFields[] = array('ec_subscriptionClass',
  __("Capture site visitors who opt-in by registering them as site users (EcampaignSubscribeUser),
  or adding them to an external email list using (EcampaignPHPList)"),'');

  $adminFields[] = array('ec_subscriptionParams',
  __("Parameters passed to instance of class above. ") .
  Ecampaign::help('#subscription'),'optin=checkbox1');

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
        <?php
        _renderCheckbox($prompt, $default, 'ec_preventDuplicateActions');

        _renderInputField($prompt, $default, 'ec_campaignEmail');

        _renderDualTextArea($prompt, $default, 'ec_layout', 26, 4);
        _renderDualTextArea($prompt, $default, 'ec_petitionLayout', 20, 4);
        _renderDualTextArea($prompt, $default, 'ec_friendsLayout', 6, 2);

        _renderDualTextArea($prompt, $default, 'ec_verificationEmailSubject', 1);
        _renderDualTextArea($prompt, $default, 'ec_verificationEmailBody', 6);

        _renderDualTextArea($prompt, $default, 'ec_confirmationEmailSubject', 1);
        _renderDualTextArea($prompt, $default, 'ec_confirmationEmailBody', 6);

        _renderInputField($prompt, $default, 'ec_mailer');
        _renderCheckbox($prompt, $default, 'ec_checkdnsrr');

        _renderInputField($prompt, $default, 'ec_captchadir');

        $listClassPath = get_option('ec_subscriptionClass');
        $msg1 ="" ;  $msg2  = ""  ;
        if (!empty($listClassPath))
        {
          try {
            $list = _createFromClassPath($listClassPath);
          }
          catch (Exception $e)
          {
            $msg1 = "<br/><span style='color : red'>" . $e->getMessage() . "</span>" ;
          }
        }

        if (!empty($list))
        {
          try {
            $msg2 = "<br/><span style='color : red'>" . $list->checkConfiguration() . "</span>" ;
          }
          catch (Exception $e)
          {
            $msg2 = "<br/><span style='color : red'>" . $e->getMessage() . "</span>" ;
          }
        }
        _renderInputField($prompt, $default, 'ec_subscriptionClass', $msg1);
        _renderDualTextArea($prompt, $default, 'ec_subscriptionParams', 0, $msg2) ;
        _renderInputField($prompt, $default, 'ec_thirdPartyKey') ?>

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


function _renderInputField($prompt, $default, $tname, $msg = "")
{
  ?>
  <tr valign="top">
  <th scope="row"><?php echo $prompt[$tname] ?> </th>
  <td><input type="text" name="<?php echo $tname?>" size='35' value="<?php echo get_option($tname,$default[$tname]); ?>" />
  <?php echo $msg ?></td>
  </tr>
  <?php
}

function _renderCheckbox($prompt, $default, $tname)
{
  ?>
  <tr valign="top">
  <th scope="row"><?php echo $prompt[$tname] ?> </th>
  <td><input type="checkbox" name="<?php echo $tname?>" value="1" <?php checked(get_option($tname,$default[$tname]), 1); ?> /></td>
  </tr>
  <?php
}

function _renderDualTextArea($prompt, $default, $tname, $rows, $other="")
{
  ?>
  <tr valign="top">
  <th scope="row" colspan="2"><?php echo $prompt[$tname] ?> </th>
  </tr>

  <tr valign="top">
  <td>default:<br/><textarea name="<?php echo $tname?>-d" rows=<?php echo "'$rows'" ?> cols='40' readonly='readonly'><?php echo  $default[$tname]; ?></textarea></td>
  <td>current:<br/><textarea name="<?php echo $tname?>" rows=<?php echo "'$rows'" ?> cols='40'><?php echo get_option($tname, $default[$tname]); ?></textarea>
  <?php echo is_numeric($other) ? _analyzeTemplate(get_option($tname), $other) : $other ?></td>
  </tr>
  <?php
}


function _analyzeTemplate($template, $minimumFields)
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

