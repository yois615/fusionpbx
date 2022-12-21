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
	Portions created by the Initial Developer are Copyright (C) 2008-2020
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('call_center_callback_add') || permission_exists('call_center_callback_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$call_center_queue_callback_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$call_center_queue_callback_uuid = $_POST["call_center_queue_callback_uuid"];
		$profile_name = $_POST["profile_name"];
		$profile_description = $_POST["profile_description"];
		$caller_id_name = $_POST["caller_id_name"];
		$caller_id_number = $_POST["caller_id_number"];
		$callback_dialplan = $_POST["callback_dialplan"];
		$callback_request_prompt = $_POST["callback_request_prompt"];
		$callback_confirm_prompt = $_POST["callback_confirm_prompt"];
		$callback_force_cid = $_POST["callback_force_cid"];
		$callback_retries = $_POST["callback_retries"];
		$callback_timeout = $_POST["callback_timeout"];
		$callback_retry_delay = $_POST["callback_retry_delay"];
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: call_center_callback.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (strlen($profile_name) == 0) { $msg .= $text['message-required']." ".$text['label-agent_name']."<br>\n"; }
			if (strlen($callback_force_cid) == 0) { $msg .= $text['message-required']." ".$text['label-agent_type']."<br>\n"; }
			if (strlen($callback_retries) == 0) { $msg .= $text['message-required']." ".$text['label-agent_call_timeout']."<br>\n"; }
			if (strlen($callback_retry_delay) == 0) { $msg .= $text['message-required']." ".$text['label-agent_contact']."<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the call_center_queue_callback_uuid
			if (strlen($call_center_queue_callback_uuid) == 0) {
				$call_center_queue_callback_uuid = uuid();
			}

		//prepare the array
			$array['call_center_callback_profile'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['call_center_callback_profile'][0]['call_center_queue_callback_uuid'] = $call_center_queue_callback_uuid;
			$array['call_center_callback_profile'][0]['profile_name'] = $profile_name;
			$array['call_center_callback_profile'][0]['profile_description'] = $profile_description;
			$array['call_center_callback_profile'][0]['caller_id_name'] = $caller_id_name;
			$array['call_center_callback_profile'][0]['caller_id_number'] = $caller_id_number;
			$array['call_center_callback_profile'][0]['callback_dialplan'] = $callback_dialplan;
			$array['call_center_callback_profile'][0]['callback_request_prompt'] = $callback_request_prompt;
			$array['call_center_callback_profile'][0]['callback_confirm_prompt'] = $callback_confirm_prompt;
			$array['call_center_callback_profile'][0]['callback_force_cid'] = $callback_force_cid;
			$array['call_center_callback_profile'][0]['callback_retries'] = $callback_retries;
			$array['call_center_callback_profile'][0]['callback_timeout'] = $callback_timeout;
			$array['call_center_callback_profile'][0]['callback_retry_delay'] = $callback_retry_delay;

		//save to the data
			$database = new database;
			$database->app_name = 'call_center';
			$database->app_uuid = '95788e50-9500-079e-2807-fd530b0ea370';
			$database->save($array);
			//$message = $database->message;

		//syncrhonize configuration
			save_call_center_xml();

		//clear the cache
			$cache = new cache;
			$cache->delete('configuration:callcenter.conf');

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					message::add($text['message-add']);
				}
				if ($action == "update") {
					message::add($text['message-update']);
				}
				header("Location: call_center_callback.php");
				return;
			}
	}

//initialize the destinations object
	$destination = new destinations;

//pre-populate the form
	if (is_uuid($_GET["id"]) && $_POST["persistformvar"] != "true") {
		$call_center_queue_callback_uuid = $_GET["id"];
		$sql = "select * from v_call_center_callback_profile ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and call_center_queue_callback_uuid = :call_center_queue_callback_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['call_center_queue_callback_uuid'] = $call_center_queue_callback_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$call_center_queue_callback_uuid = $row["call_center_queue_callback_uuid"];
			$profile_name = $row["profile_name"];
			$profile_description = $row["profile_description"];
			$caller_id_name = $row["caller_id_name"];
			$caller_id_number = $row["caller_id_number"];
			$callback_dialplan = $row["callback_dialplan"];
			$callback_request_prompt = $row["callback_request_prompt"];
			$callback_confirm_prompt = $row["callback_confirm_prompt"];
			$callback_force_cid = $row["callback_force_cid"];
			$callback_retries = $row["callback_retries"];
			$callback_timeout = $row["callback_timeout"];
			$callback_retry_delay = $row["callback_retry_delay"];
		}
		unset($sql, $parameters, $row);
	}

//set default values
	if (strlen($callback_dialplan) == 0) { $callback_dialplan = "^1?([2-9](?!11)[0-9]{2})([2-9](?!11)[0-9]{2})([0-9]{4})$"; }
	if (strlen($callback_force_cid) == 0) { $callback_force_cid = "false"; }
	if (strlen($callback_retries) == 0) { $callback_retries = "2"; }
	if (strlen($callback_timeout) == 0) { $callback_timeout = "30"; }
	if (strlen($callback_retry_delay) == 0) { $callback_retry_delay = "300"; }
	
//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	if ($action == "add") {
		$document['title'] = $text['title-call_center_agent_add'];
	}
	if ($action == "update") {
		$document['title'] = $text['title-call_center_agent_edit'];
	}
	require_once "resources/header.php";

