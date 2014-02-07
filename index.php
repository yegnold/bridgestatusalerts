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
		$request = $client->get('/bridge_status.shtml');
		// Send the request and get the response
		$response = $request->send();
	} catch(Guzzle\Http\Exception\BadResponseException $e) {
		/**
		 * If Guzzle throws a BadResponseException, we couldn't load the bridge status page...
		 */
		$output = '';
	    $output .=  'Could not get bridge status page - Uh oh! ' . $e->getMessage() . "\n";
	    $output .=  'HTTP request URL: ' . $e->getRequest()->getUrl() . "\n";
	    $output .=  'HTTP request: ' . $e->getRequest() . "\n";
	    $output .=  'HTTP response status: ' . $e->getResponse()->getStatusCode() . "\n";
	    $output .=  'HTTP response: ' . $e->getResponse() . "\n";
	    return $output;
	}

	/**
	 * I'm going to use the Symfony DomCrawler component to inspect the contents of the page
	 */	
	$crawler = new Symfony\Component\DomCrawler\Crawler($response->getBody(true));

	/**
	 * Right now, I'm going to determine if everything is OK based on whether or not
	 * the page contains two "status_green.gif" images contained with a td.status_content
	 *
	 * If the page layout/markup of the page ever changes, this logic will need to be updated!
	 */
	$number_of_green_statuses = $crawler->filter('td.status_content img[src$="status_green.gif"]');
	
	if(count($number_of_green_statuses) != 2) {
		$bridge_good = false;
	} else {
		$bridge_good = true;
	}

	// E-mail subject...
	$email_subject = ($bridge_good ? 'All seems to be OK with the Severn Bridge crossing.' : 'There is a possible problem with the Severn Bridge crossing');
	// E-mail plaintext contents...
	$email_plaintext_contents = ($bridge_good ? "All seems to be OK with the Severn Bridge crossing.\n\nDouble check: http://www.severnbridge.co.uk/bridge_status.shtml" : "There's possibly a problem with the Severn bridges!\n\nGo and have a look: http://www.severnbridge.co.uk/bridge_status.shtml");


//'';

	/**
	 * I'm going to mail the $return_message to defined recipients.
	 * Recipients can be defined in a file, bridge_alert_recipients.json
	 * Expected format is at bridge_alert_recipients.json.dist.
	 */
	$recipients = json_decode(file_get_contents(__DIR__.'/bridge_alert_recipients.json'));
	
	/**
	 * I'm going to use Swiftmailer to build an e-mail messsage
	 */

	// Create the message
	$message = Swift_Message::newInstance()->setSubject($email_subject)
	->setFrom(array('noreply@edwardyarnold.co.uk' => 'Ed\'s Bridge Status Service'))
	->setTo($recipients)
	->setBody($email_plaintext_contents);

	// Create the Transport
	$transport = Swift_MailTransport::newInstance();
	$mailer = Swift_Mailer::newInstance($transport);

	$result = $mailer->send($message);

	return $return_message;
	
});

$app->run();