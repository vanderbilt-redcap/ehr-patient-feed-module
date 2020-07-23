<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$module->log(EVENT_POSTED, [
    'feed_id' => $_GET['feed-id'],
    'content' => file_get_contents("php://input")
]);

?>

<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:epic-com:Events.2010.Services.Notification">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:ProcessEventResponse/>
   </soapenv:Body>
</soapenv:Envelope>