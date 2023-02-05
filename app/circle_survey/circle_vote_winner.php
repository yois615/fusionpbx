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


//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];


//get the count
	$sql = "select count(vote) from circle_tt_votes ";
	$database = new database;
	$num_votes = $database->select($sql, $parameters, 'column');
	$num_rows = 1;

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list

	$sql = "select v.vote, v.call_uuid, c.caller_id_name, c.caller_id_number, vmm.voicemail_uuid, vm.voicemail_id ";
    $sql .= "FROM circle_tt_votes v INNER JOIN circle_customer c ON v.customer_id = c.customer_id ";
	$sql .= "INNER JOIN v_voicemail_messages vmm ON v.call_uuid = vmm.voicemail_message_uuid ";
	$sql .= "INNER JOIN v_voicemails vm ON vmm.voicemail_uuid = vm.voicemail_uuid ";
	$sql .= "ORDER BY random() LIMIT 1 ";
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
	echo "	<div class='heading'><b>Pick a winner (".$num_votes.")</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','icon'=>$_SESSION['theme']['button_icon_back'],'label'=>'Back','link'=>'circle_votes.php']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	
	echo th_order_by('caller_id_number', 'Caller ID Number', $order_by, $order);
	echo th_order_by('caller_id_name', 'Caller ID Name', $order_by, $order);
    echo th_order_by('vote', 'Vote', $order_by, $order);
    echo "<th class='center shrink'>".$text['label-tools']."</th>\n";
	//echo th_order_by('call_uuid', 'Call and VM UUID', $order_by, $order);
	echo "</tr>\n";

	if (is_array($vote_results) && @sizeof($vote_results) != 0) {
		$x = 0;
		foreach ($vote_results as $row) {	
			//playback progress bar
			echo "<tr class='list-row' id='recording_progress_bar_".escape($row['call_uuid'])."' style='display: none;'><td class='playback_progress_bar_background' style='padding: 0; border: none;' colspan='4'><span class='playback_progress_bar' id='recording_progress_".escape($row['call_uuid'])."'></span></td></tr>\n";
			echo "<tr style='display: none;'><td></td></tr>\n"; // dummy row to maintain alternating background color	
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape($row['caller_id_number'])."</td>\n";
            echo "	<td>".escape($row['caller_id_name'])."</td>\n";
            echo "	<td>".escape($row['vote'])."</td>\n";
			echo "	<td class='button center no-link no-wrap'>";
			echo 		"<audio id='recording_audio_".escape($row['call_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['call_uuid'])."')\" onended=\"recording_reset('".escape($row['call_uuid'])."');\" src='/app/voicemails/voicemail_messages.php?action=download&id=".urlencode($row['voicemail_id'])."&voicemail_uuid=".urlencode($row['voicemail_uuid'])."&uuid=".urlencode($row['call_uuid'])."&r=".uuid()."'></audio>";
			echo button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$_SESSION['theme']['button_icon_play'],'id'=>'recording_button_'.escape($row['call_uuid']),'onclick'=>"recording_play('".escape($row['call_uuid'])."');"]);
			echo button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$_SESSION['theme']['button_icon_download'],'link'=>"/app/voicemails/voicemail_messages.php?action=download&id=".urlencode($row['voicemail_id'])."&voicemail_uuid=".escape($row['voicemail_uuid'])."&uuid=".escape($row['call_uuid'])."&t=bin&r=".uuid(),'onclick'=>"$(this).closest('tr').children('td').css('font-weight','normal');"]);
			echo "	</td>\n";
            
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
