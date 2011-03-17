<?php
/*

class : Ecampaign
Author: John Ackers

This class is dynamically loaded by ecampaign.php to handle an ecampaign
tag embedded in wordpress post or page.

requires PHP version > 5.0

version: 0.6 2011-02-06 help added,
         0.7 2011-03-11 add flexible form layout


todo : sort out subject, check html is clean, especially subject, use textarea
// make tool to check out protection/injection, check wp_mail field protection.

*/



class Ecampaign
{
  function help()
  {
    return <<<EOT

<h3>About this plugin</h3>
<ul>

<li>This plugin sets up a pre-prepared email to the website visitor which s/he can personalize and submit.</li>

<h3>What happens</h3>

<h4>Phase 1 :  email to target</h4>

<li>This plugin presents a pre-prepared email to the site visitor.</li>

<li>The site visitor edits the prepared email and then presses send.</li>

<li>The plugin sends email from the (unverified) website visitor's email address with 'targetSubject'
to 'targetEmail' address(es) and sends a copy to visitor's own mailbox.  Multiple tergets should
be separated by commas. Then this plugin sends an email from the website visitor's email address to
the campaign mailbox.</li>

<li>The body of the preprepared email and the body of the email to send to friends are both enclosed between
[ecampaign] to [/ecampaign] and separated by a horizontal line  &lt;hr&gt;.</li>

</ul>

<h4>Phase 2 :  email to friends</h4>

<ul>
<li>If the send above was successful, this plugin unhides a second pre-prepared email to send to friends
to the site visitor.</li>

<li>The website visitor edits the prepared email.</li>

<li>This plugin sends the email from website visitor's email address to one or more friends (max 9).</li>
</ul>

<h3>Usage</h3>
<p>Here is an example of the text you should place on on a wordpress post or page:</p>

<div style='background-color:#DFD; padding:10px'>
<p>
[ecampaign  targetEmail='john.smith@gov.uk'
            targetSubject="Objection to Abc's Planning Application for an out of town supermarket"
            friendSubject="URGENT : Email alert about UK planning and impacts of supermarkets"]
</p>
<p>
Objection to ZZZ Planning Application for "a supermarket", XYZ Road
</p>
<p>
I wish to object to ... Best regards,
</p>
&lt;hr&gt;
<p>
I have just sent an email alert from xxx website to YYY Council to object to a planning
application by company ZZZ. Please support this urgent action. To find out more,
please go to this webpage below."
</p>
<p>[/ecampaign]</p>
</div>
<h3>Notes</h3>
<p>
The subject can include single quotes but not double quotes.
You can set the campaign email and other options in the admin pages.  You can override
the campaignEmail address on the particular action.
</p>

EOT;
  }


  /**
   * load the options for this plugin. This is the only shared code
   * between creating the page and handling the ajax posts
   *
   * @param unknown_type $atts  all the attribute that follow </campaign>
   * @param unknown_type $messageBody  the text between the <ecampaign> and </ecampaign>
   * @return unknown_type
   */

  private $options ;
  function __construct()
  {
    $this->options->campaignEmail          =  get_option('campaignEmail');
    $this->options->salutation             =  get_option('salutation');
    $this->options->formStyle              =  get_option('formStyle');
    $this->options->layout                 =  get_option('layout');
    $this->options->checkbox1Text          =  get_option('checkbox1');
    $this->options->checkbox2Text          =  get_option('checkbox2');
    $this->options->thankYouText           =  get_option('thankYouText');
    $this->options->inviteFriendsText      =  get_option('inviteFriendsText');
    $this->options->mailer                 =  get_option('mailer');
    $this->options->testMode               =  get_option('testMode');
    $this->options->checkdnsrr             =  get_option('checkdnsrr');
  }

  /**
   * Generate html for the page
   * @return html string
   */

