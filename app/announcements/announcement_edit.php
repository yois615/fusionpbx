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
*/

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('annoucement_add') && !permission_exists('annoucement_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$annoucement_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$annoucement_uuid = $_POST["annoucement_uuid"];
		$annoucement_name = $_POST["annoucement_name"];
		$annoucement_destination = $_POST["annoucement_destination"];
		$annoucement_enabled = $_POST["annoucement_enabled"];
		$annoucement_description = $_POST["annoucement_description"];
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//delete the annoucement
			if (permission_exists('annoucement_delete')) {
				if ($_POST['action'] == 'delete' && is_uuid($annoucement_uuid)) {
					//prepare
						$array[0]['checked'] = 'true';
						$array[0]['uuid'] = $annoucement_uuid;
					//delete
						$obj = new annoucements;
						$obj->delete($array);
					//redirect
						header('Location: annoucements.php');
						exit;
				}
			}

		//get the uuid from the POST
			if ($action == "update") {
				$annoucement_uuid = $_POST["annoucement_uuid"];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: annoucements.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (strlen($annoucement_name) == 0) { $msg .= $text['message-required']." ".$text['label-annoucement_name']."<br>\n"; }
			if (strlen($annoucement_destination) == 0) { $msg .= $text['message-required']." ".$text['label-annoucement_destination']."<br>\n"; }
			if (strlen($annoucement_enabled) == 0) { $msg .= $text['message-required']." ".$text['label-annoucement_enabled']."<br>\n"; }
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

		//add the annoucement_uuid
			if (strlen($annoucement_uuid) == 0) {
				$annoucement_uuid = uuid();
			}

		//add the dialplan_uuid
		if (strlen($_POST["dialplan_uuid"]) == 0) {
			$dialplan_uuid = uuid();
			$_POST["dialplan_uuid"] = $dialplan_uuid;
		}

		//prepare the array
			$array['annoucements'][0]['annoucement_uuid'] = $annoucement_uuid;
			$array['annoucements'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['annoucements'][0]['annoucement_name'] = $annoucement_name;
			$array['annoucements'][0]['annoucement_destination'] = $annoucement_destination;
			$array['annoucements'][0]['annoucement_enabled'] = $annoucement_enabled;
			$array['annoucements'][0]['annoucement_description'] = $annoucement_description;

		//build the xml dialplan
		$dialplan_xml = "<extension name=\"".$announcement_name."\" continue=\"\" uuid=\"".$dialplan_uuid."\">\n";
		$dialplan_xml .= "	<condition field=\"destination_number\" expression=\"^([^#]+#)(.*)\$\" break=\"never\">\n";
		$dialplan_xml .= "		<action application=\"set\" data=\"caller_id_name=\$2\"/>\n";
		$dialplan_xml .= "	</condition>\n";
		$dialplan_xml .= "	<condition field=\"destination_number\" expression=\"^(callcenter\+)?".$queue_extension."$\">\n";
		$dialplan_xml .= "		<action application=\"answer\" data=\"\"/>\n";
		if (is_uuid($call_center_queue_uuid)) {
			$dialplan_xml .= "		<action application=\"set\" data=\"call_center_queue_uuid=".$call_center_queue_uuid."\"/>\n";
		}
		if (is_numeric($queue_extension)) {
			$dialplan_xml .= "		<action application=\"set\" data=\"queue_extension=".$queue_extension."\"/>\n";
		}
		$dialplan_xml .= "		<action application=\"set\" data=\"cc_export_vars=\${cc_export_vars},call_center_queue_uuid,sip_h_Alert-Info\"/>\n";
		$dialplan_xml .= "		<action application=\"set\" data=\"hangup_after_bridge=true\"/>\n";
		if ($queue_announce_position && is_numeric($queue_announce_frequency)) {
			$dialplan_xml .= "		<action application=\"set\" data=\"result=\${luarun(app/call_centers/resources/scripts/announce-position.lua ";
			$dialplan_xml .= "\${uuid} ".$call_center_queue_uuid." ".($queue_announce_frequency * 1000)."})\"/>\n";
		}
		if ($queue_time_base_score_sec != '') {
			$dialplan_xml .= "		<action application=\"set\" data=\"cc_base_score=".$queue_time_base_score_sec."\"/>\n";
		}
		if ($queue_greeting_path != '') {
			$dialplan_xml .= "		<action application=\"sleep\" data=\"1000\"/>\n";
			$greeting_array = explode(':', $queue_greeting_path);
			if (count($greeting_array) == 1) {
				$dialplan_xml .= "		<action application=\"playback\" data=\"".$queue_greeting_path."\"/>\n";
			}
			else {
				if ($greeting_array[0] == 'say' || $greeting_array[0] == 'tone_stream' || $greeting_array[0] == 'phrase') {
					$dialplan_xml .= "		<action application=\"".$greeting_array[0]."\" data=\"".$greeting_array[1]."\"/>\n";
				}
			}
		}
		if (strlen($queue_cid_prefix) > 0) {
			$dialplan_xml .= "		<action application=\"set\" data=\"effective_caller_id_name=".$queue_cid_prefix."#\${caller_id_name}\"/>\n";
			$dialplan_xml .= "		<action application=\"set\" data=\"caller_id_name=".$queue_cid_prefix."#\${caller_id_name}\"/>\n";
		}

		if (!strpos($queue_cc_exit_keys, "1") && strlen($queue_callback_profile) > 0) {
			$queue_cc_exit_keys .= "1";
		}

		if (strlen($queue_cc_exit_keys) > 0) {
			$dialplan_xml .= "		<action application=\"set\" data=\"cc_exit_keys=".$queue_cc_exit_keys."\"/>\n";
		}
		$dialplan_xml .= "		<action application=\"callcenter\" data=\"".$queue_extension."@".$_SESSION["domain_name"]."\"/>\n";

		if (strlen($queue_callback_profile) > 0) {
			$dialplan_xml .= "		<action application=\"lua\" data=\"app/call_centers/resources/scripts/callback.lua start ".$call_center_queue_uuid."\"/>\n";
		}

		if ($destination->valid($queue_timeout_app.':'.$queue_timeout_data)) {
			$dialplan_xml .= "		<action application=\"".$queue_timeout_app."\" data=\"".$queue_timeout_data."\"/>\n";
		}
		$dialplan_xml .= "	</condition>\n";
		$dialplan_xml .= "</extension>\n";

	//build the dialplan array
		$array['dialplans'][0]["domain_uuid"] = $_SESSION['domain_uuid'];
		$array['dialplans'][0]["dialplan_uuid"] = $dialplan_uuid;
		$array['dialplans'][0]["dialplan_name"] = $queue_name;
		$array['dialplans'][0]["dialplan_number"] = $queue_extension;
		$array['dialplans'][0]["dialplan_context"] = $_SESSION['domain_name'];
		$array['dialplans'][0]["dialplan_continue"] = "false";
		$array['dialplans'][0]["dialplan_xml"] = $dialplan_xml;
		$array['dialplans'][0]["dialplan_order"] = "230";
		$array['dialplans'][0]["dialplan_enabled"] = "true";
		$array['dialplans'][0]["dialplan_description"] = $queue_description;
		$array['dialplans'][0]["app_uuid"] = "5d2e6675-b359-4feb-80b4-602d0639ff4e";

	//add the dialplan permission
		$p = new permissions;
		$p->add("dialplan_add", "temp");
		$p->add("dialplan_edit", "temp");


		//save to the data
			$database = new database;
			$database->app_name = 'annoucements';
			$database->app_uuid = '5d2e6675-b359-4feb-80b4-602d0639ff4e';
			$database->save($array);
			$message = $database->message;

		//remove the temporary permission
			$p->delete("dialplan_add", "temp");	
			$p->delete("dialplan_edit", "temp");

			//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					$_SESSION["message"] = $text['message-add'];
				}
				if ($action == "update") {
					$_SESSION["message"] = $text['message-update'];
				}
				header('Location: annoucements.php');
				return;
			}
	}

