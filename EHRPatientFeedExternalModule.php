<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

const SUBSCRIBED = 'subscribed';
const UNSUBSCRIBED = 'unsubscribed';
const FEED_SETTINGS_UPDATED = 'Feed settings updated';
const FEED_SUBSCRIPTION_UPDATED = 'Feed subscription updated';
const EVENT_POSTED = 'Event posted';
const LAST_PROCESSED_LOG_ID = 'last-processed-log-id';

class EHRPatientFeedExternalModule extends \ExternalModules\AbstractExternalModule
{    
    function cron(){
        // TODO - This method is partially pseudo-code and needs to be finalized.

        $lastProcessedLogId = $this->getSystemSetting(LAST_PROCESSED_LOG_ID);
        if($lastProcessedLogId === null){
            $lastProcessedLogId = 0;
        }

        $result = $this->queryLogs('select log_id, feed_id, content where log_id > ? and message = ? order by log_id asc', [$lastProcessedLogId, EVENT_POSTED]);
        while($log = $result->fetch_assoc()){
            $this->processEvent($log);
            $this->setSystemSetting(LAST_PROCESSED_LOG_ID, $log['log_id']);

            if($hasRunMoreThanAMinute()){
                $this->handleError();
                break;
            }
        }
    }

    function processEvent($log){
        // TODO - This method is partially pseudo-code and needs to be finalized.
        
        $content = $log['content'];
        $xml = simplexml_load_string($content);
        if($xml === false){
            $this->handleError_considerArgs();
            return;
        }
    
        $primaryEntity = $xml->primaryEntity;
        if($primaryEntity){
            // We only saw three requests in this format on the original test project.
            $id = $primaryEntity->id->__toString();
            $type = $primaryEntity->type->__toString();
            $numericId = $id;
        }
        else{
            // We saw 27k requests in this format on the original test project.
            $primaryEntity = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('')->ProcessEvent->eventInfo->PrimaryEntity;
            $id = $primaryEntity->ID->__toString();
            $type = $primaryEntity->Type->__toString();
    
            $firstLetter = $id[0];
            if(!in_array($firstLetter, ['Z', 'M'])){
                var_dump(['unknown ID starting letter ', $id, $content]);
                $this->handleError();
                return;
            }
    
            $numericId = ltrim($id, $firstLetter);
        }
    
        if(!ctype_digit($numericId)){
            var_dump(['not numeric ', $id, $content, $primaryEntity]);
            $this->handleError();
            return;
        }
    
        if($type !== 'EPT'){
            var_dump(['Unknown primary entity type found: ', $type, $content]);
            $this->handleError();
            return;
        }
        
        if(!$this->isMRNValid($mrn)){
            $this->handleError();
            return;
        }

        $projectIds = $this->getProjectIdsForFeedId($log['feed_id']);
        foreach($projectIds as $projectId){
            $recordIdFieldName = $this->getCachedRecordIdFieldName($projectId);
            $mrnFieldName = $this->getCachedMRNFieldName($projectId);
            
            $existingRecord = REDCap::getData_needToCheckargs($projectId, "[$mrnFieldName] = '$mrn'" );
            if($existingRecord){
                continue;
            }

            $data = [
                $mrnFieldName => $mrn
            ];

            $autoNumbering = false;
            if($recordIdFieldName !== $mrnFieldName){
                // The record ID is required, but doesn't really matter since autonumbering will overwrite it.
                $data[$recordIdFieldName] = 1;
                $autoNumbering = true;
            }

            $result = $this->saveData_check_args($projectId, 'json', json_encode([$data]), $autoNumbering);
            if(resultHasWarningsOrErrors()){
                $this->handleError();    
            }
        }
    }

    function handleError($message){
        $this->log_thinkAboutItMore($message);
        if(!$this->hasRecentError()){
            // email a link to an error log page (maybe build a log viewer page into the framework instead of this module!?!?!?)
        }
    }

    function getLatestLogIdsForEachFeed($message, $additionalClauses = ''){
        $result = $this->queryLogs("
            select max(log_id) as log_id, feed_id
            where
                message = ?
                $additionalClauses
            group by feed_id
        ", $message);

        $latestLogIds = [];
        while($row = $result->fetch_assoc()){
            $latestLogIds[] = $row['log_id'];
        }

        return $latestLogIds;
    }

    function getFeeds($includedFeedIds = null, $excludedFeedIds = []){
        // Feeds are defined at the system level, and are logged without a project_id.
        $nullProjectClause = ' and project_id is null ';

        $additionalClauses = $nullProjectClause;
        
        if($includedFeedIds !== null){
            if(empty($includedFeedIds)){
                return [];
            }

            $additionalClauses .= " and feed_id in (" . implode(',', $includedFeedIds) . ") ";
        }

        if(!empty($excludedFeedIds)){
            $additionalClauses .= " and feed_id not in (" . implode(',', $excludedFeedIds) . ") ";
        }

        $latestFeedSettingsLogIds = $this->getLatestLogIdsForEachFeed(FEED_SETTINGS_UPDATED, $additionalClauses);
        if(empty($latestFeedSettingsLogIds)){
            return [];
        }
        
        $result = $this->queryLogs("
            select feed_id, description
            where
                message = ?
                $nullProjectClause
                and log_id in (" . implode(',', $latestFeedSettingsLogIds) . ")
        ", FEED_SETTINGS_UPDATED);

        $pid = $this->getProjectId();

        $feeds = [];
        while($row = $result->fetch_assoc()){
            $feedId = $row['feed_id'];
            $url = $this->getUrl('handle-event.php', true, true) . "&feed-id=$feedId";
            $url = str_replace("&pid=$pid", '', $url);

            $feeds[] = [
                'feed_id' => $feedId,
                'description' => $row['description'],
                'url' => $url
            ];
        }

        return $feeds;
    }

    function getSubscribedFeedsForCurrentProject(){
        // The queryLogs() under the hood will automatically only return feed for the current project
        // (since no project_id logic is specified and $_GET['pid'] is set).
        $latestSubscriptionLogIds = $this->getLatestLogIdsForEachFeed(FEED_SUBSCRIPTION_UPDATED);

        if(empty($latestSubscriptionLogIds)){
            return [];
        }

        $result = $this->queryLogs("
            select feed_id, status
            where
                log_id in (" . implode(',', $latestSubscriptionLogIds) . ")
                and status = ?
        ", SUBSCRIBED);

        $subscribedFeedIds = [];
        while($row = $result->fetch_assoc()){
            $subscribedFeedIds[] = $row['feed_id'];
        }

        return $this->getFeeds($subscribedFeedIds);
    }

    function feedLog($feedId, $message, $params = []){
        $params['feed_id'] = $feedId;
        $this->log($message, $params);
    }

    function updateSubscription($feedId, $status){
        $this->feedLog($feedId, FEED_SUBSCRIPTION_UPDATED, [
            'status' => $status
            // Project ID will automatically be added
        ]);
    }

    function updateDescription($feedId, $description){
        $pid = $_GET['pid'];

        // Feeds are defined at the system level, and are logged without a project_id.
        unset($_GET['pid']);

        $this->feedLog($feedId, FEED_SETTINGS_UPDATED, [
            'description' => $description
        ]);

        // Restore the $pid in case it's used elsewhere
        $_GET['pid'] = $pid;
    }

    function isOnSubscribedPage(){
        return $_GET['page'] === 'subscribed-feeds';
    }
}