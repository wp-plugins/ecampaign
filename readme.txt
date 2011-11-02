=== Plugin Name ===
Contributors: john ackers
Donate link: 
Tags: advocacy, activism, email, petition, ecampaign
Requires at least: 3.0.1
Tested up to: 3.2.1
Stable tag: trunk

A plugin that allows a simple petition or email based campaign action to be embedded 
into any wordpress page or post. Petition signatures and email activity is logged
and and viewable under the under the admin pages.

== Description ==

This plugin allows a campaign action to be embedded into any wordpress page 
or post. The sequence of events is:

1. The site visitor is presented with a form containing the petition body or an email message.
2. The visitor adds their name, email address, postal address etc. and can or may be required to customize 
the text of the email.
3. The visitor clicks on 'Sign' or 'Send', this is logged.
4. If applicable,  the email is sent to the target email address(s) and copied to the visitor's address. 
5. If applicable, an extended version of the email that includes the referer, the visitors IP address and details 
of the checked boxes is sent to the campaign email address. 
6. A hidden form is revealed which encourages the visitor to send a prepared email 
to one or more friends.
7. The visitor adds email addresses and clicks on 'Send email to friends'. 

= Features =

* Petition signatures and all other activity/errors/exceptions logged
* Optional CAPTCHA support using http://www.phpcaptcha.org/.
* Email addresses, zipcodes and UK postcodes are client side validated.
* Optional verification of site visitor's email address.
* Site visitor can be required to edit email message, by removing optional guidance notes, before sending.
* When enabled, a simple test mode diverts emails from the target address to the campaign email address.
* There is only one block of content embedded into one page and user interaction is via AJAX.
* All error messages are passed back to the visitor as well as being logged.
* Fields can be added/removed/rearranged and size changed.  
* Appearance customizable via CSS.
* Email addresses displayed are antispammed.
* Log entries are paged, filtered and can be deleted.
* I18n language translation support for server side messages.
* Extensions to look up UK MPs and councillors.


= Configurable Options = 

The site administrator can:

* add/remove/modify fields from the three form templates
* modify the attributes of the INPUT and TEXTAREA elements
* add 1 or 2 checkboxes (e.g. to opt-in to an email list).
* enable DNS checking of email addresses.


== Installation ==

1. Install using 'Plugin, add new" or upload ecampaign.zip to the server and unzip in `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure under admin >> Settings >> Ecampaign or got to /wp-admin/options-general.php?page=ecampaign
4. Create a page that contains [ecampaign] and [/ecampaign] tags, see Setting up a campaign action
5. View that page. The supplied style sheet works for the Atahualpa theme. 
For other themes you may need to change the padding and the fonts etc. If the default template doesn't
match the screen shots, check the template in Ecampaign settings especially after upgrades. 

PHP5 is required. There are no dependencies. filter_vars() from PHP5.2+ is used if available.

If you want to use CAPTCHA, download securimage from http://www.phpcaptcha.org/
and install somewehere under the plugins directory and change the ecampaign 
settings to match that location. The default location is under the ecampaign directory. 
Versions of securimage that have been tested with ecampaign are 2.0 beta 
and 1.02 packaged with si-contact-form. 

Anyone that is able to create/edit pages can create new campaign actions by creating 
a new post or page and embedding the default text of the email along with the 
default subject of the email, the target address and if necessary override the 
campaign email address.

Mail is sent directly via the PHPMailer class because it provides access to 
error messages which aren't available through the wordpress API via wp_mail.
Email addresses that appear on screen and in the HTML are broken up 
to make it slightly more difficult for spammers.

Note: If you are using the SMTP transport option offered by PHPMailer, the 
SMTP parameters must be configured either in php.ini or, for developement
or testing, directly in the top of wp-includes/class-phpmailer.php.

= Upgrading from 0.80 = 
Minor bug fix only.

= Upgrading from 0.77 = 

This is a significant upgrade. *Field definition has changed*. The two new default 
templates must be cut and pasted over the old templates on the settings page. 
This is not done automatically because any customisation would be lost. A new log 
table wp_ecampaign_log is added to the database when the plugin is reactivated. 

= Upgrading from 0.76 = 

CSS changes only.

= Upgrading from 0.75 = 

Deactivate and activate the 0.76 version to clean up the wp_postmeta table.

There was a bug fixed in 0.75 which caused one row of data to be added to the 
wp_postmeta table on every page read. When the 0.76 plugin is activated, the 
old wp_postmeta is saved, deleted and recreated for each post. The old 
rows are deleted and one new row is created in the wp_postmeta table per post.

= Upgrading from 0.74 = 

You have to include %verificationCode %captcha or %counter in the Target Form 
template to enable email verification or captcha protection.

= Upgrading from 0.73 = 

The default form template in 0.73 did not show the body of the message.
because the word %body had merged into another word. The default has been fixed and will work
on new installs. However if you are upgrading, that change has to be made manually by 
going to ecampaign settings and comparing the default template (for 0.74) and the template
you are currently using and may have edited and corrected it if necessary.  

== Frequently Asked Questions ==

None yet.

== Screenshots ==

