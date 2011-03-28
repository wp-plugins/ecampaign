/**
 * 
 * 
 * ecampaign js support 
 * 
 * Notes that element names in embedded html in ecampaign.class.php
 * must match elements names in javascript.
 */


/*
 * @param button clicked when user sends email
 * @param callBack called when action completes
 * @return
 */

var ecam = {} ;

ecam.onClickSubmit = function (buttonElement, callBack)
{
  // check that we know where we are
  if (buttonElement == null)
  {
    alert('onClickSubmit(): clickable element not declared');
    return false;
  }
  var button = jQuery(buttonElement);
  // find the root of the form
  var formRoot = button.parents('form, .ecform');
  if (formRoot.length == 0)
  {
    alert('onClickSubmit(): no form wrapper');
    return false;
  }

  // find the element used to hold status messages
  var status = formRoot.find('.ecstatus');
  if (status.length == 0)
  {
    alert('onClickSubmit(): status element not declared');
    return false;
  }

  var fields = formRoot.find(".mandatory");

  for (i=0 ; i < fields.length ; i++)
  {
    field = jQuery(fields).eq(i) ;
    if (field.val().length < 2)
    {
      field.focus() ;
      ecam.updateStatus(status,false, "Very short " + field.attr('id') + " " + field.val());
      return false ;
    }
  }

  // http://msdn.microsoft.com/en-us/library/ff650303.aspx  US Postcode

  fields = formRoot.find(".validateZipcode");
  var pass = ecam.validateField(fields, status, "invalid", /^(\d{5}-\d{4}|\d{5}|\d{9})$|^([a-zA-Z]\d[a-zA-Z] \d[a-zA-Z]\d)$/);
  if (!pass)  return false;

  // http://en.wikipedia.org/wiki/UK_postcodes
  
  fields = formRoot.find(".validatePostcode");
  var pass = ecam.validateField(fields, status, "invalid", /^[A-Z]{1,2}[0-9R][0-9A-Z]? [0-9][ABD-HJLNP-UW-Z]{2}$/);
  if (!pass)  return false;     
  
  fields = formRoot.find(".validateEmail");
  var pass = ecam.validateField(fields, status, "invalid", /^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);
  if (!pass)  return false;

  // when the user sends email to the target or to his friends, all the fields are 
  // picked up from formRoot. However when sending to his friends, his  
  // visitorName and visitorEmail have to be picked up from the ecampaign-action form, 
  
  var formFields = jQuery('input[name="visitorName"],input[name="visitorEmail"]')
                   .add(formRoot.find("*"));
  
  // about to post to server...

  ecam.updateStatus(status,true,"waiting...");

  jQuery.ajax({
    type:     "POST",
    url:      ecampaign.ajaxurl,
    dataType: "json",
    data:     formFields.serializeArray(), // gather all input fields
    error:    function(httpRequest, textStatus, errorThrown)
    {
      if (httpRequest.status != undefined)  
        if (httpRequest.status != 200)   
      {
        ecam.updateStatus(status, false, httpRequest.status + " " + httpRequest.statusText);
        return ;
      }
      // Server may be returning xml/html but not in JSON, e.g. stack dump
      ecam.updateStatus(status, false, textStatus + " " + errorThrown) ;
    },
    success:  function(response, textStatus, httpRequest)
    {
      if (response == undefined)
      {
        ecam.updateStatus(status, false, 'Error: send aborted by server'); 
      }
      else
      {
        ecam.updateStatus(status, response.success, response.msg); callBack(response.success, button);
      }
    }
  });
  return false; // suppress any other action
}

/**
 * validateField against regular expression
 */

ecam.validateField = function(fields, status, errorText, patternText)
{
  var pattern = new RegExp(patternText);
  for (i=0 ; i < fields.length ; i++)
  {
    field = fields.eq(i) ;
    if (field.val().length == 0)
      continue ;
    
    if (!pattern.test(field.val().toUpperCase()))
    {
      field.focus() ;
      ecam.updateStatus(status,false, errorText + " " + field.attr("id") + " : " + field.val());
      return false ;
    }
  }
  return true ;
}




/**
 * update status which is normally written next to the send button
 */

ecam.updateStatus = function(status, success, msg)
{
  if (status.length == 0)
  {
    alert(msg);  return success ;  // fallback to alerts
  }
  if (success)
    status.empty().append('div').removeClass('ecError').addClass('ecOk').html(msg);
  else
    status.empty().append('div').removeClass('ecOk').addClass('ecError').html(msg);
}

/**
 * called back when email send to target
 * @param button (jQuery) clicked by user
 */

ecam.targetCallBack = function (success, button)
{
  if (!success)
    return false;
  
  button.attr("disabled","disabled");
  
  // display send to friends block
  jQuery('#ec-friends').show();
}


/**
/**
 * called back when email send to friends
 * @param button (jQuery) clicked by user
 *
 * optionally empty the friends fields but it's useful for
 * site vistor to see the email addresses have been used.
 */


ecam.friendsCallBack = function(success, button)
{
//  jQuery('#ec-friendsList .first').find('input').attr('value',''); // blank field
//  jQuery('#ec-friends-list .subsequent').remove();
}


/**
 * add another input box for the user to enter friends name
 * by copying the first box
 *
 * don't seem to be able to clone 
 */

ecam.addFriend = function() 
{
  var subsequentFriend = jQuery('#ec-friends-list .first')
      .clone(true)
      .removeClass('first').addClass('subsequent')
      .eq(0)         // don't understand why needed
      .appendTo(jQuery('#ec-friends-list'));

  var numChild = jQuery('#ec-friends-list').children('div').length ;

  var forr = subsequentFriend
      .children('label')
      .attr('for','emailfriend' + numChild);

  var input = subsequentFriend
      .children('input')
      .attr('name','emailfriend' + numChild).attr('value','')
      .after("<a href='#' class='smaller float' onclick='return ecam.removeFriend(this);'>remove</a>");

  return false  ;
}


ecam.removeFriend = function(friendElement)
{
  jQuery(friendElement).parent().remove(); // remove the whole line
  return false;
}


ecam.removeLabel = function(inputElement)
{
  var input = jQuery(inputElement)
  var val =input.attr('value');
  if (val.length > 0) 
      input.next('label').hide();
  else 
      input.next('label').show();
  
  return false;
}
