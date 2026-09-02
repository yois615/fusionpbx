<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permisission
	if (permission_exists('call_center_queue_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

	if (!$settings->get("call_center", "use_modern_call_center", null)) {
		echo "not using modern callcenter";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set additional variables
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get http variables and set as php variables
	$from_stamp = $_REQUEST["from_stamp"];
	$from_stamp = ($from_stamp && $settings->get('domain', 'time_format') != '24h') ? DateTime::createFromFormat('Y-m-d h:i a', $from_stamp)->format('Y-m-d H:i') : $from_stamp;
	$from_stamp ??= date_format(date_create(), "Y-m-d 00:00");
	$to_stamp = $_REQUEST["to_stamp"];
	$to_stamp = ($to_stamp && $settings->get('domain', 'time_format') != '24h') ? DateTime::createFromFormat('Y-m-d h:i a', $to_stamp)->format('Y-m-d H:i') : $to_stamp;
	$to_stamp ??= date_format(date_create("+1 day"), "Y-m-d 00:00");
	$calls_order_by = $_GET["calls_order_by"] ?? '';
	$calls_order = $_GET["calls_order"] ?? '';
	$agents_order_by = $_GET["agents_order_by"] ?? '';
	$agents_order = $_GET["agents_order"] ?? '';
	$sort = null;

//set the time zone
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//add the search term
	$search = strtolower($_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_search = " (";
		$sql_search .= "lower(queue_name) like :search ";
		$sql_search .= "or lower(queue_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

	$sql = <<<EOF
		SELECT
		count(*) AS "total",
		count(nullif("me"."leave_reason" = 'bridged', FALSE)) AS "answered",
		count(nullif("me"."leave_reason" = 'abandoned', FALSE)) AS "abandoned",
		count(nullif("me"."leave_reason" = 'timed_out', FALSE)) AS "timed_out",
		coalesce(
			round(
				avg(
					extract(epoch FROM coalesce("cdr"."bridge_time", "me"."leave_time") - "me"."join_time")
				)
			),
		0) AS "avg_wait_time",
		"q"."queue_name"
		FROM
		"v_call_center_member_events" AS "me"
		LEFT JOIN "v_call_center_cdr" AS "cdr" ON "me"."id" = "cdr"."member_id" AND "cdr"."reason" = 'bridged'
		INNER JOIN "v_call_center_queues" AS "q" ON "me"."queue_id" = "q"."call_center_queue_uuid"
		WHERE q.domain_uuid = :domain_uuid
		AND (me.join_time AT TIME ZONE :time_zone) BETWEEN :from_stamp::timestamptz AND :to_stamp::timestamptz
		GROUP BY q.queue_name
EOF;

	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['from_stamp'] = $from_stamp.':00.000 '.$time_zone;
	$parameters['to_stamp'] = $to_stamp.':00.000 '.$time_zone;
	$parameters['time_zone'] = $time_zone;

//get the list
	$sql .= order_by($calls_order_by, $calls_order, 'queue_name', 'asc', $sort);
	$call_stats = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

$sql = <<<EOF
	WITH "cte" AS (
		SELECT
			"cdr"."agent_id",
			count(nullif("cdr"."reason" = 'bridged', FALSE)) AS "answered",
			count(nullif("cdr"."reason" IN ('busy', 'rejected'), FALSE)) AS "missed",
			coalesce(
				round(
					sum(
						extract(epoch FROM "me"."leave_time" - "cdr"."bridge_time")
					)
				),
			0) AS "in_call_time"
		FROM "v_call_center_cdr" AS "cdr"
		INNER JOIN "v_call_center_member_events" AS "me" ON "me"."id" = "cdr"."member_id"
		INNER JOIN "v_call_center_queues" AS "q" ON "me"."queue_id" = "q"."call_center_queue_uuid"
		WHERE q.domain_uuid = :domain_uuid
		AND (me.join_time AT TIME ZONE :time_zone) BETWEEN :from_stamp::timestamptz AND :to_stamp::timestamptz
		GROUP BY "cdr"."agent_id"
	)
	SELECT "a"."agent_name", "cte".*
	FROM "cte"
	LEFT JOIN "v_call_center_agents" AS "a" ON "a"."call_center_agent_uuid" = "cte"."agent_id"
EOF;

	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['from_stamp'] = $from_stamp.':00.000 '.$time_zone;
	$parameters['to_stamp'] = $to_stamp.':00.000 '.$time_zone;
	$parameters['time_zone'] = $time_zone;

//get the list
	$sql .= order_by($agents_order_by, $agents_order, 'agent_name', 'asc', $sort);
	$agent_stats = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//includes and title
	$document['title'] = $text['title-call_center_queues'];
	require_once "resources/header.php";

	function secToDuration(int $sec) {
		$min = floor($sec / 60);
		$sec -= $min * 60;
		$hours = floor($min / 60);
		$min -= $hours * 60;

		$str = $hours > 0 ? $hours." hour".($hours != 1 ? "s" : "")." " : "";
		$str .= $min > 0 ? $min." minute".($min != 1 ? "s" : "")." " : "";
		$str .= $sec." second".($sec != 1 ? "s" : "");
		return $str;
	}

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Call Center Stats</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','link'=>'call_center_queues.php']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	//echo $text['description-call_center_queues']."\n";
	//echo "<br /><br />\n";

	function build_href_params($from_stamp, $to_stamp, $calls_order_by, $calls_order, $agents_order_by, $agents_order) {
		$date_format = $GLOBALS['settings']->get('domain', 'time_format') == '24h' ? 'Y-m-d H:i' : 'Y-m-d h:i a';
		$from_stamp = is_string($from_stamp) ? date_create($from_stamp) : $from_stamp;
		$to_stamp = is_string($to_stamp) ? date_create($to_stamp) : $to_stamp;
		$query = "?from_stamp=".urlencode(date_format($from_stamp, $date_format));
		$query .= "&to_stamp=".urlencode(date_format($to_stamp, $date_format));
		$query .= "&calls_order_by=".urlencode($calls_order_by ?? '');
		$query .= "&calls_order=".urlencode($calls_order ?? '');
		$query .= "&agents_order_by=".urlencode($agents_order_by ?? '');
		$query .= "&agents_order=".urlencode($agents_order ?? '');
		return $query;
	}

	echo "<form name='frm' id='frm' method='get'>\n";
	echo "<div class='card'>\n";
	echo "	<div>\n";
	echo "		<div class='label'>Date range</div>\n";
	echo "		<div class='field no-wrap'>\n";
	echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#from_stamp' onblur=\"$(this).datetimepicker('hide');\" style='".($settings->get('domain', 'time_format') == '24h' ? 'min-width: 115px; width: 115px;' : 'min-width: 115px; width: 130px;')."' name='from_stamp' id='from_stamp' placeholder='".$text['label-from']."' value='".escape($from_stamp)."' autocomplete='off'>\n";
	echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#to_stamp' onblur=\"$(this).datetimepicker('hide');\" style='".($settings->get('domain', 'time_format') == '24h' ? 'min-width: 115px; width: 115px;' : 'min-width: 115px; width: 130px;')."' name='to_stamp' id='to_stamp' placeholder='".$text['label-to']."' value='".escape($to_stamp)."' autocomplete='off'>\n";
	echo "			<input type='hidden' name='calls_order_by value='".escape($calls_order_by ?? '')."'>";
	echo "			<input type='hidden' name='calls_order value='".escape($calls_order ?? '')."'>";
	echo "			<input type='hidden' name='agents_order_by value='".escape($agents_order_by ?? '')."'>";
	echo "			<input type='hidden' name='agents_order value='".escape($agents_order ?? '')."'>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_save','name'=>'submit']);
	echo "		</div>\n";
	echo "		<a href='".build_href_params(date_create('midnight'), "", $calls_order_by, $calls_order, $agents_order_by, $agents_order)."' style='margin-right: 5px;'>Today</a>";
	echo "		<a href='".build_href_params(date_create('sunday -1 week 00:00'), "", $calls_order_by, $calls_order, $agents_order_by, $agents_order)."' style='margin-right: 5px;'>This week</a>";
	echo "		<a href='".build_href_params(date_create('first day of 00:00'), "", $calls_order_by, $calls_order, $agents_order_by, $agents_order)."' style='margin-right: 5px;'>This month</a>";
	echo "	</div>\n";
	echo "</div>\n";
	echo "<br />\n";
	echo "</form>";

	// echo "<form id='form_list' method='post'>\n";
	// echo "<input type='hidden' id='action' name='action' value=''>\n";
	// echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<div class='card'>\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'queue_name', ($calls_order_by == 'queue_name' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Queue name</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'total', ($calls_order_by == 'total' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Total calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'answered', ($calls_order_by == 'answered' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Answered calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'abandoned', ($calls_order_by == 'abandoned' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Abandoned calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'timed_out', ($calls_order_by == 'timed_out' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Timed out calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, 'avg_wait_time', ($calls_order_by == 'avg_wait_time' && $calls_order == 'asc') ? 'desc' : 'asc', $agents_order_by, $agents_order)."'>Average wait time</a></th>";
	echo "</tr>\n";

	if (!empty($call_stats)) {
		$x = 0;
		foreach($call_stats as $row) {
			echo "<tr class='list-row'>\n";
			echo "	<td>".$row['queue_name']."</td>\n";
			echo "	<td>".number_format($row['total'], 0, '.', ',')."</td>\n";
			echo "	<td>".number_format($row['answered'], 0, '.', ',')."</td>\n";
			echo "	<td>".number_format($row['abandoned'], 0, '.', ',')."</td>\n";
			echo "	<td>".number_format($row['timed_out'], 0, '.', ',')."</td>\n";
			echo "	<td>".secToDuration($row['avg_wait_time'])."</td>\n";
			echo "</tr>\n";
			$x++;
		}
		unset($result);
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "<br />\n";

	echo "<div class='card'>\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, $calls_order_by, $calls_order, 'agent_name', ($agents_order_by == 'agent_name' && $agents_order == 'asc') ? 'desc' : 'asc')."'>Agent name</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, $calls_order_by, $calls_order, 'answered', ($agents_order_by == 'answered' && $agents_order == 'asc') ? 'desc' : 'asc')."'>Answered calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, $calls_order_by, $calls_order, 'missed', ($agents_order_by == 'missed' && $agents_order == 'asc') ? 'desc' : 'asc')."'>Missed calls</a></th>";
	echo "<th nowrap><a href='".build_href_params($from_stamp, $to_stamp, $calls_order_by, $calls_order, 'in_call_time', ($agents_order_by == 'in_call_time' && $agents_order == 'asc') ? 'desc' : 'asc')."'>Total in-call time</a></th>";
	echo "</tr>\n";

	if (!empty($agent_stats)) {
		$x = 0;
		foreach($agent_stats as $row) {
			echo "<tr class='list-row'>\n";
			echo "	<td>".$row['agent_name']."</td>\n";
			echo "	<td>".number_format($row['answered'], 0, '.', ',')."</td>\n";
			echo "	<td>".number_format($row['missed'], 0, '.', ',')."</td>\n";
			echo "	<td>".secToDuration($row['in_call_time'])."</td>\n";
			echo "</tr>\n";
			$x++;
		}
		unset($result);
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "<br />\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//show the footer
	require_once "resources/footer.php";

?>

