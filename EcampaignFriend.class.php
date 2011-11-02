<?php

/**
 * share with friends (limited to email)
 */


include_once dirname(__FILE__) . '/Ecampaign.class.php';

class EcampaignFriend extends Ecampaign
{
  function __construct()
  {
    parent::__construct();
    $this->defaultTemplate = get_option('ec_friendsLayout');
    $this->styleClass = 'ecform ec-friend';
    $this->validAjaxMethods[] = "friendSend" ;
    $this->bodyTrailer = "<p>". get_permalink() . "</p>";
  }

  /**
   * email activists friends
   * can throw exception containing text error message
   * @return html string
   */

  function friendSend()
  {
    $controlFields = array(self::sPostID => new EcampaignField(self::sPostID));
    $friendsFields = array_merge(array(self::sPostID, self::sVisitorName, self::sVisitorEmail, self::sCampaignEmail),
                     array_keys($this->templateFields));

    $fieldSet = $this->fieldSet = EcampaignField::requestPartialMap($friendsFields, array_merge($controlFields, self::$allFields));

    self::validateEmail($fieldSet->visitorEmail, "visitorEmail"); // throws exception if fails.
    self::validateEmail($fieldSet->campaignEmail, "campaignEmail"); // throws exception if fails.

    $mailer = self::getPhpMailer();
    $mailer->From = $fieldSet->visitorEmail;   $mailer->FromName = $fieldSet->visitorName;
    $mailer->AddBCC($fieldSet->campaignEmail);
    $mailer->Subject = $fieldSet->subject;
    $mailer->Body = $fieldSet->body;

    // check all the email addresses before trying to send any messages
    for ($i = 1 ; $i <= 10 ; $i++)
    {
      $friendEmail = $_POST['emailfriend'.$i];
      if (!empty($friendEmail))
      {
        self::validateEmail($friendEmail, "friendEmail");
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
    $this->log->write("friends", $fieldSet);
    return sprintf(_n("Your email has been sent to %d friend. Thank you.", "Your email has been sent to %d friends. Thank you.", $numSuccess), $numSuccess);
  }
}


?>