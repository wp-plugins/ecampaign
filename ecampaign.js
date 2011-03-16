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
  var formRoot = button.parents('form, .ajaxform');
  if (formRoot.length == 0)
  {
    alert('onClickSubmit(): no form wrapper');
    return false;
  }

  // find the element used to hold status messages
  var status = formRoot.find('#status');
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
      ecam.updateStatus(status,false, field.attr('name') + ": mandatory field " + field.val());
      return false ;
    }
  }

  fields = formRoot.find(".validateEmail");
  var pattern = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);

  for (i=0 ; i < fields.length ; i++)
  {
    field = fields.eq(i) ;
    if (field.val().length == 0)
      continue ;
    if (!pattern.test(field.val()))
    {
      field.focus() ;
      ecam.updateStatus(status,false, field.attr('name') + ": invalid email address " + field.val());
      return false ;
    }
  }
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
        if (httpRequest.status > 0)  
      {
        ecam.updateStatus(status, false, httpRequest.status + " " + httpRequest.statusText);
        return ;
      }
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
 * update status which is normally written next to the send button
 */

ecam.updateStatus = function(status, success, msg)
{
  if (status.length == 0)
  {
    alert(msg);  return success ;  // fallback to alerts
  }
  if (success)
    status.empty().append('div').css('color', 'blue').html(msg);
  else
    status.empty().append('div').css('color', 'red').html(msg);
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
  jQuery('#ecampaign-friends').show();
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
//  jQuery('#friendsList .first').find('input').attr('value',''); // blank field
//  jQuery('#friendsList .subsequent').remove();
}


/**
 * add another input box for the user to enter friends name
 * by copying the first box
 *
 * don't seem to be able to clone 
 */


ecam.addFriend = function() 
{
  var subsequentFriend = jQuery('#friendsList .first').clone().removeClass('first').addClass('subsequent')
      .appendTo(jQuery('#friendsList'));

  input = subsequentFriend
      .contents('input')
      .attr('name','emailfriend' + (1+subsequentFriend.siblings().length)).attr('value','')
      .after("<a href='#' class='smaller float' onclick='return ecam.removeFriend(this)'>remove</a>");

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



