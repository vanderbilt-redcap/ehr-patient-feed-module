<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

use Exception;

const SUBSCRIBED = 'subscribed';
const UNSUBSCRIBED = 'unsubscribed';
const FEED_SETTINGS_UPDATED = 'Feed settings updated';
const FEED_SUBSCRIPTION_UPDATED = 'Feed subscription updated';
const EVENT_POSTED = 'Event posted';
const LAST_PROCESSED_LOG_ID = 'last-processed-log-id';
const CONNECTION_SETTING_KEYS = [
    'epic-interconnect-url',
    'epic-client-id',
    'epic-username',
    'epic-password',
];

class EHRPatientFeedExternalModule extends \ExternalModules\AbstractExternalModule
{    
    function cron(){
        try{
            $this->epicConnectionInfo = $this->getEpicConnectionInfo();
            if(count($this->epicConnectionInfo) !== count(CONNECTION_SETTING_KEYS)){
                // The cron won't run until all the credentials are entered.
                return;
            }
    
            $lastProcessedLogId = $this->getSystemSetting(LAST_PROCESSED_LOG_ID);
            if($lastProcessedLogId === null){
                $lastProcessedLogId = 0;
            }
    
            $result = $this->queryLogs('
                select log_id, feed_id, content
                where message = ?
                and log_id > ?
                order by log_id asc
            ', [EVENT_POSTED, $lastProcessedLogId]);
    
            $startTime = time();
            while($log = $result->fetch_assoc()){
                try{
                    $this->processEvent($log);
                }
                catch(Exception $e){
                    throw new Exception("An error occurred on log {$log['log_id']}!", 0, $e);
                }
                finally{
                    $this->setSystemSetting(LAST_PROCESSED_LOG_ID, $log['log_id']);
                }
    
                $elapsedSeconds = time() - $startTime;
                if($elapsedSeconds > 60){
                    throw new Exception('Events are being logged faster than they can be processed!');
                }
            }
        }
        catch(\Throwable $t){
            if(SERVER_NAME === 'redcap.vanderbilt.edu'){
                $message = str_replace("\n", "<br>", $t->__toString());
                $this->sendErrorEmail($message);
            }
            else{
                throw $t;
            }
        }
    }

    private function sendErrorEmail($message){
        $to = 'mark.mcever@vumc.org';
        $from = $GLOBALS['from_email'];
        $subject = $this->getModuleName() . ' Module Error';
        
        \REDCap::email($to, $from, $subject, $message);
    }

    function processEvent($log){
        $content = $log['content'];
        if(empty($content)){
            // Just continue.  This might have been someone testing a feed url in a browser.
            return;
        }

        $mrn = $this->getMRNForPostContent($content);

        $projectIds = $this->getProjectIdsForFeedId($log['feed_id']);
        foreach($projectIds as $projectId){
            list($recordIdFieldName, $mrnFieldName) = $this->getCachedFieldNames($projectId);
            if(empty($mrnFieldName)){
                // We can't process events if this setting isn't set.
                continue;
            }

            $existingRecord = @json_decode(\REDCap::getData($projectId, 'json', null, $recordIdFieldName, null, null, false, false, false, "[$mrnFieldName] = '$mrn'" ), true)[0];
            if($existingRecord){
                // This MRN has already been added
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

            $result = \REDCap::saveData(
                $projectId,
                'json',
                json_encode([$data]),
                'normal',
                'YMD',
                'flat',
                null,
                true,
                true,
                true,
                false,
                true,
                [],
                false,
                true,
                false,
                $autoNumbering
            );

            if(!empty($result['errors']) || !empty($result['warnings'])){
                throw new Exception("Error calling saveData(): " . json_encode($result, JSON_PRETTY_PRINT));
            }
        }
    }

    function getExternalIDFromPostContent($content){
        $xml = simplexml_load_string($content);
        if($xml === false){
            throw new Exception("The posted XML was not valid: $content");
        }
    
        $primaryEntity = $xml->primaryEntity;
        if($primaryEntity){
            // We only saw three requests in this format on the original ED SOA test project.
            $id = $primaryEntity->id->__toString();
        }
        else{
            // We saw 27k requests in this format on the original test project.
            $namespace = reset($xml->getNamespaces()); // We've seem two different namespaces used.
            $primaryEntity = $xml->children($namespace)->Body->children('')->ProcessEvent->eventInfo->PrimaryEntity;
            $id = $primaryEntity->ID->__toString();
        }
    
        return trim($id);
    }

    private function getCachedFieldNames($projectId){
        $cachedFields = @$this->fieldCache[$projectId];
        if($cachedFields === null){
            $cachedFields = $this->fieldCache[$projectId] = [
                $this->getRecordIdField($projectId),
                $this->getProjectSetting('mrn-field-name', $projectId),
            ];
        }

        return $cachedFields;
    }

    private function getProjectIdsForFeedId($feedId){
        $subscriptions = $this->getFeedSubscriptions('and feed_id = ?', [$feedId]);
        return array_column($subscriptions, 'project_id');
    }

    function getMRNForPostContent($content){
        $externalId = $this->getExternalIDFromPostContent($content);

        $cacheSettingKey = "Cached MRN for $externalId";
        $cachedMRN = $this->getSystemSetting($cacheSettingKey);
        if($cachedMRN !== null){
            return $cachedMRN;
        }

        list($url, $clientId, $username, $password) = $this->epicConnectionInfo;

        $url .= '/api/epic/2015/Common/Patient/GetPatientIdentifiers/Patient/Identifiers';

        $client = new \GuzzleHttp\Client([
            'headers' =>  [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' .  base64_encode("emp\$$username:$password"),
                'Epic-Client-ID' => $clientId,
            ]
        ]);

        $params = [
            'form_params' => [
                'PatientID' => $externalId,
                'PatientIDType' => 'External',
                'UserID' => $username,
                'UserIDType' => 'External'
            ]
        ];

        $response = $client->request('POST', $url, $params);
        $data = json_decode($response->getBody(), true);

        foreach($data['Identifiers'] as $identifier){
            if($identifier['IDType'] === 'MRN'){
                $mrn = $identifier['ID'];
            }
        }
        
        if(strlen($mrn) !== 9 || !ctype_digit($mrn)){
            throw new Exception("Error looking up the MRN for $externalId");
        }

        $this->setSystemSetting($cacheSettingKey, $mrn);

        return $mrn;
    }

    private function getEpicConnectionInfo(){
        $epicConnectionInfo = [];

        foreach(CONNECTION_SETTING_KEYS as $key){
            $value = $this->getSystemSetting($key);
            if(!empty($value)){
                $epicConnectionInfo[] = $value;
            }
        }

        return $epicConnectionInfo;
    }

    function getLatestLogIdsForEachFeed($message, $additionalClauses = ''){
        $result = $this->queryLogs("
            select max(log_id) as log_id, feed_id, project_id
            where
                message = ?
                $additionalClauses
            group by feed_id, project_id
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
        $subscriptions = $this->getFeedSubscriptions();
        return $this->getFeeds(array_column($subscriptions, 'feed_id'));
    }

    private function getFeedSubscriptions($additionalClauses = '', $parameters = []){
        // The value of $_GET['pid'] will control whether queryLogs() under the hood
        // returns feeds for the current project or all projects
        $latestSubscriptionLogIds = $this->getLatestLogIdsForEachFeed(FEED_SUBSCRIPTION_UPDATED);
        
        if(empty($latestSubscriptionLogIds)){
            return [];
        }

        array_unshift($parameters, SUBSCRIBED);
        
        $result = $this->queryLogs("
            select feed_id, status, project_id
            where
                log_id in (" . implode(',', $latestSubscriptionLogIds) . ")
                and status = ?
                $additionalClauses
        ", $parameters);
        

        $subscriptions = [];
        while($row = $result->fetch_assoc()){
            $subscriptions[] = $row;
        }

        return $subscriptions;
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