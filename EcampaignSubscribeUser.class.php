<?php
/**
 * Create/update a wp user account for an ecampaign site vistor
 * Extra fields about the user are saved in user meta dats
 */

class EcampaignSubscribeUser
{
  private $args ;

  function checkConfiguration($connectDB=false)
  {
    $this->args = EcampaignField::parseAttributes(get_option('ec_subscriptionParams'), true);
    if (!isset($this->args['optin']))
      return (__("Configuration error: optin='name-of-checkbox expected"));

    return "" ;
  }

  /**
   *
   * @param unknown_type $templateFields  full set of fields and attributes
   * @param unknown_type $fieldSet        easy to access set of fields.
   */
  function subscribe($templateFields, $fieldSet)
  {
    $msg = self::checkConfiguration();
    if (!empty($msg))
      throw new Exception($msg);

    $optin = $this->args['optin'];

    if ($optin=='true')
      $optinb = true ;
    else
    {
      if (!isset($templateFields[$optin]))
        throw new Exception("Configuration error: Unable to test $optin because it is not a valid field");

      $optinb = $templateFields[$optin]->value == 'on' || $templateFields[$optin]->value == 1 ;
    }
    if (!$optinb)
      return ;

    $user = get_user_by('email', $fieldSet->visitorEmail);
    if (!$user)
    {
      $randomPassword = wp_generate_password( 12, false );
      $userId = wp_create_user($fieldSet->visitorName, $randomPassword, $fieldSet->visitorEmail );
    }
    else
    {
      $userId = $user->ID ;
    }
    foreach ($templateFields as $field)
    {
      if (strcasecmp($field->save,'usermeta')==0 && !empty($field->value))
      {
        $success = update_user_meta($userId, $field->name, $field->value);
        if (!success)
          throw Exception("Unable to update user meta data for $fieldSet->visitorEmail");
      }
    }
  }
}