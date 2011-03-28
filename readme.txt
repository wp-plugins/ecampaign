=== Plugin Name ===
Contributors: john ackers
Donate link: 
Tags: advocacy, activism, email, ecampaigning
Requires at least: 3.0.1
Tested up to: 3.1
Stable tag: trunk

A plugin that allows a simple email based campaign action to be embedded 
into any wordpress page or post. 

== Description ==

This plugin allows a campaign action to be embedded into any wordpress page 
or post. The sequence of events is:

1. The site visitor is presented with a prepared email.
2. The visitor adds their name, email address, postal address etc. and can customize 
the text of the email.
3. The visitor clicks on 'Send email'.
4. The email is sent to the target email address(s) and copied to the visitor's address. 
5. An extended version of the email that includes the referer, the visitors IP address and details 
of the checked boxes is sent to the campaign email address. 
6. A hidden form is revealed which encourages the visitor to send a prepared email 
to one or more friends.
7. The visitor adds email addresses and clicks on 'Send email to friends'. 


= Configurable Options = 

The site administrator can:

* change the campaign email address.
* change the size of the message area.
* change which fields appear in the form and their size.
* enable 1 or 2 additional checkboxes (e.g. to prompt the visitor for 
further campaign updates).
* enable DNS checking of email addresses.

= Features =

* Apart from the plugin options, for good or bad, no data is stored in the wordpress database. 
* A simple test mode that diverts emails from the target address to the campaign email address.
* There is only one block of content embedded into one page and user interaction is via AJAX.
* Fields can be added/removed/rearranged and size changed. 
* Email addresses, zipcodes and UK postcodes are validated.
* Appearance customizable via CSS.
* Site visitor can be required to edit message, by removing optional guidance notes, before sending.
* All available error messages are passed back to the visitor.
* Email addresses displayed are antispammed.
* I18n language translation support for server side messages.


== Installation ==

1. Install using 'Plugin, add new" or upload ecampaign.zip to the server and unzip in `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure under admin >> Settings >> Ecampaign or got to /wp-admin/options-general.php?page=ecampaign
4. Create a page that contains [ecampaign] and [/ecampaign] tags, see Setting up a campaign action
5. View that page. The supplied style sheet works for the Atahualpa theme. 
For other themes you may need to change the padding and the fonts etc. If the default layout doesn't
match the screen shots, check the layout in Ecampaign settings especially after upgrades. 

PHP5 is required. There are no dependencies. filter_vars() from PHP5.2+ is used if available.

Anyone that is able to create/edit pages can create new campaign actions by creating 
a new post or page and embedding the default text of the email along with the 
default subject of the email,  the target address and if necessary override the 
campaign email address.

Mail is sent directly via the PHPMailer class because it provides access to 
error messages which aren't available through the wordpress api via wp_mail.
Email addresses that appear on screen and in the html are broken up 
to make it slightly more difficult for spammers.

If you are using the SMTP transport option offered by PHPMailer, the 
SMTP parameters must be configured either in php.ini or, for developement
or testing, directly in the top of wp-includes/class-phpmailer.php.

= Upgrading from 0.73 = 

The default form template in 0.73 did not show the body of the message.
because the word %body had merged into another word. The default has been fixed and will work
on new installs. However if you are upgrading, that change has to be made manually by 
going to ecampaign settings and comparing the default template (for 0.74) and the template
you are currently using and may have edited and corrected it if necessary.  

== Frequently Asked Questions ==

None yet.

== Screenshots ==

1. screenshot-1.png - upper block showing the email succesfully sent to target
2. screenshot-2.png - lower block made visible when email succesfully sent to target

== Changelog ==

= 0.74 =
* %body not shown in default template in 0.73, bug fixed. 
* Site visitor can be required to change text of message before sending
* The size/width of each field can be specified in layout, see settings page
* The minimum number of chars keyed into each field can be specified in layout
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

= 0.74 =
* Upgrade recommended if ecampaign is live but not being used.

= 0.73 =
* Small enhancements and some bug fixes, upgrade optional.

= 0.72 =
* Upgrade needed to send message to target, see Changelog 

= 0.71 =
* No code changes, upgrade not required.

= 0.7 =
* First version.



== Setting up a Campaign Action ==


Below there is an example of the text you should place on on a wordpress post or page.  

    [ecampaign targetEmail='parking.services@abcde.gov.uk,john.smith@abcde.gov.uk' targetSubject="Objection to Islington Council's proposals to introduce a Residents Roamer, and unlimited visitors vouchers (Ref. TMO/3176)" friendSubject="Roamer parking,  more traffic - please email Islington Council" campaignEmail='info@thecampaign.org.uk']
    Dear Sirs
    
    [Please customise this message and delete this text]

    Objection to Islington Council's proposals to introduce a Residents' Roamer, and unlimited visitors vouchers (Ref. TMO/3176)

    I am writing to object to the above changes in parking rules. I believe they will lead to an increase in traffic, and will worsen conditions for pedestrians, cyclists and buses, as well as leading to an increase in pollution and climate change emissions.

    In addition, in parts of the borough, the changes could result in there sometimes being insufficient parking bays available for local permit holders.

    I therefore call on Islington Council to abandon the scheme.
    <hr />
    Hi friend,
    Please email Islington Council about the roamer parking scheme which will increase traffic.
    [/ecampaign]

Note that between [ecampaign] and [/ecampaign], there are the bodies of two emails, separated by 

    <hr/>.

The second message is hidden until the first message is sent. The site visitor cannot 
send an email until all guidance notes wrapped in square brackets is removed. 
New lines are not permitted inside the [ecampaign  ] shortcode.

== Known restrictions, deficiencies and inflexibilty == 

* The activist only gets a copy of their email. There is no thank you email.
* The format of the email sent to campaignEmail is fixed.
* All fields included in the form layout are mandatory. 
* There is currently no CAPTCHA or Akismet protection but it can be added.
* The visitor's email address is not verified by sending an email.
* Some error messages in js are not easily translated.

