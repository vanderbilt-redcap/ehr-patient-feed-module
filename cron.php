<?php

// Required for FhirEhr::getPatientIdFromMrnWebService() to get a FHIR access token when testing via the browser.
global $userid;
$userid = null;

$module->cron();