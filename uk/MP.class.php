<?php

/*
class : MP
Author: John Ackers

Supports the use of a %lookup button in a form.
The lookup function takes the value of the field 'ukpostcode'
and looks up the corresponding UK councillors using two steps.

It makes use of http://findyourmp.parliament.uk/api

This class is loaded by ecampaign.php when the
attribute class='uk/MP' is added to the ecampaign shortcode
e.g. [ecampaign class='uk/MP'].....[/ecampaign]

*/

include_once dirname(__FILE__) . '/../EcampaignTarget.class.php';

class MP extends EcampaignTarget
{
  const sLookup = 'lookup' ;

  function __construct()
  {
    parent::__construct();
    $this->classPath = "uk/MP";
    $this->validAjaxMethods[] = self::sLookup ;
    $this->submitEnabled = false  ;       // initially disable the send button
  }

  function initializeCannedFields()
  {
    parent::initializeCannedFields();
    $this->cannedFields[self::sLookup] = array(__(self::sLookup),   0,  0);
  }


  function createField($noun, $efield, $page)
  {
    switch($noun) {

      case self::sLookup :
        $html = "
        <span class='eclookup'>
          <input type='button' name='lookup-postcode' value='{$efield->label}'
          onclick=\"return ecam.onClickSubmit(this, '$this->classPath', 'lookup');\"/>
        </span>
        <span class='ecstatus'></span>";
        break ;

      default :
        $html = parent::createField($noun, $efield, $page);
    }
    return $html ;
  }

  /*
   * email the original or updated message to the party or parties that are the target of
   * this campaign.
   * can throw exception containing text error message
   */

  function lookup()
  {
    $desiredFields = array(self::sPostID, self::sUKPostcode, self::sCampaignEmail, self::sVisitorName, self::sVisitorEmail);
    $controlFields = array(self::sPostID => new EcampaignField(self::sPostID));
    $this->fieldSet = EcampaignField::requestPartialMap($desiredFields, array_merge($controlFields, self::$allFields));

    if (empty($this->fieldSet->ukpostcode))
      throw new Exception("Postcode field is empty");

    $constituency = self::request("http://findyourmp.parliament.uk/api/search?f=xml&q=". urlencode($this->fieldSet->ukpostcode),
    "/results/constituencies/constituency");

    if ($constituency == null)
      throw new Exception("Unable to find constituency details for ". $this->fieldSet->ukpostcode);

    $uri = (String) $constituency->uri ;

    $constituency = self::request($uri, "/constituency");

    $target = array();
    $target['name']  = (String) $constituency->{"member-name"} ;
    $target['email'] = $this->testMode->isDiverted() ?
      $this->fieldSet->campaignEmail : (String) $constituency->{"member-email"} ;

    $this->log->write("lookup", $this->fieldSet, (String) $constituency->name);
    return array("target" => array($target),
                 "constituency" => (String) $constituency->name,
                 "success" => true,
                 "callbackJS" => 'updateMessageFields',
                 "msg" => (String) $constituency->name);
  }


  /**
   * wrapper to make external requests and process response
   *
   * @param $url
   * @param $xpath
   * @return unknown_type
   */

  function request($url, $xpath = null)
  {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $xml = curl_exec($ch);

    if ($xml == false)
      throw new Exception("Unable to reach or no response from " . $url);

    curl_close($ch);

    $xmlnodes = simplexml_load_string($xml);

    if ($xmlnodes == false)
      return false ;

    if (isset($xpath))
    {
      $xmlnodes = $xmlnodes->xpath($xpath);
    }
    return $xmlnodes[0];
  }
}
?>