<?php

use const Vanderbilt\EHRPatientFeedExternalModule\EVENT_POSTED;

$offset = \db_real_escape_string($_GET['start']);
$limit = \db_real_escape_string($_GET['length']);
$limitClause = "limit $limit offset $offset";

$whereClause = "
	where project_id is null
	and message = ?
	and feed_id = ?
";

$whereParams = [EVENT_POSTED, $_GET['feed-id']];

$result = $module->queryLogs("select count(*) as count $whereClause", $whereParams);
$row = db_fetch_assoc($result);
$totalRowCount = $row['count'];

$results = $module->queryLogs("
	select log_id, timestamp, content
	$whereClause
	order by log_id desc
	$limitClause
", $whereParams); 

$rows = [];
while($row = $results->fetch_assoc()){
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
	$dom->loadXML($row['content']);
	$dom->formatOutput = true;

	$row['content'] = '<textarea>' . $dom->saveXML() . '</textarea>';
	$rows[] = $row;
}

?>

{
	"draw": <?=$_GET['draw']?>,
	"recordsTotal": <?=$totalRowCount?>,
	"recordsFiltered": <?=$totalRowCount?>,
	"data": <?=json_encode($rows)?>
}