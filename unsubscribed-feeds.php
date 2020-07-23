<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$title = 'Unsubscribed Feeds';
require_once __DIR__ . '/header.php';

?>

<p>These are other available feeds that your project is not currently subscribed to.</p>

<a href="<?=$module->getUrl('subscribed-feeds.php')?>">
    <button>Show Subscribed Feeds</button>
</a>

<?php

require_once __DIR__ . '/footer-feeds.php';