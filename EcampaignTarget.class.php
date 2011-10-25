<?php

include_once dirname(__FILE__) . '/EcampaignPetition.class.php';

/**
 * Handle ecampaign ajax triggered actions
 * for send email to target
 *
 * Functionality for email actions and petition
 * was originally in the same class.
 *
 * @author johna
 */


class EcampaignTarget extends EcampaignPetition
{
  function __construct()
  {
    parent::__construct();
    $this->defaultTemplate = get_option('ec_layout');
  }
}