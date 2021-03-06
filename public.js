/**
 * ecampaign js support 
 */

var ecam = {} ;

/**
 * The default css does not float float labels over input fields
 */

ecam.floatLabel = function(input)
{
  var label = input.parent().prev("label");
  label.addClass('float-over-input');
}; 

/**
 * hide label when input has one of more characters 
 */

ecam.toggleLabel = function(input)
{
  var label = input.parent().prev("label");
  if (input.val() > 0)
    label.hide();
  else
    label.show();
};   


ecam.onClickSubmit = function (buttonElement, phpClass, phpMethod)
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

  // find the closest element used to hold status messages
  var status = ecam.findClosestRelative(button, '.ecstatus');
  if (status.length == 0)
  {
    alert('onClickSubmit(): status element not declared');
    return false;
  }

  formRoot.find('.ecError').remove(); // remove any previous error messages    
  
  var fields = formRoot.find(".validateZipcode, .validateUKPostcode, .validateEmail, :visible.mandatory");

  patternMandatory = new RegExp(/([^\s]{1,})/);
  
  // http://msdn.microsoft.com/en-us/library/ff650303.aspx  US Postcode  
  patternZipcode = new RegExp(/^(\d{5}-\d{4}|\d{5}|\d{9})$|^([a-zA-Z]\d[a-zA-Z] \d[a-zA-Z]\d)$/);

  // http://en.wikipedia.org/wiki/UK_postcodes  
  patternUKPostcode = new RegExp(/^[A-Z]{1,2}[0-9R][0-9A-Z]? [0-9][ABD-HJLNP-UW-Z]{2}$/);
  
  patternEmail = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);  
  
  // process the fields in order
  for (i=0 ; i < fields.length ; i++)
  {
    field = fields.eq(i);
    var pass = ecam.validateField(field.filter(":visible.mandatory"), status, "Required field", patternMandatory );
    if (!pass) return false;
    
    if (field.val().length == 0)  // skip non mandatory, empty fields
      continue ;
    
    var pass = ecam.validateField(field.filter(".validateZipcode"), status, "Invalid zipcode", patternZipcode);
    if (!pass) return false;
    
    var pass = ecam.validateField(field.filter(".validateUKPostcode"), status, "Invalid postcode", patternUKPostcode);
    if (!pass) return false;     
    
    var pass = ecam.validateField(field.filter(".validateEmail"), status, "Invalid email address", patternEmail); 
    if (!pass) return false;
  }

  // when the user sends email to the target or to his friends, all the fields are 
  // picked up from formRoot. However when sending to his friends or somewhere else, his  
  // visitorName, visitorEmail, may have to be picked up from fields in adjacent form. 

  var controlData = new Array();
  
  var recipient = formRoot.find('#recipients-email'); 

  controlData.push(   // and add fields wrapped in DIV tags
    {  name :  'action',          value : 'ecampaign' },
    {  name :  'formID',          value : formRoot.attr('id')},
    {  name :  'class',           value : phpClass },
    {  name :  'method',          value : phpMethod },
    {  name :  'recipientsEmail', value : ecam.removeAntiSpam(recipient) }    
  );

  var primaryFormFields = formRoot.find("input, textarea").serializeArray();
  var secondaryFormFields = formRoot.siblings().find("input, textarea").serializeArray();

  var postData = ecam.mergeArraysSkippingDuplicateKeys(new Array(controlData, primaryFormFields, secondaryFormFields));  
 
  // about to post to server...

  ecam.updateStatus(status,true,"waiting...");

  jQuery.ajax({
    type:     "POST",
    url:      ecampaign.ajaxurl,
    dataType: "json",
    data:     postData,
    error:    function(httpRequest, textStatus, errorThrown)
    {
      if (httpRequest.status != undefined)  
        if (httpRequest.status != 200)   
      {
        ecam.updateStatus(status, false, httpRequest.status + " " + httpRequest.statusText);
        return false ;
      }
      // Server may be returning xml/html but not in JSON, e.g. stack dump
      // ignore JSON parse errors expected in textStatus and errorThrown 
      ecam.updateStatus(status, false, httpRequest.responseText);
    },
    success:  function(response, textStatus, httpRequest)
    {
      if (response == undefined)
      {
        ecam.updateStatus(status, false, 'Error: submit aborted by server '); 
      }
      else 
      {
        if (response.success == undefined)  // has response been generated by plugin? No, check 'action'
        {
          ecam.updateStatus(status, false, "Error : submit failed, server thread exited returning error code " + response); // e.g. die() called, no 
        }
        else
        {
          ecam.updateStatus(status, response.success, response.msg); 

          if (response.nonce != undefined)        // update nonce 
            formRoot.find("input[name='_ajax_nonce']").attr('value',response.nonce);

          if (response.callbackJS != undefined) 
          {
            switch(response.callbackJS)
            {               // cannot work how to use JS introspection 
              case  'updateMessageFields' :     ecam.updateMessageFields(response, button, formRoot); break;
              case  'revealVerificationField' : ecam.revealVerificationField(response, button, formRoot); break;              
              case  'revealForm' :              ecam.revealForm(response, button, formRoot); break;   
            }
          }
          if (response.regexp != undefined) 
            ecam.updateMessageBody(response.regexp, button, formRoot);
        }
      }
    }
  });
  return false; // suppress any other action
};


