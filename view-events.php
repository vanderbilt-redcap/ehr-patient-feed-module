<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$title = 'View Posted Events';
require_once __DIR__ . '/header.php';

$feedId = $_GET['feed-id'];

?>

<style>
   #ehr-patient-feed-container textarea{
       width: 525px;
       height: 200px;
   }
</style>

<p>Viewing events for feed <?=$feedId?>.</p>
<br>

<table></table>

<script>
    $(function(){
        var container = $('#ehr-patient-feed-container')

        var table = container.find('table').DataTable({
            pageLength: 100,
            ordering: false,
            searching: false,
            serverSide: true,
            ajax: {
                url: <?=json_encode($module->getUrl('get-event-logs.php') . "&feed-id=$feedId")?>
            },
            columns: [
                {
                    data: 'log_id',
                    title: 'Log ID'
                },
                {
                    data: 'timestamp',
                    title: 'Date/Time'
                },
                {
                    data: 'content',
                    title: 'POST Content'
                },
                {
                    data: 'mrn',
                    title: 'Resolved MRN'
                }
            ]
        })
    })
</script>

<?php

require_once __DIR__ . '/footer.php';