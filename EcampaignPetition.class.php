<?php

include_once dirname(__FILE__) . '/Ecampaign.class.php';

/**
 * Handle ecampaign ajax triggered actions
 * to sign petition.
 *
 * @author johna
 */


class EcampaignPetition extends Ecampaign
{
  const jsRevealNextForm = "revealNextForm"  ;

  function __construct()
  {
    parent::__construct();
    $this->defaultTemplate = get_option('ec_petitionLayout');
    $this->styleClass = 'ecform ec-target';
    $this->validAjaxMethods[] = "sign" ;
    $this->validAjaxMethods[] = "send" ;
  }

  /**
   * if site options are configured to validate email address send the sitre visitor an
   * email with a 4 digit code
   *
   * @return unknown_type
   */

  private function sendVerificationCode($fieldSet, $code)
  {
    $mailer = self::getPhpMailer();
    $mailer->Subject = $fieldSet->subject ? $fieldSet->subject : "Verification code for ecampaign action" ;
    $mailer->From = $fieldSet->campaignEmail ;
    $mailer->FromName = get_bloginfo("name");
    $mailer->AddAddress($fieldSet->visitorEmail, $fieldSet->visitorName);

//    if ($this->options->bccCampaignEmail)
//      $mailer->AddBCC($fieldSet->campaignEmail);    // this isn't really necessary

    $mailer->Body = $fieldSet->subject . "\r\n\r\n".
      sprintf(__("Thank you for verifying your email address. ".
      "Please enter %s in the original web page in order to send your email."), $code);

    $success = $mailer->Send();

    if (!$success)
    {
      throw new Exception(__("unable to send email to") . " {$fieldSet->visitorEmail}, {$mailer->ErrorInfo}");
    }

    $this->log->write("verification", $fieldSet, "code:". $code);

    return array("success" => true, //"getCode" => true,
                 "callbackJS" => 'revealVerificationField',
                 "msg" => __("Please check your email. Please enter 4 characters in the empty code field above."));
  }


  private function preProcess()
  {
    $desiredFields = array_merge(array(self::sPostID, self::sVisitorName, self::sVisitorEmail, self::sCampaignEmail, self::sReferer),
                     array_keys($targetFields = $this->templateFields));

    $controlFields = array(self::sPostID => new EcampaignField(self::sPostID),
                           self::sReferer => new EcampaignField(self::sReferer));

    $fieldSet = $this->fieldSet = EcampaignField::requestPartialMap($desiredFields, array_merge($controlFields, self::$allFields));

    $fieldSet->recipients =  self::parseEmailRecipients($_POST['recipientsEmail']) ;

    self::validateEmail($fieldSet->campaignEmail, "campaignEmail"); // throws exception if fails.

    // any square brackets inside the text of the message must be deleted by the site visitor

    if (0 < preg_match("/([\[][^\]]*\])/", $fieldSet->body))
    {
      return (array("success"=>false, "msg" =>__("Please note guidance inside the square brackets [ ] in the message")));
    }

    self::validateEmail($fieldSet->visitorEmail, "visitorEmail"); // throws exception if fails.

    $address = new EcampaignString(array($fieldSet->address1,$fieldSet->address2,$fieldSet->address3,
      $fieldSet->city,$fieldSet->postcode,$fieldSet->ukpostcode,
      trim("{$fieldSet->state} {$fieldSet->zipcode}"),$fieldSet->country));

    $fieldSet->postalAddress = $address->removeEmptyFields();

    $this->infoMap = array(
       "referer: " . $fieldSet->referer,
       "remote: " . "{$_SERVER['REMOTE_HOST']} {$_SERVER['REMOTE_ADDR']}",
       "user-agent: " . $_SERVER['HTTP_USER_AGENT']);

    if (isset($targetFields[self::sCheckbox1]))
      $this->infoMap[] = "checkbox1: " . ($fieldSet->ch1 = isset($fieldSet->checkbox1) ?  "yes" : "no") ." ". $targetFields[self::sCheckbox1]->label ;

    if (isset($targetFields[self::sCheckbox2]))
      $this->infoMap[] = "checkbox2: " . ($fieldSet->ch2 = isset($fieldSet->checkbox2) ?  "yes" : "no") ." ". $targetFields[self::sCheckbox2]->label ;

    /* catcha and verification need to work together easily if only for test pUurposes.
     * verification field not displayed until captcha entered so don't check captcha again
     * not least because i think captcha codes only work once.
     */

    $userSuppliedVerificationCode = $_POST[self::sVeriCode];

    if (array_key_exists(self::sCaptcha, $targetFields) && empty($userSuppliedVerificationCode))
    {
      $captchadir = get_option('ec_captchadir');
      include_once WP_PLUGIN_DIR . $captchadir . '/securimage.php' ;
      $securimage = new Securimage();
      if ($securimage->check($_POST[self::sCaptcha]) == false)
      {
        $this->log->write("captchaFail", $fieldSet);
        return array("success" => false,
                     "msg" => __("The captcha security code does not match the image, please try again. Case does not matter."));
      }
    }

    if (array_key_exists(self::sVeriCode, $targetFields))
    {
      // nonce creation lifted from wordpress pluggable.php
      // verification code is different on each site for same email addresses

      $i = wp_nonce_tick();
      $hashCodes = array();
      $action = $fieldSet->targetEmail . $fieldSet->visitorEmail;
      // Nonce generated 0-12 hours ago
      $hashCodes[] = substr(wp_hash($i . $action, 'nonce'), -12, 4);
      // Nonce generated 12-24 hours ago
      $hashCodes[] = substr(wp_hash(($i - 1) . $action, 'nonce'), -12, 4);

      if (empty($userSuppliedVerificationCode))
      {
        return $this->sendVerificationCode($fieldSet, $hashCodes[0]);
      }

      if ((0 != strcasecmp($userSuppliedVerificationCode,$hashCodes[0]))
      &&  (0 != strcasecmp($userSuppliedVerificationCode,$hashCodes[1])))
      {
        return array("success" => false,
                     "msg" => __("The code entered $userSuppliedVerificationCode does not appear to match the value sent by email."));
      }
      $this->log->write("verified", $fieldSet, $this->infoMap);
    }

    // Increment counter. Note that this read-and-update is not an
    // indivisible transaction, some other thread might be accessing this code
    // and the count may fall behind.

//    add_post_meta($fieldSet->postID, self::$counter, 0, true);
    $count = get_post_meta($fieldSet->postID, self::ecCounter, true);
    update_post_meta($fieldSet->postID, self::ecCounter, $count+1);

    // setup success message
    $this->successMessage = $this->templateFields[self::sSuccessMessage]->value;
    if (empty($this->successMessage))
    {
      $this->successMessage = __("Done. Thank you for taking part in this action.");
    }
    return false ;
  }

