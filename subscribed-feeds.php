<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$title = 'Subscribed Feeds';
require_once __DIR__ . '/header.php';

?>

<p>Subscribe to one or more feeds to automatically create records with the MRN as matching events occur for each patient.</p>

<a href="<?=$module->getUrl('unsubscribed-feeds.php')?>">
    <button>Show Unsubscribed Feeds</button>
</a>
<button class='edit-feed'>Create New Feed & Subscribe</button>

<?php

require_once __DIR__ . '/footer-feeds.php';