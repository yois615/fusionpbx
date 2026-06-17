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

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('survey_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

	//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$survey_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

	//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$greeting = $_POST["greeting"];
		$exit_file = $_POST["exit_file"];
		$age_file = $_POST["age_file"];
		$gender_file = $_POST["gender_file"];
		$retake_file = $_POST["retake_file"];
		$zip_code_file = $_POST["zip_code_file"];
		$greeting_suffix = $_POST["greeting_suffix"];
		$question_answered_file = $_POST["question_answered_file"];
		$reason_file = $_POST["reason_file"];
		$reason_0_file = $_POST["reason_0_file"];
		$ask_reason_below = $_POST["ask_reason_below"];
		$exit_action = $_POST["exit_action"];
		$description = $_POST["description"];
		$name = $_POST["name"];
		$survey_questions = $_POST["survey_questions"];
		$survey_questions_delete = $_POST["survey_questions_delete"];
		$ask_only_odd_even = $_POST["ask_only_odd_even"];
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {


		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: surveys.php');
				exit;
			}

		//get the uuid from the POST
			if ($action == "update") {
				$survey_uuid = $_POST["survey_uuid"];
			}


		//add the survey_uuid
			if (strlen($survey_uuid) == 0) {
				$survey_uuid = uuid();
			}

		//prepare the recordings array
			$msg = '';
			if (is_array($survey_questions)) {
				$sequence_ids = array();
				foreach ($survey_questions as $i => $r) {
					if (in_array($r['sequence_id'], $sequence_ids)) {
						//duplicate sequence_id
						$msg .= "Duplicate sequence id detected.<br>\n";
					}
					else {
						$sequence_ids[] = $r['sequence_id'];
					}
					if (strlen($r['recording']) > 0) {
						if (is_uuid($r['survey_question_uuid'])) {
							$survey_question_uuid = $r['survey_question_uuid'];
						}
						else {
							$survey_question_uuid = uuid();
						}
						$array['surveys'][0]['survey_questions'][$i]['survey_uuid'] = $survey_uuid;
						$array['surveys'][0]['survey_questions'][$i]['survey_question_uuid'] = $survey_question_uuid;
						$array['surveys'][0]['survey_questions'][$i]['domain_uuid'] = $_SESSION["domain_uuid"];
						$array['surveys'][0]['survey_questions'][$i]['sequence_id'] = $r['sequence_id'];
						$array['surveys'][0]['survey_questions'][$i]['recording'] = $r['recording'];
						$array['surveys'][0]['survey_questions'][$i]['recording_suffix'] = $r['recording_suffix'];
						$array['surveys'][0]['survey_questions'][$i]['description'] = $r['description'];
						$array['surveys'][0]['survey_questions'][$i]['highest_number'] = $r['highest_number'];
					}
				}
			}

			// Check that no question is missing in the sequence
			$all_sequence_id = range(1,max($sequence_ids));                                                    

			// use array_diff to get the missing elements 
			$missing = array_diff($all_sequence_id, $sequence_ids);

			if (count($missing) > 0) {
				$msg .= "Missing sequence id detected.<br>\n";
			}

		//check for all required data
			if (strlen($greeting) == 0) { $msg .= $text['message-required']." ".$text['label-greeting']."<br>\n"; }
			if (!is_array($array['surveys'][0]['survey_questions']) || sizeof($array['surveys'][0]['survey_questions']) == 0) {
				$msg .= $text['message-required']." ".$text['label-survey-questions']."<br>\n"; 
			}
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
			$array['surveys'][0]['survey_uuid'] = $survey_uuid;
			$array['surveys'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['surveys'][0]['name'] = $name;
			$array['surveys'][0]['description'] = $description;
			$array['surveys'][0]['greeting'] = $greeting;
			$array['surveys'][0]['exit_file'] = $exit_file;
			$array['surveys'][0]['question_answered_file'] = $question_answered_file;
			$array['surveys'][0]['age_file'] = $age_file;
			$array['surveys'][0]['gender_file'] = $gender_file;
			$array['surveys'][0]['retake_file'] = $retake_file;
			$array['surveys'][0]['zip_code_file'] = $zip_code_file;
			$array['surveys'][0]['greeting_suffix'] = $greeting_suffix;
			$array['surveys'][0]['reason_file'] = $reason_file;
			$array['surveys'][0]['reason_0_file'] = $reason_0_file;
			$array['surveys'][0]['ask_reason_below'] = $ask_reason_below;
			$array['surveys'][0]['exit_action'] = $exit_action;
			$array['surveys'][0]['ask_only_odd_even'] = $ask_only_odd_even;



		//grant temporary permissions
			$p = permissions::new();
			$p->add('survey_question_add', 'temp');
			$p->add('survey_question_edit', 'temp');

		//save to the data
			$database = new database;
			$database->app_name = 'survey';
			$database->app_uuid = '32af1175-9f22-4073-9499-33b50bbddad5';
			$database->save($array);
			$message = $database->message;

		//remove temporary permissions
				$p->delete('survey_question_add', 'temp');
				$p->delete('survey_question_edit', 'temp');

		//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//remove checked questions
			if (
				$action == 'update'
				&& is_array($survey_questions_delete)
				&& @sizeof($survey_questions_delete) != 0
				) {
				$obj = new survey;
				$obj->survey_uuid = $survey_uuid;
				$obj->delete_questions($survey_questions_delete);
				//Need to reorder sequence_id
				$sql = "select * from v_survey_questions ";
				$sql .= "where domain_uuid = :domain_uuid ";
				$sql .= "and survey_uuid = :survey_uuid ";
				$sql .= "order by sequence_id asc ";
				$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
				$parameters['survey_uuid'] = $survey_uuid;
				$database = new database;
				$survey_questions = $database->select($sql, $parameters, 'all');
				foreach ($survey_questions as $i => $row) {
					$sql = "UPDATE v_survey_questions SET sequence_id = :sequence_id ";
					$sql .= "WHERE survey_question_uuid = :survey_question_uuid ";
					$sql .= "and domain_uuid = :domain_uuid ";
					$sql .= "and survey_uuid = :survey_uuid ";
					$parameters['sequence_id'] = $i + 1;
					$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
					$parameters['survey_uuid'] = $survey_uuid;
					$parameters['survey_question_uuid'] = $row['survey_question_uuid'];
					$database = new database;
					$database->select($sql, $parameters, 'all');
				}
				unset($sql, $parameters, $survey_questions);
			}

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					$_SESSION["message"] = $text['message-add'];
				}
				if ($action == "update") {
					$_SESSION["message"] = $text['message-update'];
				}
				header('Location: survey_edit.php?id='.$survey_uuid);
				return;
			}
	}