ecam.removeAntiSpam = function(selection)
{
  var string = "";
  if (selection.length > 0)
    string = ecam.getNodeText(selection[0], string);
  return string ;
};


ecam.getNodeText = function(parent, string) 
{
  var i ; for (i in parent.childNodes)
  {
    var node = parent.childNodes[i];
    if (node.nodeType == 3)
      string += node.data;
    else {
      if (node.childNodes != undefined)
        string = ecam.getNodeText(node, string);
    }
  }
  return string ; 
};


/**
 * validateField against regular expression
 */

ecam.validateField = function(field, status, errorText, pattern)
{
  if (field.length > 0 && !pattern.test(field.val().toUpperCase()))
  {
    field.focus();
    jQuery('<div></div>').addClass('ecFieldError ecError').html(errorText).appendTo(field.parent());
    return false ;
  }  
  return true ;
};


/**
 * update status which is normally written next to the send button
 */

ecam.updateStatus = function(status, success, msg)
{
  if (status.length == 0)
  {
    alert(msg);  return success ;  // fallback to alerts
  }
  // take care not to trash the status placeholder
  if (success)
    jQuery('<div></div>').addClass('ecOk').html(msg).appendTo(status.empty());
  else
    jQuery('<div></div>').addClass('ecError').html(msg).appendTo(status.empty());
};

/**
 * call back when after postcode/zipcode has been looked up.
 * Update email message fields with new data if supplied
 */

ecam.updateMessageBody = function (regexp, button, formRoot)
{  
  var body = formRoot.find("textarea[name='body']").first();
  var currentText = body.val() ;  // may NOT be actually what has been keyed in 

  var updatedText = currentText.replace(regexp.pattern, regexp.replacement);
  
  if (updatedText != currentText)   // successful update
  {
    ecam.lastOriginalText = currentText;
    ecam.lastUpdatedText = updatedText;
  }    
  else    
  {
    // unable to replace the text in the current page, has the text 
    // been modified by the user, if not, we can use any original 
    // copy of the text that we saved from the last substitution.
    // This situation occurs when the site visitor changes the 
    // postcode multiple times. Easiier to accomodate this than block repeat lookups.
    
    if (ecam.lastUpdatedText != undefined  && currentText == ecam.lastUpdatedText)
    {
      updatedText = ecam.lastOriginalText.replace(regexp.pattern, regexp.replacement);
      if (updatedText != ecam.lastOriginalText)
      {
        ecam.lastUpdatedText = updatedText; 
      }
      else 
      {
        alert("Unable to substitute " + regexp.pattern +  " with " + regexp.replacement);
        return ;         
      }
    }
    else
    {
      alert("Message text may have been edited; " +
          " unable to substitute " + regexp.pattern + " with " + regexp.replacement + 
          ". Please check the message text before sending.");
      return ; 
    }
  }
  var updatedMessageBody = updatedText ;
    
  //you cannot update textarea in IE7 or 8 using innerText and preserve all the line breaks. 
  // so clone html of teaxtarea and insert new textarea into page and remove old text area. 
  if (jQuery.browser.msie) 
  {
    var parent = body.parent();
    var openingTagPattern = new RegExp("<textarea[^>]*>","i");
    var openingTagMatch = openingTagPattern.exec(parent.html());  // get opening tag and attributes
    var textArea = openingTagMatch[0] + updatedMessageBody + '</textarea>' ;
  	
    body.before(textArea); 
    body.remove();
  }
  else 
    body.val(updatedMessageBody); 
}


ecam.updateMessageFields = function (response, button, formRoot)
{  
  var names="", emails="", num=0 ; 

  if (response.target != undefined) // if the target is defined but empty, remove existing email addresses
  {
    for (var i in response.target)
    {
      if (num++ != 0) {
        names =  names + ",<br/>";
        emails = emails + ",";
      }    
      names = names + response.target[i].name + "&nbsp;&lt;" + response.target[i].email + "&gt;" ;
      emails = emails + response.target[i].email ; 
    }
    formRoot.find("#recipients-email").html(names);
  }

  if (response.targetSubject != undefined)
    formRoot.find("input[name='subject']").attr('value',response.targetSubject);
  
  if (response.targetBody != undefined)
    formRoot.find("textarea[name='body']").html(response.targetBody);
  
  submitButton = formRoot.find(".ecsend input");
  submitButton.removeAttr("disabled");
};