  function createPage($pageAttributesArray, $messageBody)
  {
    $page = (object) (Ecampaign::shortcode_atts_case_sensitive(array(
    'targetEmail'      => '',
    'targetSubject'     => '',
    'friendSubject'  => '',
    'friendBody'  => '',
    'campaignEmail'  =>  $this->options->campaignEmail
    ), $pageAttributesArray));

    $html = "" ;

    if (empty($page->targetEmail))
      $html .= "'targetEmail' not set. " ;

    if (empty($page->targetSubject))
      $html .= "'targetSubject' not set. " ;

    if (empty($page->friendSubject))
      $html .=  "'friendSubject' not set. " ;

    if (empty($messageBody))
      $html .= "there is no text between [ecampaign] and [/ecampaign]. " ;

    $messageBodyParts = preg_split("$<hr[^/]*/>$", $messageBody);
    if (count($messageBodyParts) < 2)
    {
      $this->html .= "cannot find a &lt;hr/&gt; between the introduction of the action and the suggested message text.<br/>" ;
    }
    else
    {
      $page->targetBody = trim(preg_replace("$^</[pP]>$", "\n", $messageBodyParts[0]));
      $page->friendBody = trim(preg_replace("$^</[pP]>$", "\n", $messageBodyParts[1]));
    }

    if (!empty($html))
      die("ecampaign not setup properly:<p>$html</p><p>{$this->help()}</p>");

    $options = $this->options ;  // er just being lazy

    $campaignEmailBrokenUp = Ecampaign::breakupEmail($page->campaignEmail);
    $targetEmailBrokenUp = Ecampaign::breakupEmail($page->targetEmail);

    $matches = array();  preg_match_all('$%[\w]*$', $options->layout, $matches);
    $layout = $options->layout;

    foreach($matches[0] as $match)
    {
      $replace = null ;
      switch($match) {

        case '%to' :
          $recipientsBrokenUp = $options->testMode ? $campaignEmailBrokenUp : $targetEmailBrokenUp;
          $settingsUrl =  admin_url("options-general.php?page=ecampaign");
          $helpOutOfTestMode =  $options->testMode ?  "<span class='smaller'>[in test mode <a href='{$settingsUrl}')>change</a>]</span>" : "" ;
          $replace = "<p>to: $recipientsBrokenUp $helpOutOfTestMode</p>" ;
          break ;

        case '%subject' :
          $replace = "<p><input name='subject' size='65' value=" . '"'. $page->targetSubject . '"' . " </p>";
          break ;

        case '%body' :
          $replace = "<p><textarea name='body' rows='15'>{$this->replaceParagraphTagsWithNewlines($page->targetBody)}</textarea><p>";
          break ;

        case '%name' :
          $replace = Ecampaign::renderField('visitorName', 'Name', 'mandatory');
          break ;

        case '%address1' :
          $replace = Ecampaign::renderField('address1', 'Address 1', 'mandatory');
          break ;

        case '%address2' :
          $replace = Ecampaign::renderField('address2', 'Address 2', 'mandatory');
          break ;

        case '%postcode' :
          $replace = Ecampaign::renderField('postcode', 'Postcode',  'mandatory');
          break ;

        case '%country' :
          $replace = Ecampaign::renderField('country', 'Country',    'mandatory');
          break ;

        case '%email' :
          $replace = Ecampaign::renderField('visitorEmail', 'Email', 'mandatory validateEmail');
          break ;

        case '%checkbox1' :
          if (empty($options->checkbox1Text))
            continue ;
         $replace = Ecampaign::renderCheckBox('checkbox1', $options->checkbox1Text, '');
          break ;

        case '%checkbox2' :
          if (empty($options->checkbox2Text))
            continue ;
         $replace = Ecampaign::renderCheckBox('checkbox2', $options->checkbox2Text, '');
         break ;

        case '%campaignEmail' :
          $replace = $campaignEmailBrokenUp ;
          break ;

        case '%send' :
          $nonce = wp_create_nonce('ecampaign');
          $campaignEmailHidden =  Ecampaign::hideEmail($page->campaignEmail);  // hide @
          $recipientsHidden = Ecampaign::hideEmail($options->testMode ? $options->campaignEmail : $page->targetEmail); // hide @
          $replace = "
        <input type='hidden' name='targetEmail'    value='{$recipientsHidden}' />
        <input type='hidden' name='campaignEmail'  value='{$campaignEmailHidden}' />
        <input type='hidden' name='_ajax_nonce'  value={$nonce} />
        <input type='hidden' name='action'  value='ecampaign_post'/>
        <input type='hidden' name='ecampaign_action'  value='sendToTarget'/>

        <div class='clear fixedLong'><input type='button' name='send-to-target' value='Send email'
        onclick='return ecam.onClickSubmit(this, ecam.targetCallBack);'/></div>
        <div id='status' class='float'></div>" ;

          break ;

      }
      if (isset($replace))
      $layout = preg_replace("/$match/", $replace, $layout, 1);
    }

    $html = "
    <!-- ecampaign plugin for wordpress John Ackers  -->

    <div class='clear ajaxform' id='ecampaign-action' >
    $layout
    </div>
    <div>&nbsp;</div>" ; // create some space between the forms;

    $scriptUri = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    $friendBody = Ecampaign::replaceParagraphTagsWithNewLines($page->friendBody) . "\n\n" . $scriptUri ;

    $html .= "

    <div class='clear hidden ajaxform' id='ecampaign-friends' >

      {$options->inviteFriendsText}

      <p><input name='friendSubject' length='160' size='65' value=" . '"'.$page->friendSubject.'"'." </p>
      <p><textarea name='friendBody' rows='10'>{$friendBody}</textarea></p>

      <div id='friendsList'>
        <div class='first'>
          <div class='clear fixedLong'>Friend's email address</div><input class='float validateEmail'  type='text' size='42' name='emailfriend1'/>
        </div>
      </div>

      <div class='clear fixedLong'>&nbsp;</div><a class='float smaller' href='#' onclick='return ecam.addFriend()'>add another</a>

      <input type='hidden' name='_ajax_nonce'  value={$nonce} />
      <input type='hidden' name='campaignEmail'  value='{$campaignEmailHidden}' />
      <input type='hidden' name='action'  value='ecampaign_post'/>
      <input type='hidden' name='ecampaign_action'  value='sendToFriend'/>

      <div class='clear fixedLong'>
        <input type='button' name='send-to-friends'  value='Send email to friends'
        onclick='return ecam.onClickSubmit(this, ecam.friendsCallBack);' />
      </div>
      <div class='float' id='status'></div>
      <div class='clear'></div>

    </div>";
    return $html ;
  }