//add multi-lingual support
	$language = new text;
	$text = $language->get();

//initialize the destinations object
$destination = new destinations;

//pre-populate the form
	$sql = "SELECT * FROM v_surveys ";
	$sql .= "where survey_uuid = :survey_uuid ";
	$parameters['survey_uuid'] = $survey_uuid;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && sizeof($row) != 0) {
		$greeting = $row["greeting"];
		$name = $row["name"];
		$description = $row["description"];
		$exit_file = $row['exit_file'];
		$question_answered_file = $row['question_answered_file'];
		$age_file = $row['age_file'];
		$gender_file = $row['gender_file'];
		$retake_file = $row['retake_file'];
		$zip_code_file = $row['zip_code_file'];
		$greeting_suffix = $row['greeting_suffix'];
		$reason_file = $row['reason_file'];
		$reason_0_file = $row['reason_0_file'];
		$ask_reason_below = $row['ask_reason_below'];
		$exit_action = $row['exit_action'];
		$ask_only_odd_even = $row['ask_only_odd_even'];
	}
	unset($sql, $parameters, $row);

//Get the questions
	if (is_uuid($survey_uuid)) {
		$sql = "select * from v_survey_questions ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and survey_uuid = :survey_uuid ";
		$sql .= "order by sequence_id asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['survey_uuid'] = $survey_uuid;
		$database = new database;
		$survey_questions = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);
	}