ecam.findClosestRelative = function (elements, select)
{  
  var parents = elements.parents();
  for (var i=0 ; i < parents.length ; i++)
  {
    var cousins = jQuery(parents[i]).find(select);
    if (cousins.length > 0) 
      return cousins.first();
  }
  return jQuery();  // returns an empty set
};


/**
 * called back when email send to target
 * @param button (jQuery) clicked by user
 */

ecam.revealVerificationField = function (response, button, formRoot)
{   
  if (response.success)
  {   
    formRoot.find('.eccode').show('slow'); 
  }
};
   
    
ecam.revealForm = function (response, button, formRoot)
{    
  var nextForm ; 
  if (response.success)
  {    
    button.attr("disabled","disabled");   // prevent user from trying to sending two messages
    if (response.selector == undefined)
      nextForm = formRoot.siblings();     // default is to reveal all forms
    else
      nextForm = jQuery(response.selector);
    nextForm.show('slow');                // the friends form is enabled, now show it
  }
};

/**
 * merge all the arrays of name, value pair objects contained in array s 
 * into single array.  Ignore objects with duplicate keys. 
 */

ecam.mergeArraysSkippingDuplicateKeys = function(s)
{
  var keys = new Array();
  var output = new Array();
  for(i in s)
  {
    for(j in s[i])
    {
      obj = s[i][j];
      if (keys[obj.name] == undefined)
      {
        keys[obj.name] = obj.value;
        output.push(obj);
      }
    }
  }
  return output ;
};

/**
/**
 * called back when email send to friends
 * @param button (jQuery) clicked by user
 *
 * optionally empty the friends fields but it's useful for
 * site vistor to see the email addresses have been used.
 */


ecam.friendsCallBack = function(response, button)
{
//  jQuery('#ec-friendsList .first').find('input').attr('value',''); // blank field
//  jQuery('#ec-friends-list .subsequent').remove();
};


/**
 * respond to user
 * add input field for another friend
 * by copying the first box
 *
 * don't seem to be able to clone 
 * problem with conflicting IDs
 */

ecam.addFriend = function() 
{
  var subsequentFriend = jQuery('#ec-friends-list div.ecfriend:first')
      .find('.ecError').remove().end()    // remove any errors
      .clone(true)
      .addClass('subsequent')
      .insertBefore(jQuery('#ec-add-friend'));
  
  //  subsequentFriend.find('.ecError').remove(); 
  
  // renumber all the fields from 1 to n, including the original field
  jQuery('#ec-friends-list .ecfriend').each(function(i, friendElement) {
    var ecfriend = jQuery(friendElement)
      .find('label')
        .removeAttr('id')
        .attr('for','ef-' + i).end()   
      .find('input')
        .attr('id','ef-' + i)
        .attr('name','emailfriend' + (i+1));
  });  
  var input = subsequentFriend.find('input');   
  input.after("<a href='#' class='smaller float' onclick='return ecam.removeFriend(this);'>remove</a>");
  return false  ;
};

/**
 * respond to user
 * remove one of the friends 
 */


ecam.removeFriend = function(friendElement)
{
  jQuery(friendElement).closest('.ecfriend').remove(); // remove the whole line
  return false;
};


ecam.removeLabel = function(inputElement)
{
  var input = jQuery(inputElement)
  var val = input.attr('value');
  if (val.length > 0) 
      input.next('label').hide();
  else 
      input.next('label').show();
  
  return false; 
};




jQuery(document).ready(function() { 
  
  jQuery(".ecinputwrap input[type='text']").each(function(index, element)
  {
//    ecam.floatLabel(jQuery(element));
//    ecam.toggleLabel(jQuery(element));
  });

  jQuery(".ecinputwrap input[type='text']").keyup(
      
      function() {        
//      ecam.toggleLabel(jQuery(this));
        return true ;
      }
  );  
  /**
   * when the use updates an input field, copy it to any other input fields
   * of the same name to reduce rekeying.
   */
  
  jQuery(".ecinputwrap input[type='text']").change(
      
      function() {
        var input = jQuery(this);
        var name = input.attr('name');
        var ecampaign = input.parents(".ecform, .ecampaign");
        var sameInputs = ecampaign.siblings().find(".ecinputwrap input[type='text']");
        sameInputs = sameInputs.filter("[name='" + name + "']");
        var dataKeyed = input.val();
        sameInputs.attr('value', dataKeyed);
//        ecam.flipLabel(sameInputs);
//        sameInputs.after('<div>therather</div>');        
        return true ;
      }
  );
});
