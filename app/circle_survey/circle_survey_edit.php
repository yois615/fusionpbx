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
	if (permission_exists('circle_survey_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

	//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$circle_survey_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

	//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$week_id = $_POST["week_id"];
		$greeting = $_POST["greeting"];
		$survey_recordings = $_POST["survey_recordings"];
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {


		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: circle_surveys.php');
				exit;
			}

		//get the uuid from the POST
			if ($action == "update") {
				$circle_survey_uuid = $_POST["circle_survey_uuid"];
			}

		//add the circle_survey_uuid
			if (strlen($circle_survey_uuid) == 0) {
				$circle_survey_uuid = uuid();
			}

		//check for all required data
			$msg = '';
			if (strlen($week_id) == 0) { $msg .= $text['message-required']." ".$text['label-week-id']."<br>\n"; }
			if (strlen($greeting) == 0) { $msg .= $text['message-required']." ".$text['label-greeting']."<br>\n"; }
			if (strlen($survey_recordings) == 0) { $msg .= $text['message-required']." ".$text['label-survey-recordings']."<br>\n"; }
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


		//prepare the array
			$array['circle_survey'][0]['circle_survey_uuid'] = $circle_survey_uuid;
			$array['circle_survey'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['circle_survey'][0]['week_id'] = $week_id;
			$array['circle_survey'][0]['greeting'] = $greeting;

		//prepare the recordings array
			if (is_array($survey_recordings)) {
				foreach ($survey_recordings as $i => $r) {
				$array['circle_survey_questions'][$i]['circle_survey_uuid'] = $circle_survey_uuid;
				$array['circle_survey_questions'][$i]['domain_uuid'] = $_SESSION["domain_uuid"];
				$array['circle_survey_questions'][$i]['sequence_id'] = $sequence_id;
				$array['circle_survey_questions'][$i]['recording'] = $recording;
				}
			}

		//grant temporary permissions
			$p = new permissions;
			$p->add('circle_survey_questions_add', 'temp');
			$p->add('circle_survey_questions_edit', 'temp');

		//save to the data
			$database = new database;
			$database->app_name = 'circle_survey';
			$database->app_uuid = '32af1175-9f22-4073-9499-33b50bbddad5';
			$database->save($array);
			$message = $database->message;

		//remove temporary permissions
				$p->delete('circle_survey_questions_add', 'temp');
				$p->delete('circle_survey_questions_edit', 'temp');

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
				header('Location: circle_survey.php');
				return;
			}
	}



//add multi-lingual support
	$language = new text;
	$text = $language->get();


//pre-populate the form
	$sql = "SELECT * FROM v_circle_surveys ";
	$sql .= "where circle_survey_uuid = :circle_survey_uuid ";
	$parameters['circle_survey_uuid'] = $circle_survey_uuid;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && sizeof($row) != 0) {
		$week_id = $row["weed_id"];
		$greeting = $row["greeting"];
	}
	unset($sql, $parameters, $row);

//Get the questions
	if (is_uuid($circle_survey_uuid)) {
		$sql = "select * from v_circle_survey_questions ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and circle_survey_uuid = :circle_survey_uuid ";
		$sql .= "order by sequence_id asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['circle_survey_uuid'] = $circle_survey_uuid;
		$database = new database;
		$survey_recordings = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);
	}

//get the recordings
	$sql = "select recording_name, recording_filename from v_recordings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by recording_name asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-circle_survey_edit'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Survey Config</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','icon'=>$_SESSION['theme']['button_icon_back'],'label'=>'Back','link'=>'circle_survey.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-week_id']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='week_id' maxlength='255' value='".escape($week_id)."'>\n";
	echo "<br />\n";
	echo $text['description-week-id']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-greeting']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='greeting' id='greeting' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		$tmp_selected = false;
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as &$row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if ($greeting == $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$recording_filename && strlen($greeting) > 0) {
					$tmp_selected = true;
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else if ($greeting == $recording_filename && strlen($greeting) > 0) {
					$tmp_selected = true;
					echo "	<option value='".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else {
					echo "	<option value='".escape($recording_filename)."'>".escape($recording_name)."</option>\n";
				}
			}
			echo "</optgroup>\n";
		}
	echo "	</select>\n";
	echo "</td>\n";
	echo "<br />\n";
	echo $text['description-greeting']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-survey_recordings']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<select class='formfld' name='survey_recordings'>\n";
	if ($bridge_enabled == "true") {
		echo "		<option value='true' selected='selected'>".$text['label-true']."</option>\n";
	}
	else {
		echo "		<option value='true'>".$text['label-true']."</option>\n";
	}
	if ($bridge_enabled == "false") {
		echo "		<option value='false' selected='selected'>".$text['label-false']."</option>\n";
	}
	else {
		echo "		<option value='false'>".$text['label-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-bridge_enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-bridge_description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='bridge_description' maxlength='255' value=\"".escape($bridge_description)."\">\n";
	echo "<br />\n";
	echo $text['description-bridge_description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='bridge_uuid' value='".escape($bridge_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
