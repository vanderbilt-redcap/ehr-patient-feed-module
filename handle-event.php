<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$module->log(EVENT_POSTED, [
    'feed_id' => $_GET['feed-id'],
    'content' => file_get_contents("php://input")
]);

header('Content-Type: application/soap+xml; charset=utf-8');

?>
<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope" xmlns:urn="urn:epic-com:Events.2010.Services.Notification">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:ProcessEventResponse/>
   </soapenv:Body>
</soapenv:Envelope>