1. screenshot-1.png - upper form (version 0.73) showing the email succesfully sent to target
2. screenshot-2.png - lower form (version 0.73) made visible when email succesfully sent to target
3. screenshot-3.png - upper form (version 0.75) showing the optional email verification field and the CAPTCHA fields
4. screenshot-4.png - none
4. screenshot-5.png - ecampaign settings screen, upper half
5. screenshot-6.png - ecampaign settings screen, lower half

== Changelog ==
= 0.81
* Bug fix: stop the form data being resaved (saved as formList in post metadata) when page accessed. The symptom is that many 'formUpdate' entries can been in log.
* Ability to download emails addresses in CSV and tabbed formats on admin/ecampaign log page.

= 0.80 = 
* Field definition has changed. Each field is wrapped in {}. 
* Mandatory fields no longer hard coded. Each field can be made mandatory by adding asterisk e.g. {subject*}
* Petition functionality added.
* Logging of petition signatures added
* Sidebar available showing ecampaign activity.  
* Log administration, logging of email activity and any exceptions added  
* Code refactoring to allow ecampaign class to be extended.
* Ecampaign extensions including uk/MP, uk/MSP and uk/Councillor to lookup up politicians using UK postcode.

= 0.77 = 
* CSS tweaked to make sure form(s) fill available width on IE.

= 0.76 = 
* Bug in 0.75 fixed which caused unnecessary rows of data (holding the counter data) to be added to the wp_postmeta table.
* Extra spaces removed from header of email sent back to campaign email address. 

= 0.75 =
* CAPTCHA functionality added.
* email verification added.
* counter functionality added.

= 0.74 =
* %body not shown in default template in 0.73, bug fixed. 
* Site visitor can be required to change text of message before sending.
* The size/width of each field can be specified in the templates, see settings page.
* The minimum number of chars keyed into each field can be specified in the templates.
* The CSS has been much simplified.
* Option variables now prefixed by ec-.  Old variables imported and deleted once.
* Outgoing messages word wrapped at 76 chars.
* JS in browser handles unexpected non JSON blocks e.g. PHP stack dumps which of course you will never see!
* use filter_vars() function (in PHP 5.2) only if available

= 0.73 =
* GPL2 copyright added. Contact details added. 
* Add %zipcode %city %state and other optional fields, 
* Add browser checks on US zipcode, UK postcode.
* Fixed referer field in email sent to campaignEmail.
* i18n string conversion added but not fully tested.

= 0.72 =
* Previous version didn't send email to target. Send suppressed to take screenshots!  

= 0.71 =
* Documentation changes and improvements only.

= 0.70 =
* First version

== Upgrade Notice ==
= 0.81 = 
* Bug fix. Upgrade recommended.

= 0.80 = 
* Upgrade to productions sites NOT recommended. Wait for next release.

= 0.77 =
* Upgrade recommended.

= 0.76 =
* Upgrade recommended to fix bug in 0.75.

= 0.75 =
* Upgrade recommended if ecampaign is installed but not being used in production.

= 0.74 =
* Upgrade recommended if ecampaign is installed but not being used in production.

= 0.73 =
* Small enhancements and some bug fixes, upgrade optional.

= 0.72 =
* Upgrade needed to send message to target, see Changelog 

= 0.71 =
* No code changes, upgrade not required.

= 0.7 =
* First version.



== Setting up a Campaign Action ==


Below there is an simple example of the text you could place on on a wordpress post or page to create an out-of-the-can campaign action.  

    [ecampaign targetEmail='parking.services@abcde.gov.uk,john.smith@abcde.gov.uk' targetSubject="Objection to Islington Council's proposals to introduce a Residents Roamer, and unlimited visitors vouchers (Ref. TMO/3176)" friendSubject="Roamer parking,  more traffic - please email Islington Council" campaignEmail='info@thecampaign.org.uk']
    Dear Sirs
    
    [Please customise this message and delete this text]

    Objection to Islington Council's proposals to introduce a Residents' Roamer, and unlimited visitors vouchers (Ref. TMO/3176)

    I am writing to object to the above changes in parking rules. I believe they will lead to an increase in traffic, and will worsen conditions for pedestrians, cyclists and buses, as well as leading to an increase in pollution and climate change emissions.

    In addition, in parts of the borough, the changes could result in there sometimes being insufficient parking bays available for local permit holders.

    I therefore call on the council to abandon the scheme.
    <hr />
    Hi friend,
    Please email Islington Council about the roamer parking scheme which will increase traffic.
    [/ecampaign]

More detail and onfiguration options is provided in the readme.html in the plugin directory. 
(Most recent version http://plugins.svn.wordpress.org/ecampaign/trunk/readme.html)

== Known restrictions, deficiencies and inflexibilty == 

* The site visitor has to have javascript enabled in their browser. There is no fallback mode.
* The activist only gets one email, a copy of their own email. There is no thank you email.
* The format of the email sent to campaignEmail is fixed.
* There is currently no Akismet protection but it could be added.
* Some error messages in the javascript are not easily translated.
* Emails sent to verify email addresses contain 4 digit codes which have to be retyped, it is 
not possible to simply click on a link in the email (which is usually offered).
* The verify code in the email must be entered and submitted before the captcha code because 
the captcha code can only be checked once, subsequent attempts will fail.  Not that ecampaign
itself does not start a session. However the securimage (captcha) does start a session.
* The counter value is stored in the metadata for the page and is therefore incremented 
by all the campaign actions on the same page (if there are more than one).

