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
The vistor can add their own details (configurable), optionally modify the 
text of the email and send it.
The email is sent to the target email address and copied to their own 
email address. The details of the site visitor, including IP address 
and email message are  sent to the campaign email address.   

The site administrator can change the campaign email address, the form layout 
and select mandatory fields and additional checkboxes (to prompt the user for 
further campaign updates). No data is stored on the site database. There is 
a test mode that diverts emails from the target address to 
 
The page editor can create new campaign actions by creating a new post 
and embedding the default text of the email alonf with the default subject 
of the email,  the target address and if necessary override the campaign 
address.

The site visitor is also prompted to send a prepared but modifiable 
email to his/her friends.

There is only one user facing page and and user interaction is via ajax.

Mail is not sent via the PHPMailer class because it provides access to 
error messages which aren't available through wp_mail.
Email addresses that appear on screen and in the html are broken up 
to make it slightly more difficult for spammers.

There is currently no CAPTCHA protection.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `ecampaign.*` to the `/wp-content/plugins/ecampaign/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Create a page that contains [ecampaign] and [/ecampaign] tags.

== Frequently Asked Questions ==



== Screenshots ==

1. `/tags/4.3/screen1.png` 
2. `/tags/4.3/screen2.png` 

== Changelog ==

= 0.1 =
* First version

== Upgrade Notice ==


== Configuration ==

Usage

Below there is an example of the text you should place on on a wordpress post or page.  

[ecampaign targetEmail='parking.services@abcde.gov.uk,john.smith@abcde.gov.uk' targetSubject="Objection to Islington Council’s proposals to introduce a Residents’ Roamer, and unlimited visitors’ vouchers (Ref. TMO/3176)" friendSubject="Roamer parking,  more traffic - please email Islington Council" campaignEmail='info@thecampaign.org.uk']
Dear Sirs

Objection to Islington Council’s proposals to introduce a Residents’ Roamer, and unlimited visitors’ vouchers (Ref. TMO/3176)

I am writing to object to the above changes in parking rules. I believe they will lead to an increase in traffic, and will worsen conditions for pedestrians, cyclists and buses, as well as leading to an increase in pollution and climate change emissions.

In addition, in parts of the borough, the changes could result in there sometimes being insufficient parking bays available for local permit holders.

I therefore call on Islington Council to abandon the scheme.
<hr />
Please email Islington Council about the roamer parking scheme which will increase traffic.
[/ecampaign]

Note that between the [ecampaign] tags, there are two messages separated by <hr/>.  The second message is hidden 
until the first message is sent. New lines are not permitted inside the [ecampaign  ] tag.
