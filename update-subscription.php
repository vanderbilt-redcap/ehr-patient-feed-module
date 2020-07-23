<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$module->updateSubscription($_POST['feed_id'], $_POST['status']);

echo 'success';