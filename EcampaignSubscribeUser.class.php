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
    if (!isset($this->args['checkbox1']) && !isset($this->args['checkbox2']))
      return (__("Configuration error: checkbox1=on or checkbox2=on expected"));

    return "" ;
  }

  /**
   *
   * @param unknown_type $templateFields  full set of fields and attributes
   * @param unknown_type $fieldSet        easy to access set of fields.
   */
  function subscribe($templateFields, $fieldSet)
  {
    $this->checkConfiguration();

    $subscribe = false ;
    $c1 = $this->args['checkbox1'];
    if (!empty($c1) && $fieldSet->checkbox1)
      $subscribe = true ;

    $c2 = $this->args['checkbox2'];
    if (!empty($c2) && $fieldSet->checkbox2)
      $subscribe = true ;

    if (!$subscribe)
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