//get the sounds
	$sounds = new sounds;
	$sounds = $sounds->get();

//show the content
	echo "<form method='post' name='frm' id='frm' >\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-call_center_agent_add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-call_center_agent_edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'call_center_callback.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-profile_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='profile_name' maxlength='255' value=\"".escape($profile_name)."\" />\n";
	echo "<br />\n";
	echo $text['description-profile_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	// Up to here
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-type']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='agent_type' maxlength='255' value=\"".escape($agent_type)."\" pattern='^(callback|uuid-standby)$'>\n";
	echo "<br />\n";
	echo $text['description-type']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-call_timeout']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_call_timeout' maxlength='255' min='1' step='1' value='".escape($agent_call_timeout)."'>\n";
	echo "<br />\n";
	echo $text['description-call_timeout']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>";
	echo "		<td class='vncell' valign='top'>".$text['label-username']."</td>";
	echo "		<td class='vtable' align='left'>";
	echo "<select name='queue_greeting' class='formfld' style='width: 200px;' ".((if_group("superadmin")) ? "onchange='changeToInput(this);'" : null).">\n";
	echo "	<option value=''></option>\n";
	foreach($sounds as $key => $value) {
		echo "<optgroup label=".$text['label-'.$key].">\n";
		$selected = false;
		foreach($value as $row) {
			if ($queue_greeting == $row["value"]) { 
				$selected = true;
				echo "	<option value='".escape($row["value"])."' selected='selected'>".escape($row["name"])."</option>\n";
			}
			else {
				echo "	<option value='".escape($row["value"])."'>".escape($row["name"])."</option>\n";
			}
		}
		echo "</optgroup>\n";
	}
	if (if_group("superadmin")) {
		if (!$selected && strlen($queue_greeting) > 0) {
			echo "	<option value='".escape($queue_greeting)."' selected='selected'>".escape($queue_greeting)."</option>\n";
		}
		unset($selected);
	}
	echo "	</select>\n";
	unset($users);
	echo "			<br>\n";
	echo "			".$text['description-users']."\n";
	echo "		</td>";
	echo "	</tr>";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-agent_id']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_id' id='agent_id' maxlength='255' min='1' step='1' value='".escape($agent_id)."'>\n";
	echo "	<div style='display: none;' id='duplicate_agent_id_response'></div>\n";
	echo "<br />\n";
	echo $text['description-agent_id']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-agent_password']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='password' name='agent_password' autocomplete='off' onmouseover=\"this.type='text';\" onfocus=\"this.type='text';\" onmouseout=\"if (!\$(this).is(':focus')) { this.type='password'; }\" onblur=\"this.type='password';\" maxlength='255' min='1' step='1' value='".escape($agent_password)."'>\n";
	echo "<br />\n";
	echo $text['description-agent_password']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-contact']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo $destination->select('user_contact', 'agent_contact', $agent_contact);
	echo "<br />\n";
	echo $text['description-contact']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-status']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='agent_status'>\n";
	echo "	<option value=''></option>\n";
	if ($agent_status == "Logged Out") {
		echo "	<option value='Logged Out' SELECTED >".$text['option-logged_out']."</option>\n";
	}
	else {
		echo "	<option value='Logged Out'>".$text['option-logged_out']."</option>\n";
	}
	if ($agent_status == "Available") {
		echo "	<option value='Available' SELECTED >".$text['option-available']."</option>\n";
	}
	else {
		echo "	<option value='Available'>".$text['option-available']."</option>\n";
	}
	if ($agent_status == "Available (On Demand)") {
		echo "	<option value='Available (On Demand)' SELECTED >".$text['option-available_on_demand']."</option>\n";
	}
	else {
		echo "	<option value='Available (On Demand)'>".$text['option-available_on_demand']."</option>\n";
	}
	if ($agent_status == "On Break") {
		echo "	<option value='On Break' SELECTED >".$text['option-on_break']."</option>\n";
	}
	else {
		echo "	<option value='On Break'>".$text['option-on_break']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-status']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-no_answer_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_no_answer_delay_time' maxlength='255' min='0' step='1' value='".escape($agent_no_answer_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-no_answer_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-max_no_answer']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_max_no_answer' maxlength='255' min='0' step='1' value='".escape($agent_max_no_answer)."'>\n";
	echo "<br />\n";
	echo $text['description-max_no_answer']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-wrap_up_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_wrap_up_time' maxlength='255' min='0' step='1' value='".escape($agent_wrap_up_time)."'>\n";
	echo "<br />\n";
	echo $text['description-wrap_up_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-reject_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_reject_delay_time' maxlength='255' min='0' step='1' value='".escape($agent_reject_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-reject_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-busy_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_busy_delay_time' maxlength='255' min='1' step='1' value='".escape($agent_busy_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-busy_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-record_template']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='agent_record'>\n";
	echo "	<option value='true' ".($agent_record == "true" ?  "selected='selected'" : '')." >".$text['option-true']."</option>\n";
	echo "	<option value='false' ".($agent_record != "true" ?  "selected='selected'" : '').">".$text['option-false']."</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-record_template']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='call_center_queue_callback_uuid' value='".escape($call_center_queue_callback_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
