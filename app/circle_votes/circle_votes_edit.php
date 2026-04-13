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
	if (permission_exists('circle_votes_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//get the vote_id
	$vote_id = $_GET["vote_id"];
	if (empty($vote_id)) {
		echo "missing vote_id";
		exit;
	}

	//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$question_audio = $_POST["question_audio"];
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {


		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: circle_votes.php?vote_id='.$vote_id);
				exit;
			}


		//prepare the recordings array
			$msg = '';

		//check for all required data
			if (strlen($question_audio) == 0) { $msg .= $text['message-required']." ".$text['label-question_audio']."<br>\n"; }
			
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
            $sql = "select circle_tt_vote_audio_uuid from v_circle_tt_vote_audios ";
            $sql .= "where domain_uuid = :domain_uuid ";
            $sql .= "and vote_id = :vote_id ";
            $parameters['domain_uuid'] = $_SESSION['domain_uuid'];
            $parameters['vote_id'] = $vote_id;
            $database = new database;
            $circle_tt_vote_audio_uuid = $database->select($sql, $parameters, 'all');
            unset($sql, $parameters);

            $array['circle_tt_vote_audios'][0]['circle_tt_vote_audio_uuid'] = $circle_tt_vote_audio_uuid;
			$array['circle_tt_vote_audios'][0]['vote_id'] = $vote_id;
			$array['circle_tt_vote_audios'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['circle_tt_vote_audios'][0]['question_audio'] = $question_audio;

		//grant temporary permissions
			$p = new permissions;
			$p->add('circle_tt_vote_audios_add', 'temp');
			$p->add('circle_tt_vote_audios_edit', 'temp');

		//save to the data
			$database = new database;
			$database->app_name = 'circle_votes';
			$database->app_uuid = '32af1175-9f22-4073-9499-33b50bbddad5';
			$database->save($array);
			$message = $database->message;

		//remove temporary permissions
				$p->delete('circle_tt_vote_audios_add', 'temp');
				$p->delete('circle_tt_vote_audios_edit', 'temp');

		//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//redirect the user
            $_SESSION["message"] = $text['message-update'];
            header('Location: circle_votes.php?vote_id='.$vote_id);
            return;
	}



//add multi-lingual support
	$language = new text;
	$text = $language->get();

//initialize the destinations object
$destination = new destinations;

//pre-populate the form
	$sql = "SELECT * FROM v_circle_tt_vote_audios ";
	$sql .= "where vote_id = :vote_id ";
	$parameters['vote_id'] = $vote_id;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && sizeof($row) != 0) {
		$question_audio = $row["question_audio"];
	}
	unset($sql, $parameters, $row);

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
	$document['title'] = $text['title-circle_votes_edit'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='form_list' method='post'>\n";
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Survey Config</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','icon'=>$_SESSION['theme']['button_icon_back'],'label'=>'Back','link'=>'circle_votes.php?vote_id='.$vote_id]);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	//echo "<table class='list'>\n";
	//echo "<tr class='list-header'>\n";
	
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-question_audio']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "<select name='question_audio' id='question_audio' class='formfld'>\n";
	echo "	<option></option>\n";
		//recordings
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (strlen($question_audio) > 0 && $question_audio == $recording_filename) {
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
	echo $text['description-question_audio']."\n";
	echo "</td>\n";
	echo "</tr>\n";

		echo "</table>";
	echo "<br /><br />";

	echo "<input type='hidden' name='vote_id' value='".escape($vote_id)."'>\n";
	
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
