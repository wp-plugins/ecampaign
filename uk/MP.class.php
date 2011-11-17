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

    $memberEmail = (String) $constituency->{"member-email"} ;
    $memberName = (String) $constituency->{"member-name"}  ;

    // Some MPs email addresses are not available (or have been removed)
    // from the database accessible through the API.
    // In that event, get the member biography from the constituency page
    // and scrape through it for a likely email address i.e. one that contains
    // the MPs name. Note some MPs often have office staff handle all their mail.

    if (empty($memberEmail))
    {
      $biographyUrl = (String) $constituency->{"member-biography-url"};
      if (empty($biographyUrl))
        throw new Exception("Unable to find biography (and so email) for ".(String) $constituency->{"member-name"});

      $memberEmail = self::lookupEmailFromName($memberName, $biographyUrl);
    }
    if (empty($memberEmail))
      throw new Exception("Unable to find email address for ".(String) $constituency->{"member-name"});


    $target = array();
    $target['name']  =   $memberName ;
    $target['email'] = $this->testMode->isDiverted() ? $this->fieldSet->campaignEmail : $memberEmail;

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


  /**
   * Trying to find an MPs email address. Search the MPs web page and
   * look for an address that matches (ideally first and) last name.
   * Something similar used on MSP lookup page.
   *
   * @param unknown_type $firstName
   * @param unknown_type $lastName
   * @param unknown_type $url
   */

  private static function lookupEmailFromName($name, $url)
  {
    if (true)
    {
      $header = array();
      $header[] = "Cache-Control: max-age=0";
      $header[] = "User-Agent: Mozilla/5.0 (X11; Linux i686) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.57 Safari/534.24" ;
      $header[] = "Accept: application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;";
      $header[] = "Accept-Language: en-GB,en-US;q=0.8,en;q=0.6";
      $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.8,en;q=0.6";

      $ch = curl_init($url);

      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

      $mpPage = curl_exec($ch);
    }
    if (empty($name))
      throw new Exception("Name is empty");

    $dottedName = str_replace(" ", ".", $name);

    //try traditional firstname.lastname
    $mpRegex = '$href="mailto:([^"]*?' . $dottedName . '[^"]*?)"$i';
    $num = preg_match_all($mpRegex, $mpPage, $matches);
    if ($num == 1)
      return $matches[1][0];

    // then try lastname only
    $names = explode(" ", $name);
    $lastName = $names[count($names)-1];

    $mpRegex = '$href="mailto:([^"]*?' . $lastName . '[^"]*?)"$i';
    $num = preg_match_all($mpRegex, $mpPage, $matches);
    if ($num == 1)
      return $matches[1][0];

    if ($num > 1)
      throw new Exception("Unable to find $name and multiple email addresses match $lastName on page $url");

    return "" ;
  }
}

?>