//add an empty row to the options array
if (!is_array($survey_questions) || count($survey_questions) == 0) {
	$rows = 5;
	$sequence_id = 0;
	$show_destination_delete = false;
}
if (is_array($survey_questions) && count($survey_questions) > 0) {
	$rows = 1;
	$sequence_id = count($survey_questions)+1;
	$show_destination_delete = true;
}
for ($x = 0; $x < $rows; $x++) {
	$survey_questions[$sequence_id]['recording'] = '';
	$survey_questions[$sequence_id]['highest_number'] = '';
	$sequence_id++;
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
	$document['title'] = $text['title-survey_edit'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='form_list' method='post'>\n";
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Survey Config</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','icon'=>$_SESSION['theme']['button_icon_back'],'label'=>'Back','link'=>'survey_votes.php?id='.$survey_uuid]);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	//echo "<table class='list'>\n";
	//echo "<tr class='list-header'>\n";
	
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='name' maxlength='255' value=\"".escape($name)."\" required='required'>\n";
	echo "<br />\n";
	echo $text['description-name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='description' maxlength='255' value=\"".escape($description)."\">\n";
	echo "<br />\n";
	echo $text['description-survey_description']."\n";
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
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($greeting) > 0 && $greeting == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-greeting']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-greeting_suffix']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='greeting_suffix' id='greeting_suffix' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($greeting_suffix) > 0 && $greeting_suffix == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-greeting_suffix']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-retake_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='retake_file' id='retake_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($retake_file) > 0 && $retake_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-retake_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-age_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='age_file' id='age_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($age_file) > 0 && $age_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-age_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-gender_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='gender_file' id='gender_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($gender_file) > 0 && $gender_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-gender_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-zip_code_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='zip_code_file' id='zip_code_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($zip_code_file) > 0 && $zip_code_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-zip_code_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-question_answered_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='question_answered_file' id='question_answered_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($question_answered_file) > 0 && $question_answered_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-question_answered_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-reason_0_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='reason_0_file' id='reason_0_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($reason_0_file) > 0 && $reason_0_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-reason_0_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-ask_reason_below']."\n";
	echo "</td>\n";
	echo "				<td class='formfld'>\n";
	echo "					<select name=\"ask_reason_below\" class='formfld' style='width:55px'>\n";
	$i=0;
	while ($i <= 9) {
		if ($i == $ask_reason_below) {
			echo "				<option value='$i' selected='selected'>$i</option>\n";
		}
		else {
			echo "				<option value='$i'>$i</option>\n";
		}
		$i = $i + 1;
	}
	echo "					</select>\n";
	echo "				</td>\n";
	echo "<td>\n";
	echo $text['description-ask_reason_below']."\n";
	echo "</td>\n";
	echo "			</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-reason_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='reason_file' id='reason_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($reason_file) > 0 && $reason_file == $recording_filename) {
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
	echo "<td>\n";
	echo $text['description-reason_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-ask_only_odd_even']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='ask_only_odd_even' id='ask_only_odd_even'>\n";
	echo "    	<option value='false' ".(($ask_only_odd_even == "false") ? "selected='selected'" : null).">".$text['label-false']."</option>\n";
	echo "    	<option value='true' ".(($ask_only_odd_even == "true") ? "selected='selected'" : null).">".$text['label-true']."</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-ask_only_odd_even']."\n";
	echo "</td>\n";
	echo "</tr>\n";



	echo "	<tr>";
	echo "		<td class='vncellreq' valign='top'>".$text['label-survey-questions']."</td>";
	echo "		<td class='vtable' align='left'>";

	echo "			<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "				<tr>\n";
	echo "					<td class='vtable'>".$text['label-survey_sequence']."</td>\n";
	echo "					<td class='vtable'>".$text['label-survey-recording']."</td>\n";
	echo "					<td class='vtable'>Recording Suffix</td>\n";
	echo "					<td class='vtable'>".$text['label-survey-highest-number']."</td>\n";
	echo "					<td class='vtable'>".$text['label-survey-description']."</td>\n";

	if ($show_destination_delete) {
		echo "					<td class='vtable edit_delete_checkbox_all' onmouseover=\"swap_display('delete_label_destinations', 'delete_toggle_destinations');\" onmouseout=\"swap_display('delete_label_destinations', 'delete_toggle_destinations');\">\n";
		echo "						<span id='delete_label_destinations'>".$text['label-delete']."</span>\n";
		echo "						<span id='delete_toggle_destinations'><input type='checkbox' id='checkbox_all_destinations' name='checkbox_all' onclick=\"edit_all_toggle('destinations');\"></span>\n";
		echo "					</td>\n";
	}
	echo "				</tr>\n";
	$x = 0;
	foreach ($survey_questions as $row) {
		if (strlen($row['recording']) == 0) { $row['recording'] = ""; }
		if (strlen($row['highest_number']) == 0) { $row['highest_number'] = "5"; }
		$row['sequence_id'] = $x + 1 ;

		if (strlen($row['survey_question_uuid']) > 0) {
			echo "		<input name=\"survey_questions[".$x."][survey_question_uuid]\" type='hidden' value=\"".escape($row['survey_question_uuid'])."\">\n";
		}
		echo "			<tr>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "		<input class=\"formfld\" style=\"width: 50px; text-align: center;\" name=\"survey_questions[".$x."][sequence_id]\" value=\"".escape($row['sequence_id'])."\">\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "<select name=\"survey_questions[".$x."][recording]\" class='formfld' style=\"width: 250px;\">\n";
		echo "	<option></option>\n";
			//recordings
			if (is_array($recordings)) {
				echo "<optgroup label='Recordings'>\n";
				foreach ($recordings as $recording) {
					$recording_name = $recording["recording_name"];
					$recording_filename = $recording["recording_filename"];
					if (strlen($row['recording']) > 0 && $row['recording'] == $recording_filename) {
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

		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "<select name=\"survey_questions[".$x."][recording_suffix]\" class='formfld' style=\"width: 250px;\">\n";
		echo "	<option></option>\n";
			//recordings
			if (is_array($recordings)) {
				echo "<optgroup label='Recordings'>\n";
				foreach ($recordings as $recording) {
					$recording_name = $recording["recording_name"];
					$recording_filename = $recording["recording_filename"];
					if (strlen($row['recording_suffix']) > 0 && $row['recording_suffix'] == $recording_filename) {
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

		echo "				<td class='formfld'>\n";
		echo "					<select name=\"survey_questions[".$x."][highest_number]\" class='formfld' style='width:55px'>\n";
		$i=0;
		while ($i <= 9) {
			if ($i == $row['highest_number']) {
				echo "				<option value='$i' selected='selected'>$i</option>\n";
			}
			else {
				echo "				<option value='$i'>$i</option>\n";
			}
			$i = $i + 1;
		}
		echo "					</select>\n";
		echo "				</td>\n";

		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "		<input class=\"formfld\" style=\"width: 250px; \" name=\"survey_questions[".$x."][description]\"' value=\"".escape($row['description'])."\">\n";
		echo "</td>\n";

		if ($show_destination_delete) {
			if (!empty($row['survey_question_uuid'])) {
				echo "			<td class='vtable' style='text-align: center; padding-bottom: 3px;'>";
				echo "				<input type='checkbox' name='survey_questions_delete[".$x."][checked]' value='true' class='chk_delete checkbox_questions' onclick=\"edit_delete_action('questions');\">\n";
				echo "				<input type='hidden' name='survey_questions_delete[".$x."][survey_question_uuid]' value='".escape($row['survey_question_uuid'])."' />\n";
			}
			else {
				echo "			<td>\n";
			}
			echo "			</td>\n";
		}
		echo "			</tr>\n";
		$x++;
	}
	echo "			</table>\n";
	echo "			".$text['description-survey-questions']."\n";
	echo "			<br />\n";
	echo "		</td>";
	echo "	</tr>";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-exit_file']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='exit_file' id='exit_file' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($exit_file) > 0 && $exit_file == $recording_filename) {
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
	echo $text['description-exit_file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "    ".$text['label-exit_action']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo $destination->select('dialplan', 'exit_action', $exit_action);
	echo "	<br />\n";
	echo "	".$text['description-exit_action']."\n";
	echo "</td>\n";
	echo "</tr>\n";


	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='survey_uuid' value='".escape($survey_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
