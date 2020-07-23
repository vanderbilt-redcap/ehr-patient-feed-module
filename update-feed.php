<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

if(SUPER_USER !== '1'){
    header('Content-Type: application/json');
    die(json_encode(['message' => 'You do not have access to create or edit feeds because you are not a SUPER USER.']));
}

$feedId = $_POST['feed_id'];

$creatingFeed = false;
if(empty($feedId)){
    // We're creating a new feed.
    // Use the log ID as the feed ID.  It's a really simple way to ensure we have a unique ID.
    $feedId = $module->log('Getting next feed ID');
    $creatingFeed = true;
}

$module->updateDescription($feedId, $_POST['description']);

if($creatingFeed){
    $module->updateSubscription($feedId, SUBSCRIBED);
}

echo 'success';