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

//check permissions
	if (permission_exists('circle_raffle_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//process the http post data by action
	if ($action == 'delete' && permission_exists('circle_raffle_delete')) {
		$sql = "DELETE FROM circle_raffle_numbers ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		$sql = "DELETE FROM circle_raffle_customer ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		$sql = "DELETE FROM circle_raffle_cdr ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		//delete the voicemails
		$voicemail_id = $vote_id;
		//Get the VM uuid
		$sql = "SELECT voicemail_uuid FROM v_voicemails ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$sql .= "AND voicemail_id = :voicemail_id ";
		$parameters['domain_uuid'] = $_SESSION['domain_name'];
		$parameters['voicemail_id'] = $voicemail_id;
		$database = new database;
		$voicemail_uuid = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

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
		header('Location: circle_raffle.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

	if ($action == 'save' && permission_exists('circle_raffle_delete')) {
		//Winning numbers can only be added/saved by deleting the old database
		$sql = "DELETE FROM circle_raffle_numbers ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		$sql = "DELETE FROM circle_raffle_customer ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		$sql = "DELETE FROM circle_raffle_cdr ";
	    $database = new database;
	    $vote_results = $database->select($sql);
	    unset($sql, $parameters);

		//delete the voicemails
		$voicemail_id = $vote_id;
		//Get the VM uuid
		$sql = "SELECT voicemail_uuid FROM v_voicemails ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$sql .= "AND voicemail_id = :voicemail_id ";
		$parameters['domain_uuid'] = $_SESSION['domain_name'];
		$parameters['voicemail_id'] = $voicemail_id;
		$database = new database;
		$voicemail_uuid = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

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

		$winning_numbers = $_POST["winning_numbers"];

		if (is_array($winning_numbers)) {
			foreach ($winning_numbers as $i => $r) {
				$sql = "INSERT INTO v_circle_raffle_numbers (winning_number) VALUES (".$r['winning_number'].")";
				$database = new database;
				$database->select($sql);
				unset($sql, $parameters);
			}
		}			
	}


//get the raffle numbers
	$sql = "select r.winning_number, r.winning_customer_id, r.call_epoch, r.call_uuid, c.caller_id_name, c.caller_id_number, vmm.voicemail_uuid, vm.voicemail_id ";
    $sql .= "FROM circle_raffle_numbers r INNER JOIN circle_raffle_customer c ON r.winning_customer_id = c.customer_id ";
	$sql .= "INNER JOIN v_voicemail_messages vmm ON v.call_uuid = vmm.voicemail_message_uuid ";
	$sql .= "INNER JOIN v_voicemails vm ON vmm.voicemail_uuid = vm.voicemail_uuid ORDER BY r.call_epoch, r.winning_number ";

	$database = new database;
	$winning_number_results = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-circle-raffle'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-circle-raffle']." (".count($raffle_numbers).")</b></div>\n";
	echo "	<div class='actions'>\n";
	
	if (permission_exists('circle_votes_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-circle-raffle-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('circle_votes_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}


	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Raffle Config</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	 // Show input winning number, Winner caller ID name, number, winning epoch, and voicemail action play button.
	 ?>
		<td>Winning Number</td>
		<td>Claimed time</td>
		<td>Winner Caller ID Num</td>
		<td>Winner Caller ID Name</td>
		<td>Voicemail</td>

	 <?php
	echo "</tr>\n";

	if (is_array($winning_number_results) && @sizeof($winning_number_results) != 0) {
		$x = 0;
		foreach ($winning_number_results as $row) {		
			$array = explode(' ', $row['call_epoch']);
			if ($array[0].' '.$array[1].' '.$array[2] == date('j M Y')) { //today
				$created_date = escape($array[3].' '.$array[4]); //only show time
			}
			else {
				$created_date = escape($array[0].' '.$array[1].' '.$array[2])." <span class='hide-xs' title=\"".escape($array[3].' '.$array[4])."\">".escape($array[3].' '.$array[4])."</span>";
			}

			echo "<tr class='list-row'>\n";
			echo "<td class='vtable' align='left'>\n";
			echo "	<input class='formfld' type='text' name=\"circle_raffle_numbers[".$x."][winning_number]\" maxlength='5' value=\"".escape($row['winning_number'])."\" readonly>\n";
			echo "</td>\n";
			//Show input winning number, Winner caller ID name, number, winning epoch, and voicemail action play button.  Also need a plus button to add a row.
			echo "	<td>".$created_date."</td>\n";
            echo "	<td>".escape($row['caller_id_number'])."</td>\n";
			echo "	<td>".escape($row['caller_id_name'])."</td>\n";
			echo "	<td class='button center no-link no-wrap'>";
			echo 		"<audio id='recording_audio_".escape($row['call_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['call_uuid'])."')\" onended=\"recording_reset('".escape($row['call_uuid'])."');\" src='/app/voicemails/voicemail_messages.php?action=download&id=".urlencode($row['voicemail_id'])."&voicemail_uuid=".urlencode($row['voicemail_uuid'])."&uuid=".urlencode($row['call_uuid'])."&r=".uuid()."'></audio>";
			echo button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$_SESSION['theme']['button_icon_play'],'id'=>'recording_button_'.escape($row['call_uuid']),'onclick'=>"recording_play('".escape($row['call_uuid'])."');"]);
			echo button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$_SESSION['theme']['button_icon_download'],'link'=>"/app/voicemails/voicemail_messages.php?action=download&id=".urlencode($row['voicemail_id'])."&voicemail_uuid=".escape($row['voicemail_uuid'])."&uuid=".escape($row['call_uuid'])."&t=bin&r=".uuid(),'onclick'=>"$(this).closest('tr').children('td').css('font-weight','normal');"]);
			echo "	</td>\n";
			echo "</tr>\n";
			$x++;
		}
		if ($x < 21) {
			// Add more blank rows
			echo "<tr class='list-row'>\n";
			echo "<td class='vtable' align='left'>\n";
			echo "	<input class='formfld' type='text' name=\"circle_raffle_numbers[".$x."][winning_number]\" maxlength='5'>\n";
			echo "</td>\n";
			echo "</tr>\n";
			$x++;
		}
		unset($winning_number_results);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
