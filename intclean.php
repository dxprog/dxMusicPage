<?php

include('lib/aal.php');

$result = Lib\Db::Query('SELECT content_id, content_meta FROM content WHERE content_type = "song"');
while ($row = Lib\Db::Fetch($result)) {

	$row->meta = json_decode($row->content_meta);
	if (isset($row->meta->track)) {
		$row->meta->track = (int) $row->meta->track;
	}

	if (isset($row->meta->disc)) {
		$row->meta->disc = (int) $row->meta->disc;
	}

	if (isset($row->meta->year)) {
		$row->meta->year = (int) $row->meta->year;
	}

	$query = 'UPDATE content SET content_meta = :meta WHERE content_id = :id';
	$params = [ ':meta' => json_encode($row->meta), ':id' => (int) $row->content_id ];
	Lib\Db::Query($query, $params);

}