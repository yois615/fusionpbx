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
	if (permission_exists('circle_votes_view')) {
		//access granted
	}
	else {
		echo "access denied";
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
		$circle_votes = $_POST['circle_votes'];
	

//process the http post data by action
	if ($action == 'delete' && permission_exists('circle_votes_delete')) {
		$sql = "DELETE FROM circle_tt_votes ";
	    $database = new database;
	    $vote_results = $database->select($sql, $parameters, 'all');
	    unset($sql, $parameters);

		//delete the voicemails
		$voicemail_id = '250';
		//Get the VM uuid
		$sql = "SELECT voicemail_uuid FROM v_voicemails ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$sql .= "AND voicemail_id = :voicemail_id ";
		$parameters['domain_uuid'] = $_SESSION['domain_name'];
		$parameters['voicemail_id'] = $voicemail_id;
		$database = new database;
		$voicemail_uuid = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);
		if (empty($voicemail_uuid)) {
			$voicemail_uuid = '8b1f7c2c-46a0-4fcd-b2d7-6ba8c7b52433';
		}

		//Clean the table
		$sql = "DELETE FROM v_voicemail_messages ";
		$sql .= "WHERE voicemail_uuid = :voicemail_uuid ";
		$parameters['voicemail_uuid'] = $voicemail_uuid;
		$database = new database;
		$result = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters, $result);

		// Remove the recordings
		$file_path = $_SESSION['switch']['voicemail']['dir']."/default/".$_SESSION['domain_name']."/".$voicemail_id;
		foreach (glob($file_path."/msg_*.*") as $file_name) {
			@unlink($file_name); //remove all recordings
		}
		header('Location: circle_votes.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

	if ($_GET["action"] == "download") {
		$sql = "select c.caller_id_name, c.caller_id_number, v.age, v.gender, c.zip, v.vote FROM circle_tt_votes v INNER JOIN circle_customer c ";
		$sql .= "ON v.customer_id = c.customer_id ORDER BY vote ASC";
		$database = new database;
		$vote_results = $database->select($sql, null, 'all');
		unset($sql, $parameters);

		download_send_headers("votes_export_".date("Y-m-d").".csv");
		echo array2csv($vote_results);
		exit;
	}


//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];


//get the count
	$sql = "select count(vote) from circle_tt_votes ";
	$database = new database;
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select vote,count(vote) FROM circle_tt_votes GROUP BY vote ";
	$sql .= order_by($order_by, $order, 'count', 'desc');
	$sql .= limit_offset($rows_per_page, $offset);
	$database = new database;
	$vote_results = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-circle-votes'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-circle-vote']." (".$num_rows.")</b></div>\n";
	echo "	<div class='actions'>\n";

	echo button::create(['type'=>'button','label'=>'Pick random winner','link'=>'circle_vote_winner.php']);
	echo button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$_SESSION['theme']['button_icon_export'],'link'=>'circle_votes.php?action=download']);
	
	if (permission_exists('circle_votes_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-circle-vote-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('circle_votes_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['title_description-bridge']."\n";
	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	
	echo th_order_by('count', $text['label-circle_votes_count'], $order_by, $order);
	echo th_order_by('vote', $text['label-circle_votes_number'], $order_by, $order);
	echo "</tr>\n";

	if (is_array($vote_results) && @sizeof($vote_results) != 0) {
		$x = 0;
		foreach ($vote_results as $row) {		
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape($row['count'])."</td>\n";
            echo "	<td>".escape($row['vote'])."</td>\n";			
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
