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
or post. The plugin presents the site visitor with a prepared email.
The vistor can add their own details, email address etc., optionally modify the 
text of the email and send it.
The email is sent to the target email address and copied to their own 
email address. The details of the site visitor, including IP address 
and email message are sent to the campaign email address.   

The site administrator can change the campaign email address, the form layout 
and select mandatory fields and additional checkboxes (to prompt the user for 
further campaign updates). No data is stored on the site database. There is 
a test mode that diverts emails from the target address to the campaign addreess.
 
The page editor can create new campaign actions by creating a new post or page
and embedding the default text of the email along with the default subject 
of the email,  the target address and if necessary override the campaign 
address.

The site visitor is also prompted to send a prepared but modifiable 
email to his/her friends.

There is only one user facing page and and user interaction is via ajax.

Mail is sent directly via the PHPMailer class because it provides access to 
error messages which aren't available through wp_mail.
Email addresses that appear on screen and in the html are broken up 
to make it slightly more difficult for spammers.

There is currently no CAPTCHA protection.

== Installation ==

1. Install using 'Plugin, add new" or upload ecampaign.zip to the server and unzip in `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure under admin >> Settings >> Ecampaign or got to /wp-admin/options-general.php?page=ecampaign
4. Create a page that contains [ecampaign] and [/ecampaign] tags, see Setting up a campaign action

If you are using the SMTP transport option offered by PHPMailer, the 
SMTP parameters must be configured either in php.ini or, for developement
or testing, directly in the top of wp-includes/class-phpmailer.php.

== Frequently Asked Questions ==

None yet.

== Screenshots ==

1. screenshot-1.png - upper block showing the email succesfully sent to target
2. screenshot-2.png - lower block made visible when email succesfully sent to target

== Changelog ==

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

    Objection to Islington Council's proposals to introduce a Residents' Roamer, and unlimited visitors vouchers (Ref. TMO/3176)

    I am writing to object to the above changes in parking rules. I believe they will lead to an increase in traffic, and will worsen conditions for pedestrians, cyclists and buses, as well as leading to an increase in pollution and climate change emissions.

    In addition, in parts of the borough, the changes could result in there sometimes being insufficient parking bays available for local permit holders.

    I therefore call on Islington Council to abandon the scheme.
    <hr />
    Hi friend,
    Please email Islington Council about the roamer parking scheme which will increase traffic.
    [/ecampaign]

Note that between [ecampaign] and [/ecampaign], there are the bodies of two emails, separated by 

    <hr />.

The second message is hidden until the first message is sent. 
New lines are not permitted inside the [ecampaign  ] shortcode.