  function sign()
  {
    $response = $this->preProcess();
    if ($response)
      return $response;

    $fieldSet = $this->fieldSet;

    $this->log->write("sign", $fieldSet, $this->infoMap);
    $this->subscribe($fieldSet);
    return $this->revealNextApplicableForm(array("success" => true, "msg" => $this->successMessage));

  }
  /*
   * email the original or updated message to the party or parties that are the target of
   * this campaign.
   * can throw exception containing text error message
   */

  function send()
  {
    $response = $this->preProcess();
    if ($response)
      return $response;

    $fieldSet = $this->fieldSet;

    $mailer = self::getPhpMailer();
    $mailer->Subject = $fieldSet->subject;
    $mailer->From = $fieldSet->visitorEmail;
    $mailer->FromName = $fieldSet->visitorName;
    $mailer->AddBCC($fieldSet->visitorEmail);       // copy of email for site visitor

    foreach ($fieldSet->recipients as $recipient)
    {
      self::validateEmail($recipient, "recipientEmail");
      $mailer->AddAddress($recipient);
    }
//    if ($this->options->bccCampaignEmail)
//      $mailer->AddBCC($fieldSet->campaignEmail);    // this isn't really necessary

    $text = new EcampaignString();
    $text->add($fieldSet->body)  // this has been trimmed
         ->add(" ")
         ->add($fieldSet->visitorName)
         ->add($fieldSet->postalAddress);
    $mailer->Body = $text->asBlock();

    $this->infoMap[] = 'recipients: ' . ($recipientString = implode(', ', $fieldSet->recipients));

    $delivery = $this->testMode->isSuppressed() ? 1 : ($mailer->Send() ? 2 : 0);

    if ($delivery == 0)
      throw new Exception(__("unable to send email to") . " {$recipientString}, {$mailer->ErrorInfo}");

    if (!$this->testMode->isNormal())
      $this->infoMap[] = 'test mode: ' . $this->testMode->toString();

    $this->log->write("sent", $fieldSet, $this->infoMap);
    $this->subscribe($fieldSet);

    // forward a similar version of the email to the campaign but add the checkfields that they have clicked

    if (true)
    {
      $mailer->ClearAllRecipients();
      $mailer->AddAddress($fieldSet->campaignEmail);
      $mailer->Subject = "$fieldSet->ch1 : $fieldSet->ch2 : " . $fieldSet->subject ;

      $this->infoMap[] = " "; $this->infoMap[] = $mailer->Body;
      $mailer->Body = implode("\r\n", $this->infoMap);

      $delivery |= $this->testMode->isSuppressed() ? 1 : ($mailer->Send() ? 2 : 0);

      if ($delivery == 0)
        throw new Exception(__("unable to cc email to"). " {$fieldSet->campaignEmail}, {$mailer->ErrorInfo}");
    }
    $sMsg = $this->successMessage . ($delivery == 2 ? "" : " Delivery suppressed (code:$delivery) ");
    $response = array("success" => true,
                      "msg" => $sMsg,
                      "callbackJS" => self::jsRevealNextForm);

    return $this->revealNextApplicableForm(array("success" => true, "msg" => $sMsg));
  }

  /**
   * subscribe this site visitor to an email list
   */

  function subscribe($fieldSet)
  {
    $listClassPath = get_option('ec_subscriptionClass');
    if (empty($listClassPath))
      return ;
    $listClass = _createFromClassPath($listClassPath);
    try {
      $listClass->subscribe($fieldSet->visitorEmail,
                            $fieldSet->checkbox1, $fieldSet->checkbox1);

      $this->log->write("subscribe", $fieldSet, $this->infoMap);
    }
    catch(Exception $e)
    {
      $this->log->write("exception", $fieldSet, $e->getMessage());
    }
  }
}

?>