//pre-populate the form
	if (is_array($_GET) && $_POST["persistformvar"] != "true") {
		$annoucement_uuid = $_GET["id"];
		$sql = "select * from v_annoucements ";
		$sql .= "where annoucement_uuid = :annoucement_uuid ";
		$parameters['annoucement_uuid'] = $annoucement_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && sizeof($row) != 0) {
			$annoucement_name = $row["annoucement_name"];
			$annoucement_destination = $row["annoucement_destination"];
			$annoucement_enabled = $row["annoucement_enabled"];
			$annoucement_description = $row["annoucement_description"];
		}
		unset($sql, $parameters, $row);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-annoucement'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-annoucement']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'annoucements.php']);
	if ($action == 'update' && permission_exists('annoucement_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('annoucement_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-annoucement_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='annoucement_name' maxlength='255' value='".escape($annoucement_name)."'>\n";
	echo "<br />\n";
	echo $text['description-annoucement_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-annoucement_destination']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='annoucement_destination' maxlength='255' value='".escape($annoucement_destination)."'>\n";
	echo "<br />\n";
	echo $text['description-annoucement_destination']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-annoucement_enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<select class='formfld' name='annoucement_enabled'>\n";
	if ($annoucement_enabled == "true") {
		echo "		<option value='true' selected='selected'>".$text['label-true']."</option>\n";
	}
	else {
		echo "		<option value='true'>".$text['label-true']."</option>\n";
	}
	if ($annoucement_enabled == "false") {
		echo "		<option value='false' selected='selected'>".$text['label-false']."</option>\n";
	}
	else {
		echo "		<option value='false'>".$text['label-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-annoucement_enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-annoucement_description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='annoucement_description' maxlength='255' value=\"".escape($annoucement_description)."\">\n";
	echo "<br />\n";
	echo $text['description-annoucement_description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='annoucement_uuid' value='".escape($annoucement_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>