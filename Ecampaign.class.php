<?php
/*

class : Ecampaign
Author: John Ackers

This class is dynamically loaded by ecampaign.php to handle an ecampaign
tag embedded in wordpress post or page. If there are muliple tags on the
same page, multiple instances of the class are instantiated.

requires PHP version > 5.0

There has to be one instance of Ecampaign per form.

*/

include_once dirname(__FILE__) . '/EcampaignField.class.php';
include_once dirname(__FILE__) . '/EcampaignLog.class.php';
include_once dirname(__FILE__) . '/EcampaignString.class.php';

class Ecampaign
{
  static function help($anchor)
  {
    $path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
    $helpFile = "readme.html" ;
    return "<a target='_blank' href='$path$helpFile$anchor' title='$anchor: Open ecampaign help page in another window'>". __("More help")."</a>" ;
  }

  static $formList, $allFields = array();

  protected $classPath,                   // path and class name of declaring class
            $testMode,                    // determines whether messages are delivered and to where
            $cannedFields = null,         // array of hard coded fields and label names
            $templateFields,              // array of fields after template processing
            $submitEnabled = true,        // prevents attempts to send/sign before any (postcode) lookups
            $defaultTemplate,             // set by extending classes
            $styleClass = 'notset';       // class attribute given to outer wrapper of form

  public $validAjaxMethods = array(),     // methods in this class that ajax calls can access
         $fieldSet ;                      // name/value pairs recieved in POST

  const ecCounter = 'ecCounter' ;    // number of people taking action, counter value is saved in metadata in post

  const
    sTo = 'to',
    sFrom = 'from',
    sSubject = 'subject',
    sBody = 'body',
    sVisitorName = 'visitorName',
    sVisitorEmail = 'visitorEmail',
    sAddress1 = 'address1',
    sAddress2 = 'address2',
    sAddress3 = 'address3',
    sCity = 'city',
    sPostcode = 'postcode',
    sUKPostcode = 'ukpostcode',
    sLookup = 'lookup',
    sZipcode = 'zipcode',
    sState = 'state',
    sCountry = 'country',
    sCheckbox1 = 'checkbox1',
    sCheckbox2 = 'checkbox2',
    sVeriCode = 'verificationCode',
    sCaptcha = 'captcha',
    sSend = 'send',
    sSign = 'sign',
    sCounter = 'counter',
    sReferer = 'referer',
    sPostID = 'postID',

    sFriendEmail = 'friendEmail',
    sFriendSend = 'friendSend' ,

    sTargetEmail = 'targetEmail',
    sCampaignEmail = 'campaignEmail',
    sSuccessMessage = 'success' ; // was previously the thank you message

  /**
   *
   * @param unknown_type $atts  all the attribute that follow </campaign>
   * @param unknown_type $messageBody  the text between the <ecampaign> and </ecampaign>
   * @return unknown_type
   */


  function __construct()
  {
    $this->testMode = new EcampaignTestMode();
    $this->log = new EcampaignLog();
    $this->classPath = get_class($this);
    $this->bodyTrailer = "" ;
    if (!isset(self::$formList)) self::$formList = array();
  }

/*
  function initializeCannedFieldsNext()
  {
    $this->cannedFields =

    " { " .self::sTo.     " label = ". __('To'). " min=10 size=70 } ".
    " { " .self::sSubject." label = ". __('').   " min=10 size=70 } ".
    " { " .self::sBody. " label = ". __('').   " min=10 size=70 } ".

    "";
  }
*/


