<?php

/*
class : MSP
Author: John Ackers
Date : 18-oct-2011

Supports the use of a %lookup button in a form.
The lookup function takes the value of the field 'ukpostcode'
and looks up the corresponding constituency MSP using two steps.


This class is loaded by ecampaign.php when the
attribute class='uk/MSP' is added to the ecampaign shortcode
e.g. [ecampaign class='uk/MSP'].....[/ecampaign]

*/

include_once dirname(__FILE__) . '/../EcampaignTarget.class.php';

class MSP extends EcampaignTarget
{
  const sLookup = 'lookup' ;

  function __construct()
  {
    parent::__construct();
    $this->classPath = "uk/MSP";
    $this->validAjaxMethods[] = self::sLookup ;
    $this->submitEnabled = false  ;       // initially disable the send button
  }

  function initializeCannedFields()
  {
    parent::initializeCannedFields();
    $this->cannedFields[self::sLookup] = array('lookup MSP',   0,  0);
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
   * Get the MSP name from theyworkforus.  Then search for their second name
   * on a scottish parliament web page. This code could easily break and
   * needs replacing with direct API.
   *
   * can throw exception containing text error message
   */

  function lookup()
  {
    $desiredFields = array(self::sPostID, self::sUKPostcode, self::sCampaignEmail, self::sVisitorName, self::sVisitorEmail);
    $controlFields = array(self::sPostID => new EcampaignField(self::sPostID));
    $this->fieldSet = EcampaignField::requestPartialMap($desiredFields, array_merge($controlFields, self::$allFields));

    if (empty($this->fieldSet->ukpostcode))
      throw new Exception("Postcode field is empty");

    $twfyKey = get_option('ec_thirdPartyKey');  // http://www.theyworkforyou.com/api/key
    if (empty($twfyKey))
      throw new Exception("TheyWorkForYou third Party API key not set");

    $mspList = self::request("http://www.theyworkforyou.com/api/getMSP?output=xml&key=$twfyKey&postcode=". urlencode($this->fieldSet->ukpostcode),
    "/twfy/match");

    if ($mspList == null)
      throw new Exception("Unable to retrieve MSP list for ". $this->fieldSet->ukpostcode);

    $targets = array();
    foreach ($mspList as $msp)
    {
      if (empty($constituency))
        $constituency = $msp ;    // save details of first MSP

      $target['name']  = (String) $msp->{"full_name"} ;

      if (true)  // scrape the official web page
      {
        $mspEmail = self::lookupMSPEmailFromName((String) $msp->{"first_name"}, (String) $msp->{"last_name"} );
      }
      else    // unused fall back, this works for most but not all MSPs
      {
        $mspEmail = (String) $msp->{"first_name"} .".". (String) $msp->{"last_name"} .".msp@scottish.parliament.uk" ;
      }
      $target['email'] = $this->testMode->isDiverted() ? $this->fieldSet->campaignEmail : (String) $mspEmail ;
      $targets[]  = $target ;
    }
    $info = $this->fieldSet->ukpostcode.", ".(String) $constituency->constituency.", ".count($targets)." MSPs";
    $this->log->write("lookup", $this->fieldSet, $info);
    return array("target" => $targets,
                 "constituency" => (String) $constituency->constituency,
                 "success" => true,
                 "callbackJS" => 'updateMessageFields',
                 "msg" => (String) $constituency->constituency);
  }

  static $mspPage = null ;

  /**
   * This is a messy function to search for an MSPs name on a Scottish Parliament webpage.
   * @param unknown_type $mspLastName
   */

  private static function lookupMSPEmailFromName($firstName, $lastName)
  {
    if (self::$mspPage == null)
    {
      $url = "http://www.scottish.parliament.uk/msps/177.aspx" ;

      $header = array();  // Scottish parliament website is running asp and likes to see familiar browsers
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

      self::$mspPage = curl_exec($ch);
    }
    $ab = self::$mspPage;

    //try traditional firstname.lastname
    $mspRegex = '$href="mailto:([^"]*?' . $firstName . "\." . $lastName . '[^"]*?)"$i';
    $num = preg_match_all($mspRegex, self::$mspPage, $matches);
    if ($num == 1)
      return $matches[1][0];

    // then try lastname only
    $mspRegex = '$href="mailto:([^"]*?' . $lastName . '[^"]*?)"$i';
    $num = preg_match_all($mspRegex, self::$mspPage, $matches);
    if ($num == 1)
      return $matches[1][0];

    if ($num > 1)
      throw new Exception("Unable to find $firstName $lastName and multiple email addresses match $lastName on page $url");

    return "" ;
  }

  /**
   * wrapper to make external requests and process response
   *
   * @param $url
   * @param $xpath
   * @return unknown_type
   */

  private function request($url, $xpath = null)
  {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $xml = curl_exec($ch);

    curl_close($ch);

    if ($xml == false)
      throw new Exception("Unable to reach or no response from " . $url);

    $error = array();
    if (0 < preg_match("$<error>(.+?)<\/error>$", $xml, $error))
      throw new Exception($error[1]);

    $xmlnodes = simplexml_load_string($xml);

//    if ($xmlnodes == false)
  //    return false ;

    if (isset($xpath))
    {
      $xmlnodes = $xmlnodes->xpath($xpath);
    }
    return $xmlnodes;
  }
}
?>