  /**
   * entry point from ecampaign_post
   * convert exceptions thrown by handlers into
   * structured responses encoded as json.
   *
   * @return does not
   */

  function ajaxPost()
  {
    try {
      check_ajax_referer("ecampaign");

      $action = $_POST['ecampaign_action'];

      if (!in_array($action,array("sendToTarget", "sendToFriend")))
        throw new Exception("ecampaign_action set to $action which is not valid.");

      $result = $this->$action() ;
      // convert info text into response block
      $response = is_array($result) ? $result : array("success"=>true, "msg" => $result);
    }
    catch (Exception $e)
    {
      $response = array("success"=>false, "msg" => "ecampaign: {$e->getMessage()}") ;
    }
    header( "Content-Type: application/json" );
    echo json_encode($response);
    exit ;
  }


  /*
   * email the original or updated message to the party or parties that are the target of
   * this campaign.
   * can throw exception containing text error message
   */

  function sendToTarget()
  {
    $field = null ;
    Ecampaign::fetchEscapedPostedFields($field,array('subject', 'body', 'address1', 'address2', 'postcode', 'country', 'checkbox1', 'checkbox2'));

    Ecampaign::fetchCheckedPostedFields($field,array('visitorName', 'visitorEmail', 'targetEmail', 'campaignEmail'));

    $field->campaignEmail = Ecampaign::hideEmail($field->campaignEmail) ;  // restore @
    $recipientString =      Ecampaign::hideEmail($field->targetEmail) ;  // restore @
    $recipients = explode(',', $recipientString );

    Ecampaign::validateEmail($field->visitorEmail, $this->options->checkdnsrr); // throws exception if fails.

    $mailer = Ecampaign::getPhpMailer($this->options);
    $mailer->Subject = $field->subject;
    $mailer->From = $field->visitorEmail;
    $mailer->FromName = $field->visitorName;
    $mailer->AddBCC($field->visitorEmail);       // copy of email for site visitor

    foreach ($recipients as $recipient)
    {
      $recipient =  trim($recipient);
      Ecampaign::validateEmail($recipient, $this->options->checkdnsrr);
      $mailer->AddAddress($recipient);
    }
    if ($this->options->bccCampaignEmail = false)
      $mailer->AddBCC($field->campaignEmail);    // this isn't really necessary

    $msgParts = array(html_entity_decode($field->body, ENT_QUOTES),
    $field->visitorName,
    $field->address1,$field->address2,$field->postcode,$field->country,
    $field->references);

    $mailer->Body = Ecampaign::assemblePlainMsg(array(html_entity_decode($field->body, ENT_QUOTES),
      $field->visitorName,
      $field->address1,$field->address2,$field->postcode,$field->country,
      $field->references));
    return $this->options->thankYouText ;
    $success = $mailer->Send();

    if (!$success)
    {
      throw new Exception("unable to send email to {$recipientString}, {$mailer->ErrorInfo}");
    }
    // forward a similar version of the email to the campaign but add the checkfields that they have clicked

    $mailer->ClearAllRecipients();
    $mailer->AddAddress($field->campaignEmail);
    $mailer->Subject = ($ch1 = $field->checkbox1 ?  "yes" : "no " ) . " : "
                     . ($ch2 = $field->checkbox2 ?  "yes" : "no " ) . " : "
                     . $field->subject ;

    $options = $this->options ;
    $mailer->Body = Ecampaign::assemblePlainMsg(array(
                       "to:         $recipientString",
                       "from:       $field->visitorEmail $field->visitorName",
                       "referer:    ${$_SERVER['HTTP_REFERER']}",
                       "remote:     ${$_SERVER['REMOTE_HOST']} {$_SERVER['REMOTE_ADDR']}",
                       "checkbox1:  $ch1  $options->checkbox1Text",
                       "checkbox2:  $ch2  $options->checkbox2Text",
                       " ",
                       $mailer->Body));

    $success = $mailer->Send();

    if (!$success)
      throw new Exception("unable to cc email to {$field->campaignEmail}, {$mailer->ErrorInfo}");

    return $this->options->thankYouText ;
  }