  function initializeCannedFields()
  {
    $this->cannedFields = array(
      self::sTo           => array(__('To')),    // field lengths irrelevant
      self::sSubject      => array(null,           "data-min='10' size='70'"),
      self::sBody         => array(null,           "data-min='30' cols='70' rows='10'"),
      self::sVisitorName  => array(__('Name'),     "data-min='4'  size='20'"),
      self::sVisitorEmail => array(__('Email'),    "data-min='4'  size='20'", 'validateEmail'),
      self::sAddress1     => array(__('Address 1'),"data-min='4'  size='20'"),
      self::sAddress2     => array(__('Address 2'),"data-min='4'  size='20'"),
      self::sAddress3     => array(__('Address 3'),"data-min='4'  size='20'"),
      self::sCity         => array(__('City'),     "data-min='4'  size='10'"),
      self::sPostcode     => array(__('Postcode'), "data-min='4'  size='10'"),
      self::sUKPostcode   => array(__('Postcode'), "data-min='4'  size='10'", 'validateUKPostcode'),
      self::sZipcode      => array(__('Zipcode'),  "data-min='5'  size='10'", 'validateZipcode'),
      self::sState        => array(__('State'),    "data-min='2'  size='2'"),    // tx, ca
      self::sCountry      => array(__('Country'),  "data-min='2'  size='15'"),   // us, uk
      self::sVeriCode     => array(__('Code'),     "data-min='4'  size='4'"),
      self::sCaptcha      => array(__('Captcha'),  "data-min='4'  size='4'"),
      self::sSend         => array(__('Send')),
      self::sSign         => array(__('Sign the petition')),

      self::sFriendEmail  => array(__("Friend's email address") ,"data-min='0', size=15'",'validateEmail'),
      self::sFriendSend   => array(__('Send to friends'),        "data-min='0'"),

      self::sCampaignEmail=> array(null, "")              // so it gets saved as session data
    );
  }

  /**
   * parse the template and extract all the fields and their atributes
   * each field is of the form
   *
   * {fieldName[*] attributeKey1=attributeValue1 attributeKey2=attributeValue2... fieldValue}
   *
   * attribute values can be single or double quoted but quotes cannot be quoted or escaped
   *
   * parser tested at http://www.regextester.com/index2.html with string
   * {subject* min=20 flavor='chocolate chip' Lorem ipsum dolor sit amet}
   * {body rows=8 cols='70' Ut enim ad minim veniam}
   *
   * @return array of EcampaignField
   */
  const regexParseTemplate = '${(\w+)(\*)?((?:\s+[\w][\w-]+=(?:[%]?[\w]+|[\'\"\”][^\'\"\”]+[\'\"\”]))*)\s*([^}]*)}$' ;

  function parseTemplate($layout, $pageAttributes)
  {
    $parsedFields = array();
    preg_match_all(self::regexParseTemplate, $layout, $parsedFields);
    // the label and default sizes of all the supported fields are listed above.
    // special handling is handled in the case statement below.

    $templateFields = array();
    for($i = 0 ;  $i < count($parsedFields[0]) ; $i++)
    {
      $efield = new EcampaignField ;
      $efield->wholeField = $parsedFields[0][$i];
      $noun = $parsedFields[1][$i];

      // use aliases for just these two fields, full name use everywhere else
      if ($noun == 'name')  $noun = 'visitorName';
      if ($noun == 'email') $noun = 'visitorEmail';

      $efield->name = $noun ;

      $knownField = $this->cannedFields[$noun];

      if (!isset($knownField))
      {
        $knownField = array($noun,'');  // ignore fields like %xyz we don't recognise, they will stay in the text
        $efield->isCustom = true ;
      }
      // attributes in template or form are allowed to overwrite canned attributes
      $attributeMap = EcampaignField::parseAttributes($knownField[1]." ".$parsedFields[3][$i]);

      $efield->label = isset($attributeMap['label']) ? $attributeMap['label'] : $knownField[0];
      $attributeMap['label'] = null ;  // remove label attribute from HTML

      $efield->save = $attributeMap['save'];  $attributeMap['save'] = null;
      $efield->type = $attributeMap['type'];
      $efield->attributes = EcampaignField::serializeAttributes($attributeMap);
      $efield->mandatory = $parsedFields[2][$i] == "*";
      $efield->value = $value = trim($parsedFields[4][$i]);
      $efield->validator = $knownField[2];

      if (!empty($value))
      {
        $efield->definition = true ;
        $efield->value = $value ;
      }
      // page attributes override any values set in template fields even if template on the page
      if (empty($efield->value))
      {
        $efield->value = $pageAttributes->$noun ;
      }

      $templateFields[$noun] = $efield ;
    }
    return $templateFields ;
  }


