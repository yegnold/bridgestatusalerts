Bridge Status Alerts
====================

Intro
---------------------

My friend Craig travels across the Severn Estuary from Wales in to England to get to work.

He said it would be nice if he could receive e-mails telling him whether there are problems on the bridges.

This tool does that for him..

### How it works

At 0545, 1345, 1450 and 2250 it will check the contents of the page at the following URL:

http://www.severnbridge.co.uk/bridge_status.shtml

If the image "status_green.gif" does not appear in the document twice, this implies that one of the bridges
is suffering with problems of some kind, and an e-mail will be sent to Craig's address to tell him to check
the page.

#### Technical Details

I'm using "Guzzle" for HTTP requests.

I'm using the Symfony2 DOM Crawler component for inspecting the contents of the page.  I've also used the
Symfony2 CSS Selector component to make filtering the page contents easier.

I'm using swiftmailer to send emails.

Recipients of the alerts (at the time of writing, Craig only) are to be defined in json format in the file
bridge_alert_recipients.json.