  /**
   * email activists friends
   * can throw exception containing text error message
   * @return html string
   */

  function sendToFriend()
  {
    $field = null ;
    Ecampaign::fetchEscapedPostedFields($field, array('friendSubject', 'friendBody'));

    Ecampaign::fetchCheckedPostedFields($field, array('visitorName', 'visitorEmail', 'campaignEmail'));

    $field->campaignEmail = Ecampaign::hideEmail($field->campaignEmail) ;  // restore @

    $mailer = Ecampaign::getPhpMailer($this->options);
    $mailer->From = $field->visitorEmail;   $mailer->FromName = $field->visitorName;
    $mailer->AddBCC($field->campaignEmail);
    $mailer->Subject = $field->friendSubject;
    $mailer->Body = $field->friendBody;

    // check all the email addresses before trying to send any messages
    for ($i = 1 ; $i <= 10 ; $i++)
    {
      $friendEmail = $_POST['emailfriend'.$i];
      if (!empty($friendEmail))
      {
        Ecampaign::validateEmail($friendEmail, $this->options->checkdnsrr);
      }
    }

    $numSuccess = 0 ;
    for ($i = 1 ; $i <= 10 ; $i++)
    {
      $friendEmail = $_POST['emailfriend'.$i];
      if (!empty($friendEmail))
      {
        $mailer->ClearAddresses();
        $mailer->AddAddress($friendEmail);
        $success = $mailer->Send();

        if (!$success)
          throw new Exception("unable to send email to {$friendEmail}, {$mailer->ErrorInfo}");
        $numSuccess++ ;
      }
    }
    return "Your email has been sent to $numSuccess friend(s). Thank you. ";
  }

  static function renderField($name, $label, $class)
  {
    $replace = "<p><label class='clear fixedShort' for='$name' >$label</label><input type='text' name='$name' id='$name' class='$class field'/></p>";
    return $replace ;
  }

  static function renderCheckBox($name, $label, $class)
  {
    $replace = "<p><input type='checkbox' name='$name' id='$name' class='$class'/><label for='$name'>$label</label></p>";
    return $replace ;
  }

  /**
   * float the label over the text field
   */
  static function renderFieldNotUsed($name, $label, $class)
  {
    $replace = "<p><input type='$type' name='$name' class='clear field $class' onkeydown='ecam.removeLabel(this);' />
    <label class='label' for='$name'>$label</label></p>";

    return $replace ;
  }

//  <label for="send-formName" class="labeloverlay sendoverlay labeloverlayhidden" >Name</label>

  /**
   * Convert </p> tags into CR-LF and strip out
   * any remaining html tags from the message body
   * @param $html
   * @return $text with \n added
   */

  static function replaceParagraphTagsWithNewlines($html)
  {
    $html = preg_replace("$</[pP]>$", "\n", $html);
    return strip_tags($html);
  }

  /**
   * check data entered in the textareas and remove slashes
   * @param unknown_type $fields
   * @return unknown_type
   */