  /**
   * Generate html for one or more forms
   * @return html string
   */

  function createPage($pageAttributes, $pageBody)
  {
    $this->initializeCannedFields();
    $this->testMode = new EcampaignTestMode($pageAttributes->testMode);
    if (empty($pageBody))
      throw new Exception(__("there is no text between [ecampaign] and [/ecampaign] or the closing [/ecampaign] is missing"));

    if (empty($pageAttributes->campaignEmail))
      $pageAttributes->campaignEmail = get_option('ec_campaignEmail');
    $nonce = wp_create_nonce('ecampaign');
    $postID = get_the_ID();
    $hiddenFields =
     "<input type='hidden' name='_ajax_nonce'  value='{$nonce}' />
      <input type='hidden' name='referer'  value='{$_SERVER["HTTP_REFERER"]}'/>
      <input type='hidden' name='postID'  value='{$postID}'/>";

    $form = null ;  $pageParts = preg_split("$<hr[^/]*/>$", $pageBody);

    /**
     * Only body of message defined. using default template.
     */

    switch(count($pageParts))
    {
      case 2 :      // legacy mode, two forms, target email in top form, friends email in bottom form
      {
        $error = array() ;

        if (empty($pageAttributes->targetEmail))
          $error[] = sprintf(__('%s attribute not set.'), 'targetEmail') ;

        if (empty($pageAttributes->targetSubject))
          $error[] = sprintf(__('%s attribute not set.'), 'targetSubject') ;

        if (empty($pageAttributes->friendSubject))
          $error[] = sprintf(__('%s attribute not set.'), 'friendSubject') ;

        if (count($error) > 0)
        {
          $errorText = implode('</p><p>', $error);
          die(__('ecampaign: page not setup correctly') ."&nbsp;". self::help()."#setup"
          ."<p>$errorText</p>"
          ."<div style='border:1px solid red; padding:5px'> <code>$pageBody</code></div>");
        }

        // this is legacy mode and it's messy

        $this->classPath = 'EcampaignTarget'; // bodge
        if (empty($pageAttributes->to))      $pageAttributes->to = $pageAttributes->targetEmail ;
        if (empty($pageAttributes->subject)) $pageAttributes->subject = $pageAttributes->targetSubject ;
        $pageAttributes->body = $pageParts[0] ;
        // Convert the clean \r\n characters into html breaks so its in the same
        // format as if the form were embedded in the post.
        $template = preg_replace("$[\r\n]+$", "<br/>", get_option('ec_layout'));
        $form = self::createForm($template, "ecform ec-target", $pageAttributes, $hiddenFields);

        $this->classPath = 'EcampaignFriend'; // bodge
        $pageAttributes->subject = $pageAttributes->friendSubject ;
        $pageAttributes->body = $pageParts[1] . "<p>". get_permalink() . "</p>";
        $pageAttributes->hidden = true ;
        // Convert the clean \r\n characters into html breaks so its in the same
        // format as if the form were embedded in the post.
        $template = preg_replace("$[\r\n]+$", "<br/>", get_option('ec_friendsLayout'));
        $form .= self::createForm($template, "ecform ec-friend", $pageAttributes, $hiddenFields);
        $sections[] = $form;
        break ;
      }

      case 1 :
      default :
      {
        if (4 < preg_match_all('$[{}]$' ,$pageParts[0], $matches))
        {     // single form using the template inside the post
          $template = preg_replace('$[\r\n]+$', '', $pageParts[0]);  // loose existing CRLF
          $pageAttributes->body = null ;  // the body is contained in the template
        }
        else
        {
          $template = preg_replace("$[\r\n]+$", "<br/>", $this->defaultTemplate);
          $pageAttributes->body = $pageParts[0];
        }
        if (empty($pageAttributes->to))  $pageAttributes->to = $pageAttributes->targetEmail ;
        $form = self::createForm($template, $this->styleClass, $pageAttributes, $hiddenFields);
        $sections[] = $form;
        break ;
      }
      $hiddenFields = "" ; // only needed on first form
    }
    return "<!-- http://www.wordpress.org/plugins/ecampaign  -->\r\n" . $form ;
  }

