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
		$greeting = $_POST["greeting"];
		$exit_file = $_POST["exit_file"];
		$exit_action = $_POST["exit_action"];
		$description = $_POST["description"];
		$name = $_POST["name"];
		$survey_questions = $_POST["survey_questions"];
		$survey_questions_delete = $_POST["survey_questions_delete"];
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

		//prepare the recordings array
			if (is_array($survey_questions)) {
				foreach ($survey_questions as $i => $r) {
					if (strlen($r['recording']) > 0) {
						if (is_uuid($r['circle_survey_question_uuid'])) {
							$circle_survey_question_uuid = $r['circle_survey_question_uuid'];
						}
						else {
							$circle_survey_question_uuid = uuid();
						}
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['circle_survey_uuid'] = $circle_survey_uuid;
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['circle_survey_question_uuid'] = $circle_survey_question_uuid;
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['domain_uuid'] = $_SESSION["domain_uuid"];
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['sequence_id'] = $r['sequence_id'];
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['recording'] = $r['recording'];
						$array['circle_surveys'][0]['circle_survey_questions'][$i]['highest_number'] = $r['highest_number'];
					}
				}
			}

		//check for all required data
			$msg = '';
			if (strlen($greeting) == 0) { $msg .= $text['message-required']." ".$text['label-greeting']."<br>\n"; }
			if (!is_array($array['circle_surveys'][0]['circle_survey_questions']) || sizeof($array['circle_surveys'][0]['circle_survey_questions']) == 0) {
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
			$array['circle_surveys'][0]['circle_survey_uuid'] = $circle_survey_uuid;
			$array['circle_surveys'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['circle_surveys'][0]['name'] = $name;
			$array['circle_surveys'][0]['description'] = $description;
			$array['circle_surveys'][0]['greeting'] = $greeting;
			$array['circle_surveys'][0]['exit_file'] = $exit_file;
			$array['circle_surveys'][0]['exit_action'] = $exit_action;



		//grant temporary permissions
			$p = new permissions;
			$p->add('circle_survey_question_add', 'temp');
			$p->add('circle_survey_question_edit', 'temp');

		//save to the data
			$database = new database;
			$database->app_name = 'circle_survey';
			$database->app_uuid = '32af1175-9f22-4073-9499-33b50bbddad5';
			$database->save($array);
			$message = $database->message;

		//remove temporary permissions
				$p->delete('circle_survey_question_add', 'temp');
				$p->delete('circle_survey_question_edit', 'temp');

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
				$obj = new circle_survey;
				$obj->circle_survey_uuid = $circle_survey_uuid;
				$obj->delete_questions($survey_questions_delete);
				//Need to reorder sequence_id
				$sql = "select * from v_circle_survey_questions ";
				$sql .= "where domain_uuid = :domain_uuid ";
				$sql .= "and circle_survey_uuid = :circle_survey_uuid ";
				$sql .= "order by sequence_id asc ";
				$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
				$parameters['circle_survey_uuid'] = $circle_survey_uuid;
				$database = new database;
				$survey_questions = $database->select($sql, $parameters, 'all');
				foreach ($survey_questions as $i => $row) {
					$sql = "UPDATE v_circle_survey_questions SET sequence_id = :sequence_id ";
					$sql .= "WHERE circle_survey_question_uuid = :circle_survey_question_uuid ";
					$sql .= "and domain_uuid = :domain_uuid ";
					$sql .= "and circle_survey_uuid = :circle_survey_uuid ";
					$parameters['sequence_id'] = $i + 1;
					$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
					$parameters['circle_survey_uuid'] = $circle_survey_uuid;
					$parameters['circle_survey_question_uuid'] = $row['circle_survey_question_uuid'];
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
				header('Location: circle_survey_edit.php?id='.$circle_survey_uuid);
				return;
			}
	}



//add multi-lingual support
	$language = new text;
	$text = $language->get();

//initialize the destinations object
$destination = new destinations;

//pre-populate the form
	$sql = "SELECT * FROM v_circle_surveys ";
	$sql .= "where circle_survey_uuid = :circle_survey_uuid ";
	$parameters['circle_survey_uuid'] = $circle_survey_uuid;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && sizeof($row) != 0) {
		$greeting = $row["greeting"];
		$name = $row["name"];
		$description = $row["description"];
		$exit_file = $row['exit_file'];
		$exit_action = $row['exit_action'];
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
	$document['title'] = $text['title-circle_survey_edit'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='form_list' method='post'>\n";
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Survey Config</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','icon'=>$_SESSION['theme']['button_icon_back'],'label'=>'Back','link'=>'circle_survey_votes.php?id='.$circle_survey_uuid]);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	
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
	echo "<br />\n";
	echo $text['description-greeting']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>";
	echo "		<td class='vncellreq' valign='top'>".$text['label-survey-questions']."</td>";
	echo "		<td class='vtable' align='left'>";

	echo "			<table border='0' cellpadding='0' cellspacing='0'>\n";
	echo "				<tr>\n";
	echo "					<td class='vtable'>".$text['label-circle_survey_sequence']."</td>\n";
	echo "					<td class='vtable'>".$text['label-survey-recording']."</td>\n";
	echo "					<td class='vtable'>".$text['label-survey-highest-number']."</td>\n";

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
		if (strlen($row['highest_number']) == 0) { $row['highest_number'] = "9"; }
		$row['sequence_id'] = $x + 1 ;

		if (strlen($row['circle_survey_question_uuid']) > 0) {
			echo "		<input name=\"survey_questions[".$x."][circle_survey_question_uuid]\" type='hidden' value=\"".escape($row['circle_survey_question_uuid'])."\">\n";
		}
		echo "			<tr>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "		<input class=\"formfld\" style=\"width: 50px; text-align: center;\" name=\"survey_questions[".$x."][sequence_id]\" readonly=\"readonly\"' value=\"".escape($row['sequence_id'])."\">\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "<select name=\"survey_questions[".$x."][recording]\" class='formfld'>\n";
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

		if ($show_destination_delete) {
			if (!empty($row['circle_survey_question_uuid'])) {
				echo "			<td class='vtable' style='text-align: center; padding-bottom: 3px;'>";
				echo "				<input type='checkbox' name='survey_questions_delete[".$x."][checked]' value='true' class='chk_delete checkbox_questions' onclick=\"edit_delete_action('questions');\">\n";
				echo "				<input type='hidden' name='survey_questions_delete[".$x."][circle_survey_question_uuid]' value='".escape($row['circle_survey_question_uuid'])."' />\n";
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
		echo "<input type='hidden' name='circle_survey_uuid' value='".escape($circle_survey_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