  static function fetchEscapedPostedFields(&$target,$fields)
  {
    foreach($fields as $fieldName)
    {
      $target->$fieldName = $_POST[$fieldName];
      if (get_magic_quotes_gpc())
      {
        $target->$fieldName = stripslashes($target->$fieldName);
      }
    }
  }

  /**
   * check data entered in the single line fields
   * @param unknown_type $fields
   * @return unknown_type
   */
  static function fetchCheckedPostedFields(&$target, $fields)
  {
    foreach($fields as $fieldName)
    {
      $target->$fieldName = $_POST[$fieldName];
      Ecampaign::assertNoControlChars($target->$fieldName, $fieldName);
    }
  }


  /**
   * check for field injection in headers.  Think that any injection has
   * to be terminated with a \n character for it to influence the email header.
   *
   * @returns the field so functions can be used inline
   */

  static function assertNoControlChars($field, $fieldName="")
  {
    if (strlen($field) < 2)
      throw new Exception("Too few characters in $fieldName: $field");
    if (preg_match("$[\\r\\n\\t]$", $field))
      throw new Exception("Invalid character in $fieldName: $field");
    return ($field);
  }

  // modified version of wordpress function that allows the
  // code to be case sensitive and user use any case

  static function shortcode_atts_case_sensitive($pairs, $atts) {
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


  /**
   * break up string of emails separated by commas
   * add span blocks to breaup email string
   *
   * @param $emailsBetweenCommas
   * @return string containing emails separated by comma and a space.
   */
  static function breakupEmail($emailsBetweenCommas)
  {
    $html = "" ;
    $emails = preg_split("/,/", $emailsBetweenCommas);
    foreach($emails as $email)
    {
      if (!empty($html))
        $html .= ", " ;  // separate emails with a comma
      $html .= str_replace("@", "<span class='confuseSpammers'>@</span>", $email);
    }
    return $html;
  }

  /**
   * hide/unhide email address
   * Get rid of the @ from email addresses embedded in the html
   * If that doesn't work put the @ back in the email address.
   * @param unknown_type $email
   * @return hidden or unhidden email
   */

  static function hideEmail($email)
  {
    $count = 0 ;
    $email = str_replace('@','ZyZ',$email, $count);
    if ($count == 0)
      $email = str_replace('ZyZ','@', $email);
    return $email ;
  }

  /**
   * return address of PHPMailer and sets mailer transport
   *
   * @return unknown_type
   */

  static function getPhpMailer($options)
  {
    require_once ABSPATH . WPINC . '/class-phpmailer.php';
    require_once ABSPATH . WPINC . '/class-smtp.php';
    $phpmailer = new PHPMailer();
    $phpmailer->Mailer = $options->mailer ;
    $phpmailer->CharSet = apply_filters( 'wp_mail_charset', get_bloginfo( 'charset' ));
    return $phpmailer ;
  }


/**
 * create message body text from array of line(s) or
 * groups of lines and separate with \n
 *
 * @return message text
 */

  static function assemblePlainMsg($parts)
  {
    $plainBody = "" ;
    foreach($parts as $part)
    {
      if (!empty($part))
        $plainBody .=  $part . "\n";
    }
    return $plainBody ;
  }


/**
 *
 * @param $email      address to be validated
 * @param $checkDns
 * @return true if possibly good email address
 *
 * This function has been borrowed from si-contact_form
 */


  static function validateEmail($recipient, $checkDns)
  {
    //check for all the non-printable codes in the standard ASCII set,
    //including null bytes and newlines, and return false immediately if any are found.
    if (preg_match("/[\\000-\\037]/",$email)) {
      throw new Exception("invalid email address, control characters present : {$recipient}");
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL))
      throw new Exception("invalid email address : {$recipient}");

    // Make sure the domain exists with a DNS check (if enabled in options)
    // MX records are not mandatory for email delivery, this is why this function also checks A and CNAME records.
    // if the checkdnsrr function does not exist (skip this extra check, the syntax check will have to do)
    // checkdnsrr available in Linux: PHP 4.3.0 and higher & Windows: PHP 5.3.0 and higher
    if ($checkDns == true) {
      if( function_exists('checkdnsrr') ) {
        list($user,$domain) = explode('@',$recipient);
        if(!checkdnsrr($domain.'.', 'MX') &&
        !checkdnsrr($domain.'.', 'A') &&
        !checkdnsrr($domain.'.', 'CNAME')) {
          // domain not found in DNS
          throw new Exception("unknown domain : {$recipient}");
        }
      }
    }
    return true;
  }
}