  /**
   *
   * @param $template  Must be at least 4 fileds to be accepted.
   * @param $id
   * @param $pageAttributes
   * @param $hiddenFields
   */

  function createForm($template, $styleClass, $pageAttributes, $hiddenFields)
  {
    $id = EcampaignField::nextID();
    self::$formList[$id] = array();
    self::$formList[$id]['classPath'] = $this->classPath;

    // wrap all the lines in the template in DIV.ECROW,
    // this is less flexible but makes layout in IE7 less troublesome.
    //
//    $rows = explode("\r\n", $template);
//    $html = "<div class='ecrow'>" . implode("</div>\r\n<div class='ecrow'>", $rows) ."</div>" ;

    $templateFields = self::parseTemplate($html=$template, $pageAttributes);

    if (count($templateFields) == 0)
    {
      $settingsUrl =  admin_url("options-general.php?page=ecampaign");
      $settingsLink = "<a href='$settingsUrl'>Ecampaign settings</a>";
      die(__("One or more of the templates has zero fields. If you are the site admin,
      please go to $settingsLink and check that templates. <br/><br/>".$template));
    }

    foreach ($templateFields as $noun => $efield)
    {
      $snippet = $this->createField($noun, $efield, $pageAttributes, $templateFields);
      if (isset($snippet))
      {
        $html = str_replace($efield->wholeField, $snippet, $html);
        unset($efield->wholeField);     // no need to serialize it
      }
    }
    // remove comments
    $html = preg_replace("<--.*?-->", "", $html);
    // split on paragraph tags
    $rows = preg_split("%(</?[pP]>[\\r\\n]*)|(<[bB][rR]\W*>[\\r\\n]*)%", $html);


    $html = "<div class='ecrow'>" . implode("</div>\r\n<div class='ecrow'>", $rows) ."</div>" ;

    unset($pageAttributes->body);       // no need to serialize it (and it can change
                                        // if URL attached to bottom of friends body changes)
    $form['pageAttributes'] = $pageAttributes ;
    $form['template'] = $templateFields ;
    $form['testMode'] = $this->testMode ;

    $this->updateFormList(get_the_ID(), $id, $form);

    $displayClass = $pageAttributes->hidden ? "hidden" : "" ;
    return  "<div id='$id' class='$styleClass $displayClass' >"
          . $html . $hiddenFields
          . "</div>\r\n" ;
  }

  /**
   * store all the form data in post meta because the ajax POST
   * handler doesn't have access to the page text or plugin attributes etc
   * Data only updated if it's changed.
   * Note there is double serialization.
   *
   * @param unknown_type $postID
   * @param unknown_type $formID
   * @param unknown_type $form
   */

  function updateFormList($postID, $formID, $form)
  {

    $formSerialized = serialize($form);

    $formListSerialized = get_post_meta($postID, 'formList', true);
    if (empty($formListSerialized))
    {
      $formList = array();  // creating a new list.
    }
    else
    {
      $formList = unserialize($formListSerialized);
    }
    $oldFormSerialized = $formList[$formID];
    if (!empty($oldFormSerialized))
    {
      if ($formSerialized === $oldFormSerialized)
      {
        return ;  // version stored is identical
      }
    }
    $formList[$formID] = $formSerialized;
    update_post_meta($postID, 'formList', serialize($formList));
    $this->log->write("formUpdate", array(), "postID:$postID formID:$formID");
  }

  /**
   * expected to be called when form has been been filled in
   * and completed and we want to reveal the next form
   *
   * @param unknown_type $response
   */
  function revealNextApplicableForm($response)
  {
    $currentID = $_POST['formID'];

    if (!is_array(self::$formList))
      throw new Exception("Session data missing or corrupt");

    $selector = array();
    $forms = self::$formList;

    // restore all forms including ones that are wrapped
    // in other ecampaign tags on the same page.

    foreach($forms as $ID => $serializedForm)
    {
      if (isset($lastID)) // reveal all subesquent forms
      {
        $form = unserialize($serializedForm);
        $ecampaign2 = _createFromClassPath($form['classPath']);
        // this is a bodge, copy properties from one to other class
        $ecampaign2->restoreForm($this->fieldSet->postID, $ID);  // overwrites self::$formList
        $ecampaign2->fieldSet = $this->fieldSet; // need to access posted data
        if ($ecampaign2->filterVisitors())
        {
          $selector[] = "#$ID " ; // reveal this form
        }
      }
      if ($currentID == $ID)
        $lastID = $currentID ;
    }
    if (is_array($response))
    {
      $response['selector'] =  implode(", " , $selector) ;
      $response['callbackJS'] = 'revealForm';
    }
    return $response  ;
  }


  /**
   * Using the information about the user available in posted data
   * in a previous form to determine whethere this next
   * form should be presented to the user.
   *
   * For examples visitors might sing a petition and then if their postcode
   * falls inside a particulr boundary, encourage visitors to send an
   * email to their local elected representatives. In the UK that could
   * be a ward councillor or the local plannning office.
   */

  function filterVisitors()
  {
    return true ;
  }


  /**
   * Invoked prior to handling an ajax submit
   * Rearley called so not concerned about time to unserialize
   */

  function restoreForm($postID, $formID)
  {
    $formListSerialized = get_post_meta($postID, 'formList', true);
    if (empty($formListSerialized))
    {
      throw new Exception ("Unable to recall form list for postID $postID");
    }
    self::$allFields = array();
    self::$formList = unserialize($formListSerialized);
    foreach (self::$formList as $ID => $formSerialized)
    {
      if (empty($formSerialized))
      {
        throw new Exception ("Unable to recall form for postID $postID formID $formID");
      }
      $form =  unserialize($formSerialized);
      if ($formID == $ID)
      {
        $this->pageAttributes = $form['pageAttributes'];
        $this->templateFields = $form['template'];
        $this->testMode = $form['testMode'];
      }
      else
        self::$allFields = array_merge(self::$allFields, $form['template']);
    }
    // take values from current form and override values
    self::$allFields = array_merge(self::$allFields, $this->templateFields);

    if (empty($this->templateFields))
    {
      throw new Exception ("Unable to recall form for postID $postID formID $formID");
    }
  }


  /**
   * Generate the field for each specified in the layout
   * @param $noun  to, from, subject or similar
   * @param $field properties of the field
   * @param $pageAttributes
   * @return unknown_type
   */

  function createField($noun, $efield, $pageAttributes)
  {
    switch($noun) {

      case self::sTo :
        $recipientsBrokenUp = self::breakupEmail(
          $this->testMode->isDiverted()? $pageAttributes->campaignEmail: $efield->value);

        $settingsUrl = admin_url("options-general.php?page=ecampaign");
        $helpOutOfTestMode =  $this->testMode->is(EcampaignTestMode::sNormal) ? "" : "<span id='text-test-mode'>&nbsp;[{$this->testMode->toString()} <a href='{$settingsUrl}')>change</a>]</span>"  ;
        $html = "<label id='lab-to'>$efield->label:</label><span id='recipients-email' class='ecinputwrap'>$recipientsBrokenUp</span>$helpOutOfTestMode" ;
        break ;
/*
      case self::sSubject  :
        $html = $efield->writeField(null);
        break ;
*/
      case self::sBody :
        if (isset($this->bodyTrailer))   // set in EcampaignFriends to carry url of post
          $efield->value .= $this->bodyTrailer ;
        $efield->value = strip_tags($this->replaceParagraphTagsWithNewlines($efield->value));
        $html = $efield->writeTextArea();
        unset($efield->value) ;    // to prevent if from being serialized
        break ;

      case self::sCaptcha :  // todo: need to display warning in captcha module not loaded

        $captchadir = get_option('ec_captchadir');
        $imageUrl =  WP_PLUGIN_URL . $captchadir . "/securimage_show.php" ;
        $html = "
          <label for='captcha' />&nbsp;</label>
          <img id='ec-captcha' src='{$imageUrl}' alt='#Captcha Image' title='"
        . __("Type the characters in this image into the box below.") . "' />
       <br/>
           <label for='captcha' />{$efield->label}&nbsp;*</label>
           <span class='ecinputwrap'>
             <input type='text' id ='captcha' name='captcha' size='10' maxlength='6' class='mandatory' />
             <a href='#'
               onclick=\"document.getElementById('ec-captcha').src = '{$imageUrl}?' + Math.random();
               return false\">" .  __("Try a different image") . "</a>
           </span>";
        break ;

      case self::sVeriCode :
        $efield->wrapper = 'eccode hidden';
        $html = $efield->writeField();
        break ;

      case self::sSign :
      case self::sSend :
      case self::sFriendSend :
        $efield->attributes .= $this->submitEnabled ? "" : " disabled='disabled'" ;
        $efield->attributes .= " onclick=\"return ecam.onClickSubmit(this, '$this->classPath', '$noun');\" ";
        $efield->name = 'submit';
        $html = "<div class='ecsend'>".$efield->writeButton()."</div><div class='ecstatus'></div>" ;
        break ;


      case self::sFriendEmail :
        $efield->name = 'emailfriend1';
        $efield->wrapper = 'ecfriend' ;
        $html = "
        <div id='ec-friends-list'>".
          $efield->writeField()."
          <div id='ec-add-friend'><label>&nbsp;</label>
          <a href='#' onclick='return ecam.addFriend()'>" . __("add another") . "</a></div>
        </div>";
        break ;

      case self::sCounter :
        $efield->isCustom =false ;
        $val = get_post_meta(get_the_ID(), self::ecCounter, true);
        $html = is_numeric($val)  ? $val : 0 ;
        break ;

      case self::sCampaignEmail :
        $efield->isCustom =false ;
        $html = $efield->definition ? "" : self::breakupEmail($efield->value);
        break ;

      case self::sSuccessMessage :  // have to remove any para tags, new lines are just ignored
        $efield->isCustom =false ;
        $efield->value = $this->replaceParagraphTagsWithNewlines($efield->value);
        $html = $efield->definition ? "" : $efield->value;
        break ;


      case self::sCheckbox1 :
      case self::sCheckbox2 :
        $efield->type='checkbox' ;

      default :  // handles name, email, zipcode, postcode etc

        // this is inconsistent behaviour but just for checkboxes
        // the label is taken from the value i.e. the text inside the {}
        // and the initial value (checked or otherwise) has to be set using the checked attribute

        if ($efield->type=='checkbox')
        {
          $efield->label = $efield->value ;  $efield->value = null;
          $html = $efield->writeCheckBox();
          break ;
        }
        else
        {
          $html = $efield->writeField();
          break ;
        }
    }
    return $html ;
  }



//  <label for="send-formName" class="labeloverlay sendoverlay labeloverlayhidden" >Name</label>

  /**
   * Convert </p> and <br/> tags into CR-LF and strip out any adjacent CR and LF that happen to be there
   * Note that a </p><p> sequence will be converted into \r\n\r\n i.e. one blank line between paras
   * @param $html
   * @return $text with \n added
   */

  static function replaceParagraphTagsWithNewlines($html)
  {
    // remove comments
    $html = preg_replace("<--.*?-->", "", $html);
    // replace paragraph tags
    $html = preg_replace("%(</?[pP]>[\\r\\n]*)|(<[bB][rR][^>]*>[\\r\\n]*)%", "\r\n", $html);
    return trim($html);   // use trim to remove last pair(s) of \r\n\
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
   * email addresses returned from the to: field in the browser
   * *all* of which are in 1 of 2 formats
   * 1.  mailbox@domain
   * 2.  first-name last-name <mailbox@domain>
   * and are expected to be separated by commas but not assuming that
   *
   * Cannot find any simple way of parsing emails in both formats
   * in one expression which doesn't involve fiddling with the parser results.
   *
   * @param $emailString
   * @return string containing emails separated by any whitespace character.
   */
  static function parseEmailRecipients($emailString)
  {
    $parsedFields = array();
    $num = preg_match_all('$<[\s]*([^>]+)[\s]*>$', $emailString, $parsedFields);
    if ($num >  0)
      return $parsedFields[1];  // ie just the email addresses inside < and >

    $num = preg_match_all('$[,\s]*([^<>,\s]+)$', $emailString, $parsedFields);
    return $parsedFields[1];    // return every word with 1 or more chars
  }


 /**
   * return address of PHPMailer and sets mailer transport
   *
   * @return unknown_type
   */

  static function getPhpMailer()
  {
    require_once ABSPATH . WPINC . '/class-phpmailer.php';
    require_once ABSPATH . WPINC . '/class-smtp.php';
    $phpmailer = new PHPMailer(true);   // throw exceptions
    $phpmailer->WordWrap = 76 ;    // hate forcing word wrap
    $phpmailer->Mailer = get_option('ec_mailer');
    $phpmailer->CharSet = apply_filters( 'wp_mail_charset', get_bloginfo( 'charset' ));
    // add the originating URL so abuse can be tracked back to specific web page
    $phpmailer->addCustomHeader("X-ecampaign-URL: {$_SERVER["HTTP_REFERER"]}");
    return $phpmailer ;
  }


/**
 *
 * @param $email      address to be validated
 * @param $checkDns
 * @return nothing
 *
 * This function has been borrowed from si-contact_form
 */

  static function validateEmail($recipient, $fieldName, $checkDNS=true)
  {
    if (function_exists('filter_var'))
    {
      if (!filter_var($recipient, FILTER_VALIDATE_EMAIL))
      {
        throw new Exception("$fieldName: ". __("rejected by FILTER_VALIDATE_EMAIL")." : {$recipient}");
      }
    }
    else  // for older PHP versions
    {
      $validEmail = preg_match('/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/', $recipient);
      if (!validEmail)
        throw new Exception("$fieldName: ". __("invalid email address")." : {$recipient}");
    }

    // Make sure the domain exists with a DNS check (if enabled in options)
    // MX records are not mandatory for email delivery, this is why this function also checks A and CNAME records.
    // if the checkdnsrr function does not exist (skip this extra check, the syntax check will have to do)
    // checkdnsrr available in Linux: PHP 4.3.0 and higher & Windows: PHP 5.3.0 and higher

    if ($checkDNS && get_option('ec_checkdnsrr')) {
      if( function_exists('checkdnsrr') ) {
        list($user,$domain) = explode('@',$recipient);
        if(!checkdnsrr($domain.'.', 'MX') &&
        !checkdnsrr($domain.'.', 'A') &&
        !checkdnsrr($domain.'.', 'CNAME')) {
          // domain not found in DNS
          throw new Exception("$fieldName: ". __("unknown domain"). " : {$recipient}");
        }
      }
    }
  }
}
