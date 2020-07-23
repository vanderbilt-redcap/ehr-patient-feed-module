<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>

<style>
    #ehr-patient-feed-container > .projhdr{
        margin-bottom: 25px;
    }

    #ehr-patient-feed-container,
    #ehr-patient-feed-container table{
        width: 800px;
    }

    #ehr-patient-feed-container > a > button{
        margin-top: 20px;
        margin-bottom: 20px;
    }

    #ehr-patient-feed-container table{
        border: 1px solid #d0d0d0;
    }

    #ehr-patient-feed-container th{
        border-bottom: 1px solid #d0d0d0;
    }

    #ehr-patient-feed-container table button{
        margin: 3px;
        width: 100%;
    }

    #ehr-patient-feed-container .modal.edit-feed textarea{
        width: 100%;
        height: 200px;
        margin-top: 5px;
    }
</style>

<div id='ehr-patient-feed-container'>

<div class="projhdr"><?=$module->getModuleName()?> - <?=$title?></div>

<?php

$module->initializeJavascriptModuleObject();