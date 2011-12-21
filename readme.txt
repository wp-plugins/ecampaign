=== ecampaign ===
Contributors: john ackers
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=john%2eackers%40ymail%2ecom&lc=GB&item_name=John%20Ackers&currency_code=GBP&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: advocacy, activism, email, petition, ecampaign
Requires at least: 3.0.1
Tested up to: 3.3
Stable tag: 0.83

Allows a simple petition or email based campaign action to be embedded 
into any wordpress page or post.

== Description ==

This plugin allows a campaign action to be embedded into any wordpress page 
or post. The supported sequence of events is:

1. The site visitor views the form containing the petition body or an email message.
2. The visitor enters his/her name, email address, postal address etc. and can or may be required to customize 
the text of the email.
3. The visitor clicks on 'Sign' or 'Send'.
4. If enabled, the site visitor receives an email containing a verification code, which must be rekeyed into the form 
5a. If the site visitor is sending as email, an email is sent to the target email address(s) and copied to the visitor's email address. 
5b. If the site visitor has signing a petition, a confirmation email is sent to him/her.
6. An email that includes the referer, the visitors IP address and all keyed data is sent to the campaign email address. 
7. A normally hidden form, which can contain social media buttons e.g. the http://www.addthis.com/ bar, is revealed to encourage sharing 
or to send a prepared email to one or more friends.  

= Features =

* Site visitors can be be automatically registered as wordpress users.
* Site visitors details can exported as CSV file.
* Site visitors that opt-in can be subscribed to PHPList http://www.phplist.com/.
* Petition signatures and all other activity/errors/exceptions logged.
* Optional CAPTCHA support using http://www.phpcaptcha.org/.
* No bulky pages, users sees just one page, all interaction is via AJAX.
* Email addresses, zipcodes and UK postcodes are client side validated.
* Optional verification of site visitor's email address.
* Site visitor can be required to change the body of outgoing message before sending.
* Test mode prevents emails being sent to the the target address accidentally.
* Standard and custom fields can be added/removed/rearranged/resized changed.
* The template for a form can be embeddded in each wordpress post, allowing every campaign action 
to have a different form or the editable site wide templates can be used. 
* Most error messages are returned to the visitor, all are logged.
* Log entries are paged, filtered and can be deleted.
* I18n language translation support for server side messages.
* Extensions to look up email addresses for UK MPs, MSPs and councillors.


= Configurable Options = 

The site administrator can:

* add/resize/remove/modify input fields and checkboxes for the templates for the 3 forms
* add/override the attributes of the INPUT and TEXTAREA elements
* edit templates for the email address verfication and confirmation emails.
* enable DNS checking of email addresses.


== Installation ==

1. Follow the [standard installation procedure for WordPress plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).
2. Configure under admin >> Settings >> Ecampaign or got to /wp-admin/options-general.php?page=ecampaign
3. Create a page that contains [ecampaign] and [/ecampaign] tags, see Setting up a campaign action
4. View that page. The supplied style sheet works for the Atahualpa theme. 
For other themes you may need to change the padding and the fonts etc. If the default template doesn't
match the screen shots, check the template in Ecampaign settings especially after upgrades. 

If you want to use CAPTCHA, download securimage from http://www.phpcaptcha.org/
and install somewehere under the plugins directory and change the ecampaign 
settings to match that location. The default location is under the ecampaign directory. 
Versions of securimage that have been tested with ecampaign are 2.0 beta 
and 1.02 packaged with si-contact-form. 

If you want to use PHPList, PHPList needs to be installed ideally on the same server. Take a look at the configuration notes in the readme.html
packaged with the release. 

Note: If you are using the SMTP transport option offered by PHPMailer, the 
SMTP parameters must be configured either in php.ini or, for developement
or testing, directly in the top of wp-includes/class-phpmailer.php.

= Upgrading from 0.82 = 
Minor upgrade. 

= Upgrading from 0.81 = 
Minor upgrade. 

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

= How do you download email addresses in CSV format =

1. Go to the dashboard > Tools > ecampaign log (or by wp-admin/tools.php?page=ecampaign-log)
2. Click on 'view all actions'
3. select 'send' or 'sign' 
4. select 'filter'
5. click on CSV

== Screenshots ==

1. screenshot-1.png - upper form (version 0.73) showing the email succesfully sent to target
2. screenshot-2.png - lower form (version 0.73) made visible when email succesfully sent to target
3. screenshot-3.png - upper form (version 0.75) showing the optional email verification field and the CAPTCHA fields
4. screenshot-4.png - none
4. screenshot-5.png - ecampaign settings screen, upper half
5. screenshot-6.png - ecampaign settings screen, lower half

== Changelog ==

= 0.83 =
* Site visitors can be be automatically registered as wordpress users.
* Custom text and checkbox fields supported
* Widget will now display all campaign actions if post id not specified.
* Improved ecampaign log view, all field search added.
* public and admin js and css separated.

= 0.82 =
* Bug fix: remove quote around downloaded CSV/tabbed filename.
* Ability to subscribe site visitors to PHPList (see readme.html in the release).
 
= 0.81 =
* Bug fix: stop the form data being resaved (saved as formList in post metadata) when page accessed. The symptom is that many 'formUpdate' entries can be seen in the log.
* Ability to download emails addresses in CSV and tabbed formats on admin/ecampaign log page.

= 0.80 = 
* Field definition has changed. Each field is wrapped in { }. 
* Mandatory fields no longer hard coded. Each field can be made mandatory by adding asterisk e.g. {subject*}.
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
= 0.83 = 
* Upgrade not required.

= 0.82 = 
* Upgrade not required.

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

== For More Information ==

Detail configuration options and notes are maintained in the readme.html in the released package. 
The most recent version is at http://plugins.svn.wordpress.org/ecampaign/trunk/readme.html

