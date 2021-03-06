<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();
$app['debug']  = true;

/**
 * Accessing the app will trigger a check of the bridge status and trigger an e-mail if required
 */
$app->get('/', function () {

	/**
	 * The bridge status is displayed on the page below:
	 * http://www.severnbridge.co.uk/bridge_status.shtml
	 * So the first thing we need to do is to load the contents of the page...
	 */
	try {
		$client = new Guzzle\Http\Client('http://www.severnbridge.co.uk');
		// Create a request
		$request = $client->get('/status.html');
		// Send the request and get the response
		$response = $request->send();
	} catch(Guzzle\Http\Exception\BadResponseException $e) {
		/**
		 * If Guzzle throws a BadResponseException, we couldn't load the bridge status page...
		 */
		$error_details = '';
	    $error_details .=  'Could not get bridge status page - Uh oh! ' . $e->getMessage() . "\n";
	    $error_details .=  'HTTP request URL: ' . $e->getRequest()->getUrl() . "\n";
	    $error_details .=  'HTTP request: ' . $e->getRequest() . "\n";
	    $error_details .=  'HTTP response status: ' . $e->getResponse()->getStatusCode() . "\n";
	    
	}

	if(!isset($error_details)) {
		/**
		 * I'm going to use the Symfony DomCrawler component to inspect the contents of the page
		 */	
		$crawler = new Symfony\Component\DomCrawler\Crawler($response->getBody(true));

		/**
		 * Right now, I'm going to determine if everything is OK based on whether or not
		 * the page contains two "green.png" images contained within #main .status_container .status_image divs
		 *
		 * If the page layout/markup of the page ever changes, this logic will need to be updated!
		 */
		$number_of_green_statuses = $crawler->filter('#main .status_container .status_image img[src$="green.png"]');
	} else {
		$number_of_green_statuses = 0;
	}
	
	if(count($number_of_green_statuses) != 2) {
		$bridge_good = false;
	} else {
		$bridge_good = true;
	}

	// E-mail subject...
	$email_subject = ($bridge_good ? 'Bridge Status '.date('j F Y H:i').' - All seems to be OK with the Severn Bridge crossing.' : 'Bridge Status '.date('j F Y H:i').' - There is a possible problem with the Severn Bridge crossing');
	// E-mail plaintext contents...
	$email_plaintext_contents = ($bridge_good ? "All seems to be OK with the Severn Bridge crossing.\n\nDouble check: http://www.severnbridge.co.uk/status.html" : "There's possibly a problem with the Severn bridges!\n\nGo and have a look: http://www.severnbridge.co.uk/bridge_status.shtml");

	if(isset($error_details) && strlen($error_details)) {
		$email_plaintext_contents .= "\n\n".$error_details;
	}

	/**
	 * I'm going to mail the $return_message to defined recipients.
	 * Recipients can be defined in a file, bridge_alert_recipients.json
	 * Expected format is at bridge_alert_recipients.json.dist.
	 */
	$recipients = json_decode(file_get_contents(__DIR__.'/bridge_alert_recipients.json'));
	
	/**
	 * I'm going to use Swiftmailer to build and send the e-mail messsage
	 */

	// Create the Transport
	$transport = Swift_MailTransport::newInstance();
	$mailer = Swift_Mailer::newInstance($transport);

	foreach($recipients as $recipient_email => $recipient_name) {
		// Create the message
		$message = Swift_Message::newInstance()->setSubject($email_subject)
		->setFrom(array('noreply@edwardyarnold.co.uk' => 'Ed\'s Bridge Status Service'))
		->setTo(array($recipient_email => $recipient_name))
		->setBody($email_plaintext_contents);


		$result = $mailer->send($message);
	}

	return $email_plaintext_contents;
	
});

$app->run();