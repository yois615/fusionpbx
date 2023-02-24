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
	Portions created by the Initial Developer are Copyright (C) 2018 - 2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (permission_exists('circle_survey_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

if (is_uuid($_REQUEST["id"])) {
	$circle_survey_uuid = $_REQUEST["id"];
	$id = $_REQUEST["id"];
}
else {
	echo "invalid request";
		exit;
}

//define functions
function array2csv(array &$array) {
	if (count($array) == 0) {
		return null;
	}
	ob_start();
	$df = fopen("php://output", 'w');
	fputcsv($df, array_keys(reset($array)));
	foreach ($array as $row) {
		fputcsv($df, $row);
	}
	fclose($df);
	return ob_get_clean();
}

function download_send_headers($filename) {
	// disable caching
	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");

	// force download
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");

	// disposition / encoding on response body
	header("Content-Disposition: attachment;filename={$filename}");
	header("Content-Transfer-Encoding: binary");
}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the http post data
	
		$action = $_POST['action'];
		$search = $_POST['search'];
		$circle_survey_uuid = $_GET['id'];

//process the http post data by action
	if ($action == 'delete' && permission_exists('circle_survey_delete')) {
		$sql = "DELETE FROM v_circle_survey_votes ";
		$sql .= "WHERE circle_survey_uuid = :circle_survey_uuid ";
		$sql .= "AND domain_uuid = :domain_uuid ";
		$parameters['circle_survey_uuid'] = $circle_survey_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	    $database = new database;
		$database->select($sql, $parameters, 'all');
	    unset($sql, $parameters);
		header('Location: circle_survey.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

	if ($_GET["action"] == "download") {
		$sql = "select v.sequence_id as \"Question\", v.vote as \"Vote\",  ";
		$sql .= "c.caller_id_name as \"Caller ID\", c.caller_id_number as \"Phone Number\" ";
		$sql .= "FROM v_circle_survey_votes v ";
		$sql .= "INNER JOIN v_circle_survey_customer c ";
		$sql .= "ON v.circle_survey_customer_uuid = c.circle_survey_customer_uuid ";
		$sql .= "WHERE v.circle_survey_uuid = :circle_survey_uuid ";
		$sql .= "AND v.domain_uuid = :domain_uuid ";
		$sql .= "ORDER BY sequence_id ASC ";
		$parameters['circle_survey_uuid'] = $circle_survey_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$database = new database;
		$vote_results = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		download_send_headers("votes_export_".date("Y-m-d").".csv");
		echo array2csv($vote_results);
		exit;
	}


//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];

//get the survey name
	$sql = "select name from v_circle_surveys ";
	$sql .= "WHERE circle_survey_uuid = :circle_survey_uuid ";
	$sql .= "AND domain_uuid = :domain_uuid ";
	$parameters['circle_survey_uuid'] = $circle_survey_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$survey_name = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//get the count
	$sql = "select count(DISTINCT circle_survey_customer_uuid) from v_circle_survey_votes ";
	$sql .= "WHERE circle_survey_uuid = :circle_survey_uuid ";
	$sql .= "AND domain_uuid = :domain_uuid ";
	$parameters['circle_survey_uuid'] = $circle_survey_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$num_rows = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//get the list
	$sql = "select AVG(vote) as vote_average, sequence_id FROM v_circle_survey_votes ";
	$sql .= "WHERE circle_survey_uuid = :circle_survey_uuid ";
	$sql .= "AND domain_uuid = :domain_uuid ";
	$sql .= "GROUP BY sequence_id ";
	$sql .= order_by($order_by, $order, 'sequence_id', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['circle_survey_uuid'] = $circle_survey_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$survey_results = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-circle-survey'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	// TODO Replace the below with the survey name
	echo "	<div class='heading'><b>".$survey_name." (".$num_rows.")\n</b></div>";
	echo "	<div class='actions'>\n";

	echo button::create(['type'=>'button','label'=>'Pick random winner','link'=>'circle_survey_winner.php?id='.$circle_survey_uuid]);

	if (permission_exists('circle_survey_edit')) {
		echo button::create(['type'=>'button','label'=>'Configure Survey','link'=>'circle_survey_edit.php?id='.$circle_survey_uuid]);
	}
	echo button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$_SESSION['theme']['button_icon_export'],'link'=>'circle_survey_votes.php?action=download&id='.$circle_survey_uuid]);
	
	if (permission_exists('circle_survey_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-circle-survey-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}

	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('circle_survey_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['title_description-survey']."\n";
	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	
	echo th_order_by('sequence_id', $text['label-circle_survey_sequence'], $order_by, $order);
	echo th_order_by('vote_average', $text['label-circle_survey_average'], $order_by, $order);
	echo "</tr>\n";

	if (is_array($survey_results) && @sizeof($survey_results) != 0) {
		$x = 0;
		foreach ($survey_results as $row) {		
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape($row['sequence_id'])."</td>\n";
            echo "	<td>".round(floatval($row['vote_average']), 3)."</td>\n";			
			echo "</tr>\n";
			$x++;
		}
		unset($vote_results);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
