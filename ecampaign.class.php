<?php
/*

class : Ecampaign
Author: John Ackers

This class is dynamically loaded by ecampaign.php to handle an ecampaign
tag embedded in wordpress post or page.

requires PHP version > 5.0

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
            friendSubject="Please email the council about Abc's plans to build on Midtown road!"]
</p>
<p>
Objection to ZZZ Planning Application for "a supermarket", XYZ Road
</p>
<p>
    [Please customise this message and delete this text]
</p>
<p>
I wish to object to ... Best regards,
</p>
&lt;hr&gt;
<p>
I have just sent an email alert to xxx Council to object to a planning
application by company Abc. Please support this urgent action.
To find out more, click on the link below.
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

  private $options, $targetFields, $friendsFields ;

  /**
   * load the options for this plugin. This is the only shared code
   * between creating the page and handling the ajax posts
   *
   * @param unknown_type $atts  all the attribute that follow </campaign>
   * @param unknown_type $messageBody  the text between the <ecampaign> and </ecampaign>
   * @return unknown_type
   */


  function __construct()
  {
    $this->options->campaignEmail          =  get_option('ec_campaignEmail');
    $this->options->formStyle              =  get_option('ec_formStyle');
    $this->options->targetLayout           =  get_option('ec_layout');
    $this->options->checkbox1Text          =  get_option('ec_checkbox1');
    $this->options->checkbox2Text          =  get_option('ec_checkbox2');
    $this->options->thankYouText           =  get_option('ec_thankYouText');
    $this->options->friendsLayout          =  get_option('ec_friendsLayout');
    $this->options->mailer                 =  get_option('ec_mailer');
    $this->options->testMode               =  get_option('ec_testMode');
    $this->options->checkdnsrr             =  get_option('ec_checkdnsrr');

    $this->targetFields = Ecampaign::parseLayout($this->options->targetLayout);
    $this->friendsFields = Ecampaign::parseLayout($this->options->friendsLayout);
  }

  /**
   * parse the layout string and create a list of working fields and
   * work out max and min length of fields
   *
   * @return array of fields
   */

  static function parseLayout($layout)
  {
    $knownFields = array(

      'subject'      => array(null,           55, 4),    // cols, minimum cols
      'body'         => array(null,           55,10),    // cols, rows
      'visitorName'  => array(__('Name'),     15, 4),
      'visitorEmail' => array(__('Email'),    25, 4, 'validateEmail'),
      'address1'     => array(__('Address 1'),15, 4),    // default width and minimum number of chars
      'address2'     => array(__('Address 2'),15, 0),
      'address3'     => array(__('Address 3'),15, 0),
      'city'         => array(__('City'),     15, 4),
      'postcode'     => array(__('Postcode'), 10, 4),
      'ukpostcode'   => array(__('Postcode'), 10, 4, 'validatePostcode'),
      'zipcode'      => array(__('Zipcode'),  10, 5, 'validateZipcode'),
      'state'        => array(__('State'),    2,  2),	 // tx, ca
      'country'      => array(__('Country'),  15, 2),	 // us, uk
      'send'         => array(__('Send'),     0,  0),

      'friendSubject'=> array(null,           60,20),    // cols, minimum cols
      'friendBody'   => array(null,                         65, 8),    // cols, rows
      'friendEmail'  => array(__("Friend's email address") ,25, 4),
      'friendSend'   => array(__('Send to friends'),        0,  0),
    );

    $allMatchResults = array();
    preg_match_all('$%([0-9]{0,2})[\.]?([0-9]{0,2})([\w]*)$', $layout, $allMatchResults);

    // the label and default sizes of all the supported fields are listed above.
    // special handling is handled in the case statement below.

    $workingFields = array();
    for($i = 0 ;  $i < count($allMatchResults[0]) ; $i++)
    {
      $match = $allMatchResults[3][$i];
      if ($match == 'name')  $match = 'visitorName';  // swap all aliases
      if ($match == 'email') $match = 'visitorEmail';

      $knownField = $knownFields[$match];

      if (!isset($knownField))
        $knownField = array('',0,0,'');  // ignore fields like %xyz, they will stay in the text

      $workingField = array();
      $workingField['label'] = $knownField[0] ;
      $workingField['max'] = is_numeric($allMatchResults[1][$i]) ?  $allMatchResults[1][$i] :  $knownField[1] ;
      $workingField['min'] = is_numeric($allMatchResults[2][$i]) ?  $allMatchResults[2][$i] :  $knownField[2] ;
      $workingField['class'] = $knownField[3];
      $workingField['wholeField'] = $allMatchResults[0][$i];

      $workingFields[$match] = (object) $workingField ;
    }
    if (count($workingFields) == 0)
      die(__("One or more of the templates has 0 fields. Please go to Ecampaign settings 
      and check that templates exist for the two forms and (re)save them, especially following an 
      installation or an upgrade. If that doesn't work, cut and paste default settings."));
    return $workingFields ;
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

    $error = array() ;

    if (empty($page->targetEmail))
      $error[] = sprintf(__('%s not set.'), 'targetEmail') ;

    if (empty($page->targetSubject))
      $error[] = sprintf(__('%s not set.'), 'targetSubject') ;

    if (empty($page->friendSubject))
      $error[] = sprintf(__('%s not set.'), 'friendSubject') ;

    if (empty($messageBody))
      $error[] = sprintf(__("there is no text between [ecampaign] and [/ecampaign].")) ;


    $messageBodyParts = preg_split("$<hr[^/]*/>$", $messageBody);
    if (count($messageBodyParts) < 2)
    {
      $error[] = __("cannot find a &lt;hr/&gt; between the introduction of the action and the suggested message text." . "<br/>") ;
    }
    else
    {
      $page->targetBody = trim(preg_replace("$^</[pP]>$", "\n", $messageBodyParts[0]));
      $page->friendBody = trim(preg_replace("$^</[pP]>$", "\n", $messageBodyParts[1]));
    }

    if (count($error) > 0)
    {
      $errorText = implode('</p><p>', $error);
      die(__('ecampaign: page not setup properly')."<p>$errorText</p> <p>{$this->help()}</p>");
    }

    $options = $this->options ;  // er just being lazy

    $campaignEmailBrokenUp = Ecampaign::breakupEmail($page->campaignEmail);
    $targetEmailBrokenUp = Ecampaign::breakupEmail($page->targetEmail);

    /** layout the target form **/

    $targetLayout = $options->targetLayout ;

    foreach ($this->targetFields as $match => $field)
    {
      $replace = '' ;
      switch($match) {

        case 'to' :
          $recipientsBrokenUp = $options->testMode ? $campaignEmailBrokenUp : $targetEmailBrokenUp;
          $settingsUrl =  admin_url("options-general.php?page=ecampaign");
          $helpOutOfTestMode =  $options->testMode ?  "<span id='text-test-mode'>[in test mode <a href='{$settingsUrl}')>change</a>]</span>" : "" ;
          $replace = "<div class='ecfield'>to: $recipientsBrokenUp $helpOutOfTestMode</div>" ;
          break ;

        case 'subject' :
          $replace = Ecampaign::renderField($match, '',
            $page->targetSubject, $field->max, $field->min, '', $field->class);
          break ;

        case 'body' :
          $replace = Ecampaign::renderTextArea('body',$this->replaceParagraphTagsWithNewlines($page->targetBody), $field->min, $field->max);
          break ;

        case 'checkbox1' :
          if (empty($options->checkbox1Text))
            continue ;
          $replace = Ecampaign::renderCheckBox('checkbox1', $options->checkbox1Text, '');
          break ;

        case 'checkbox2' :
          if (empty($options->checkbox2Text))
            continue ;
         $replace = Ecampaign::renderCheckBox('checkbox2', $options->checkbox2Text, '');
         break ;

        case 'campaignEmail' :
          $replace = $campaignEmailBrokenUp ;
          break ;

        case 'send' :
          $nonce = wp_create_nonce('ecampaign');
          $campaignEmailHidden =  Ecampaign::hideEmail($page->campaignEmail);  // hide @
          $recipientsHidden = Ecampaign::hideEmail($options->testMode ? $options->campaignEmail : $page->targetEmail); // hide @
          $replace =
       "<input type='hidden' name='targetEmail'    value='{$recipientsHidden}' />
        <input type='hidden' name='campaignEmail'  value='{$campaignEmailHidden}' />
        <input type='hidden' name='_ajax_nonce'  value={$nonce} />
        <input type='hidden' name='action'  value='ecampaign_post'/>
        <input type='hidden' name='ecampaign_action'  value='sendToTarget'/>
        <input type='hidden' name='referer'  value='{$_SERVER["HTTP_REFERER"]}'/>

        <div class='ecsend'><input type='button' name='send-to-target' value='{$field->label}'
         onclick='return ecam.onClickSubmit(this, ecam.targetCallBack);'/></div>
        <div class='ecstatus'></div>" ;
          break ;

        default :
          $replace = Ecampaign::renderField($match, $field->label, '', $field->max, $field->min, '', $field->class);
          break ;
      }
      if (isset($replace))
      {
        $targetLayout = preg_replace("/{$field->wholeField}/", $replace, $targetLayout, 1);
      }
    }

    /** layout the friends form **/


    $friendsLayout = $options->friendsLayout ;

    foreach ($this->friendsFields as $match => $field)
    {
      $replace = '' ;
      switch($match) {

        case 'friendSubject' :
          $replace = Ecampaign::renderField($match, '',
            $page->friendSubject, $field->max, $field->min, '', $field->class);
          break ;

        case 'friendBody' :
          $scriptUri = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
          $replace = Ecampaign::renderTextArea($match,$this->replaceParagraphTagsWithNewlines($page->friendBody)
            . "\n\n" . $scriptUri, $field->min, $field->max);
          break ;

        case 'friendEmail' :
          $replace = "
              <div id='ec-friends-list'>".
                  Ecampaign::renderField('emailfriend1', $field->label, '', $field->max, $field->min, 'first', 'validateEmail') .
             "</div>
              <div id='ec-add-friend'><label>&nbsp;</label><a
              href='#' onclick='return ecam.addFriend()'>" . __("add another") . "</div></a>";
          break ;

        case 'friendSend' :
          $nonce = wp_create_nonce('ecampaign');
          $campaignEmailHidden =  Ecampaign::hideEmail($page->campaignEmail);  // hide @
//          $recipientsHidden = Ecampaign::hideEmail($options->testMode ? $options->campaignEmail : $page->targetEmail); // hide @
          $replace =
       "<input type='hidden' name='_ajax_nonce'  value={$nonce} />
        <input type='hidden' name='campaignEmail'  value='{$campaignEmailHidden}' />
        <input type='hidden' name='action'  value='ecampaign_post'/>
        <input type='hidden' name='ecampaign_action'  value='sendToFriend'/>

        <div class='ecsend'>
          <input type='button' name='send-to-friends'  value='{$field->label}'
          onclick='return ecam.onClickSubmit(this, ecam.friendsCallBack);' />
        </div>
        <div class='ecstatus'></div>";

          break ;

        default :
          $replace = Ecampaign::renderField($match, $field->label, '', $field->max, $field->min, '', $field->class);
          break ;
      }
      if (isset($replace))
      {
        $friendsLayout = preg_replace("/{$field->wholeField}/", $replace, $friendsLayout, 1);
      }
    }

    $html =
    "<!-- ecampaign plugin for wordpress John Ackers  -->
    <div id='ec-target' class='ecform'  >
    $targetLayout
    </div>
    <div id='gap-between-forms'>&nbsp;</div>
    <div id='ec-friends' class='ecform hidden'  >
    $friendsLayout
    </div>" ;

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
      $response = array("success"=>false, "msg" => "Error: " . $e->getMessage()) ;
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
    $field = new stdClass ;
    Ecampaign::fetchPostedTextAreas($field, $this->targetFields, array('body'));

    Ecampaign::fetchPostedOtherFields($field, $this->targetFields,
    array('subject', 'visitorName', 'visitorEmail',
    'address1', 'address2', 'address3','city', 'ukpostcode', 'postcode', 'state', 'zipcode', 'country',
    'checkbox1', 'checkbox2', 'targetEmail', 'campaignEmail', 'referer'));

    $field->campaignEmail = Ecampaign::hideEmail($field->campaignEmail) ;  // restore @
    $recipientString =      Ecampaign::hideEmail($field->targetEmail) ;  // restore @
    $recipients = explode(',', $recipientString );

    // any square brackets inside the text of the message must be deleted by the site visitor

    if (0 < preg_match("/([\[][^\]]*\])/", $field->body))
    {
      throw new Exception(__("Please note guidance inside the square brackets [ ] in the message"));
    }

    Ecampaign::validateEmail($field->visitorEmail, $this->options->checkdnsrr); // throws exception if fails.

    $mailer = Ecampaign::getPhpMailer($this->options);
    $mailer->Subject = $field->subject;
    $mailer->From = $field->visitorEmail;
    $mailer->FromName = $field->visitorName;
    $mailer->AddBCC($field->visitorEmail);       // copy of email for site visitor
    // add the originating URL so abuse can be tracked back to specific web page
    $mailer->addCustomHeader("X-ecampaign-URL: {$_SERVER["HTTP_REFERER"]}");

    foreach ($recipients as $recipient)
    {
      $recipient =  trim($recipient);
      Ecampaign::validateEmail($recipient, $this->options->checkdnsrr);
      $mailer->AddAddress($recipient);
    }
//    if ($this->options->bccCampaignEmail)
//      $mailer->AddBCC($field->campaignEmail);    // this isn't really necessary

    $mailer->Body = Ecampaign::assemblePlainMsg(array($field->body,
      $field->visitorName,
      $field->address1,$field->address2,$field->address3,
      $field->city,$field->postcode,$field->ukpostcode,"{$field->state} {$field->zipcode}",$field->country));

    $success = $mailer->Send();

    if (!$success)
    {
      throw new Exception(__("unable to send email to") . " {$recipientString}, {$mailer->ErrorInfo}");
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
                       "referer:    $field->referer",
                       "remote:     {$_SERVER['REMOTE_HOST']} {$_SERVER['REMOTE_ADDR']}",
                       "checkbox1:  $ch1  $options->checkbox1Text",
                       "checkbox2:  $ch2  $options->checkbox2Text",
                       " ",
                       $mailer->Body));

    $success = $mailer->Send();

    if (!$success)
      throw new Exception(__("unable to cc email to"). " {$field->campaignEmail}, {$mailer->ErrorInfo}");

    return $this->options->thankYouText ;
  }

  /**
   * email activists friends
   * can throw exception containing text error message
   * @return html string
   */

  function sendToFriend()
  {
    $field = new stdClass ;
    Ecampaign::fetchPostedTextAreas($field, $this->friendsFields, array('friendBody'));

    Ecampaign::fetchPostedOtherFields($field, $this->friendsFields,
    array('friendSubject', 'visitorName', 'visitorEmail', 'campaignEmail'));

    $field->campaignEmail = Ecampaign::hideEmail($field->campaignEmail) ;  // restore @

    Ecampaign::validateEmail($field->visitorEmail, $this->options->checkdnsrr); // throws exception if fails.

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
    return sprintf(_n("Your email has been sent to %d friend. Thank you.", "Your email has been sent to %d friends. Thank you.", $numSuccess), $numSuccess);
  }


  static function renderField($name, $label, $value, $max, $min, $class1, $class2)
  {
    if ($min > 0)
      $class2 .= ' mandatory' ;
    // note that the value of id e.g. 'City' is expected to be translated to local language
    // and, worst it may contain a apostrophe
    $replace = "<div class='ecfield $class1'><label for='$name' >"
      .$label
      ."</label>"
      ."<input type='text' name='$name' id=\"{$label}\" size='$max' class='$class2' value=\"{$value}\" /></div>\n";
    return $replace ;
  }

  static function renderCheckBox($name, $label, $class)
  {
    return "<div class='ecfield'><input type='checkbox' name='$name' id='$label' class='$class'/><label for='$name'>$label</label></div>\n";
  }


  static function renderTextArea($name, $val, $rows, $cols)
  {
    // note that the value of id e.g. 'City' is expected to be translated to local language
    return "<div class='ecfield'><textarea name='$name' rows=$rows cols=$cols >"
           .$val
           ."</textarea></div>\n";
  }

  /**
   * float the label over the text field
   *
  static function renderFieldFloatLabel($name, $label, $class)
  {
    $replace = "<p><input type='$type' name='$name' size='$max' class=' $class' onkeydown='ecam.removeLabel(this);' />
    <label class='label' for='$name'>$label</label></p>\n";

    return $replace ;
  }
   */

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

  static function fetchPostedTextAreas(&$target, $fieldList, $fields)
  {
    foreach($fields as $fieldName)
    {
      $target->$fieldName = $_POST[$fieldName];
      $target->$fieldName = html_entity_decode($target->$fieldName, ENT_QUOTES);
      if (get_magic_quotes_gpc())
      {
        $target->$fieldName = stripslashes($target->$fieldName);
      }
      Ecampaign::assertLength($target->$fieldName, $fieldName, $fieldList);
    }
  }

  /**
   * check data entered in the single line fields
   * @param unknown_type $fields
   * @return unknown_type
   */
  static function fetchPostedOtherFields(&$target, $fieldList, $fields)
  {
    foreach($fields as $fieldName)
    {
      $target->$fieldName = $_POST[$fieldName];
      if (get_magic_quotes_gpc())
      {
        $target->$fieldName = stripslashes($target->$fieldName);
      }
      Ecampaign::assertLength($target->$fieldName, $fieldName, $fieldList);
      Ecampaign::assertCleanString($target->$fieldName, $fieldName);
    }
  }

  /**
   * check length meets any minimum specified in fieldList
   * (derived from layout and default field list);_
   *
   * @returns the field so functions can be used inline
   */

  static function assertLength($target, $fieldName, $fieldList)
  {
    $field = $fieldList[$fieldName];
    if (isset($field))
    {
      if (is_numeric($field->min))
      {
        if (strlen($target) < $field->min)
          throw new Exception($field->min . __(" or more characters expected in") . " $fieldName: $target");
      }
    }
  }


  /**
   * check for field injection in headers.  Think that any injection has
   * to be terminated with a \n character for it to influence the email header.
   * Note : filter_var DISABLED right now
   *
   * @returns the field so functions can be used inline
   */

  static function assertCleanString($target, $fieldName="", $use_filter_var = false)
  {
    if ($use_filter_var && (function_exists('filter_var')))
    {
      $ftarget = filter_var($target, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      if ($ftarget != $target)
        throw new Exception(__("invalid character in") . " $fieldName: ". $target);
      return ;
    }
    if (preg_match("/[\\000-\\037<>]/", $target))  // probably inadequate
      throw new Exception(__("unexpected character in"). " $fieldName: ". $target);
    return ($target);
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
    $phpmailer->WordWrap = 76 ;    // reluctantly
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
      throw new Exception(__("invalid email address, control characters present"). " : {$recipient}");
    }
    if (function_exists('filter_var'))
    {
      if (!filter_var($recipient, FILTER_VALIDATE_EMAIL))
        throw new Exception(__("invalid email address")." : {$recipient}");
    }

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
          throw new Exception(__("unknown domain"). " : {$recipient}");
        }
      }
    }
    return true;
  }
}

