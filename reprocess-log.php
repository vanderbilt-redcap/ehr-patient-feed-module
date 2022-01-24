<?php

$result = $module->queryLogs('
    select log_id, feed_id, content
    where log_id = ?
', $_GET['id']);

$log = $result->fetch_assoc();
if($log === null){
    throw new Exception('Log not found!');
}

$module->processEvent($log);

echo "Log reprocessed successfully";