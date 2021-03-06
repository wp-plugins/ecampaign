<?php

/*
class : Councillor
Author: John Ackers

Supports the use of a %lookup button in a form.
The lookup function takes the value of the field 'ukpostcode'
and looks up the corresponding UK councillors using two steps.

It makes use of the UK government service http://openlylocal.com
Not all councils publish details of their councillors through this
service. So it's possible to find out the name of the ward but
not the details of the councillors.

(At the time of writing (Sep 2011), it typically takes one
second but sometimes it is slow to respond.

This class is loaded by ecampaign.php when the
attribute class='uk/Councillor' is added to the ecampaign shortcode
e.g. [ecampaign class='uk/Councillor'].....[/ecampaign]

This class can be extended, for example, to change the sample message
depending on the constituency.

*/

include_once dirname(__FILE__) . '/../EcampaignTarget.class.php';

class Councillor extends EcampaignTarget
{
  const sLookup = 'lookup' ;

  function __construct()
  {
    parent::__construct();
    $this->classPath = "uk/Councillor";
    $this->validAjaxMethods[] = self::sLookup ;
    $this->submitEnabled = false  ;       // initially disable the send button
  }

  function getPredefinedFields($s="")
  {
    return parent::getPredefinedFields($s.'{'.self::sLookup.' label="Lookup Councillor(s)" type="button"}');
  }

  function createField($noun, $field, $page)
  {
    switch($noun) {

      case self::sLookup :
        $classname = get_class($this);  // class that will be invoked to handle this command
        $html = "
        <span class='eclookup'>
          <input type='button' name='lookup-postcode' value='{$field->label}'
          onclick=\"return ecam.onClickSubmit(this,  '$this->classPath', 'lookup');\"/>
        </span>
        <span class='ecstatus'></span>";
        break ;

      default :
        $html = parent::createField($noun, $field, $page);
    }
    return $html ;
  }

  /*
   * email the original or updated message to the party or parties that are the target of
   * this campaign.
   * can throw exception containing text error message
   *
   * reworked 13-sep-13 to use modified openly local API.
   */

  function lookup()
  {
    $desiredFields = array(self::sPostID, self::sUKPostcode, self::sCampaignEmail, self::sVisitorName, self::sVisitorEmail);
    $controlFields = array(self::sPostID => new EcampaignField(self::sPostID));
    $this->fieldSet = EcampaignField::requestPartialMap($desiredFields, array_merge($controlFields, self::$allFields));

    if (empty($this->fieldSet->ukpostcode))
    {
      throw new Exception("Postcode field is empty");
    }
    $wardUrl = "http://openlylocal.com/areas/postcodes/" . $this->fieldSet->ukpostcode .".xml";

    $wardLink = "<a href='$wardUrl'>more info</a>" ;

    $wardInfo = self::request($wardUrl);
    if (empty($wardInfo))
      return array("success" => false,
                   "msg"=>"Unable to retrieve information for this ward: " . $wardLink);

    $councilName = (String) $wardInfo->ward->council->name;
    $wardName = (String) $wardInfo->ward->name;

    $councillors = array();
    foreach ($wardInfo->ward->members->member as $member)
    {
      $councillor = array();
      $councillor['name']  = (String) $member->{"first-name"}." ".$member->{"last-name"} ;
      $councillor['email'] = $this->testMode->isDiverted() ?
        $this->fieldSet->campaignEmail : (String) $member->{"email"} ;
      $councillors[] = $councillor;
    }
    $success = !empty($councillors);
    $info = $success ? "$wardName, $councilName" : "Members for $wardName, $councilName not available on OpenlyLocal.com database $wardLink";
    $this->log->write("lookup", $this->fieldSet, $info);
    return array("target" => $councillors,
                 "council" => $councilName,
                 "ward" => $wardName,
                 "success" => $success,
                 "callbackJS" => 'updateMessageFields',
                 "msg" => $info);
  }



  /**
   * Using the information about the user available in posted data
   * determine whethere this form should be presented to the user.
   */

  function filterVisitors()
  {
    if (empty($this->pageAttributes->onlyIn))
      return true ;   // council not specified

    $response = $this->lookup();
    if ($response['success'] == false)
      return false ;      // councillor data may not be available

    if (empty($response['council']))   // should never happen
      return false ;
    return !strcasecmp($this->pageAttributes->onlyIn, $response['council']) ;
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
//    if ($xmlnodes typeof )
    // you have to explicitly prefix names in the default namespace

    $namespaces = $xmlnodes->getDocNamespaces();
    if (isset($namespaces['']))
    {
      $xmlnodes->registerXPathNamespace('ns0', $namespaces['']);
    }
    if (isset($xpath))
    {
      $xmlnodes = $xmlnodes->xpath($xpath);
    }
    return $xmlnodes[0];
  }
}
?>