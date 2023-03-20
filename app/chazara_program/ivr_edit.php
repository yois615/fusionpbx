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
	Portions created by the Initial Developer are Copyright (C) 2008-2022
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('chazara_ivr_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the http values and set them as php variables
	if (count($_POST) > 0) {

		//get the values from the HTTP POST and save them as PHP variables
			if (empty($_POST["chazara_ivr_uuid"])){
				$chazara_ivr_uuid = uuid();
			} else {
				$chazara_ivr_uuid = $_POST["chazara_ivr_uuid"];
			}
			$ivr_greeting_recording = $_POST["ivr_greeting_recording"];
			$grade_recording = $_POST["grade_recording"];
			$parallel_class_recordings = $_POST["parallel_class_recordings"];
	}


//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//set the domain_uuid
			$domain_uuid = $_SESSION['domain_uuid'];
			

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: recordings.php');
				exit;
			}

		//check for all required data
			$msg = '';
			 if (strlen($ivr_greeting_recording) == 0) { $msg .= $text['message-required'].$text['label-ivr-main-greeting']."<br>\n"; }
			 if (strlen($grade_recording) == 0) { $msg .= $text['message-required'].$text['label-ivr-grade_greeting']."<br>\n"; }


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

		//add or update the database
			if ($_POST["persistformvar"] != "true") {

				//create the data array
					$array["chazara_ivrs"][0]["chazara_ivr_uuid"] = $chazara_ivr_uuid;
					$array["chazara_ivrs"][0]["domain_uuid"] = $domain_uuid;
					$array["chazara_ivrs"][0]["greeting_recording"] = $ivr_greeting_recording;
					$array["chazara_ivrs"][0]["grade_recording"] = $grade_recording;

				//prepare the parallels array
					if (is_array($parallel_class_recordings)) {
						foreach ($parallel_class_recordings as $i => $r) {
							if (strlen($r['recording']) > 0) {
								if (is_uuid($r['chazara_ivr_recording_uuid'])) {
									$chazara_ivr_recording_uuid = $r['chazara_ivr_recording_uuid'];
								}
								else {
									$chazara_ivr_recording_uuid = uuid();
								}
								$array['chazara_ivrs'][0]['chazara_ivr_recordings'][$i]['chazara_ivr_recording_uuid'] = $chazara_ivr_recording_uuid;
								$array['chazara_ivrs'][0]['chazara_ivr_recordings'][$i]["chazara_ivr_uuid"] = $chazara_ivr_uuid;
								$array['chazara_ivrs'][0]['chazara_ivr_recordings'][$i]['domain_uuid'] = $_SESSION["domain_uuid"];
								$array['chazara_ivrs'][0]['chazara_ivr_recordings'][$i]['grade'] = $i;
								$array['chazara_ivrs'][0]['chazara_ivr_recordings'][$i]['recording'] = $r['recording'];
							}
						}
					}

// print_r($array); exit;

				//grant temporary permissions
					$p = new permissions;
					$p->add('chazara_ivr_recording_add', 'temp');
					$p->add('chazara_ivr_recording_edit', 'temp');

				//save to the data
					$database = new database;
					$database->app_name = 'chazara_program';
					$database->app_uuid = '37a9d861-c7a2-9e90-925d-29e3c2e0b60e';
					$database->save($array);
					$message = $database->message;
					unset($array);

				//remove temporary permissions
					$p->delete('chazara_ivr_recording_add', 'temp');
					$p->delete('chazara_ivr_recording_edit', 'temp');

				//set the message and redirect

					message::add($text['message-update']);

					header("Location: recordings.php");
					exit;
			}
	}

//pre-populate the form
	if ($_POST["persistformvar"] != "true") {
		$sql = "select * from v_chazara_ivrs ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$chazara_ivr_uuid = $row['chazara_ivr_uuid'];
			$ivr_greeting_recording = $row['greeting_recording'];
			$grade_recording = $row['grade_recording'];
		}
		unset($sql, $parameters, $row);

    }


//set the defaults
	if (!is_uuid($chazara_ivr_uuid)) {
		$chazara_ivr_uuid = uuid();
	}


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//get the list of parallel recodings for this ivr
	$sql = "select * from v_chazara_ivr_recordings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "AND chazara_ivr_uuid = :chazara_ivr_uuid ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$parameters['chazara_ivr_uuid'] = $chazara_ivr_uuid;
	$database = new database;
	$parallel_class_recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

// Get the list of parallel classes that need a recording
	$sql = "select grade from v_chazara_teachers ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "GROUP BY grade ";
	$sql .= "HAVING COUNT(grade) > 1";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$parallel_grades = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the recordings
    $sql = "select recording_name, recording_filename from v_recordings ";
    $sql .= "where domain_uuid = :domain_uuid ";
    $sql .= "order by recording_name asc ";
    $parameters['domain_uuid'] = $_SESSION['domain_uuid'];
    $database = new database;
    $recordings = $database->select($sql, $parameters, 'all');
    unset($sql, $parameters);

// print_r($recordings); exit;

//begin the page content
	require_once "resources/header.php";

	$document['title'] = $text['title-ivr-edit'];


	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	echo "<b>".$text['header-ivr-edit']."</b>";
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'recordings.php'.(is_numeric($page) ? '?page='.$page : null)]);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	//Main Greeting
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-ivr-main-greeting']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "<select name='ivr_greeting_recording' id='ivr_greeting_recording' class='formfld'>\n";
	echo "	<option></option>\n";
	//recordings
		$tmp_selected = false;
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if ($ivr_greeting_recording == $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$recording_filename && strlen($ivr_greeting_recording) > 0) {
					$tmp_selected = true;
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else if ($ivr_greeting_recording == $recording_filename && strlen($ivr_greeting_recording) > 0) {
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
	echo "	<br />\n";
	echo $text['description-greet_long']."\n";
	echo "</td>\n";
	echo "</tr>\n";

    // Grade Menu recording
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-ivr-grade_greeting']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "<select name='grade_recording' id='grade_recording' class='formfld'>\n";
		echo "	<option></option>\n";
		//recordings
			$tmp_selected = false;
			if (is_array($recordings)) {
				echo "<optgroup label='Recordings'>\n";
				foreach ($recordings as $row) {
					$recording_name = $row["recording_name"];
					$recording_filename = $row["recording_filename"];
					if ($grade_recording == $recording_filename && strlen($grade_recording) > 0) {
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
		echo "<br />\n";
		echo $text['description-grade']."\n";
		echo "</td>\n";
		echo "</tr>\n";

		//Parallel class recordings
		if (is_array($parallel_grades)) {
			foreach($parallel_grades as $pg) {
				$parallel_recording = '';
				echo "<tr>\n";
				echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
				echo "    ".$text['label-grade']." ".$pg["grade"]."\n";
				echo "</td>\n";
				echo "<td class='vtable' align='left'>\n";
				// Set row value
				$select_name = "\"parallel_class_recordings[".$pg["grade"]."][recording]\"";
				//Prepopluate data
				foreach($parallel_class_recordings as $pr){
					if ($pr['grade'] == $pg['grade']) {
						$parallel_recording = $pr['recording'];
						$chazara_ivr_recording_uuid = $pr['chazara_ivr_recording_uuid'];
						break;
					}
				}
				echo "<select name=".$select_name." id=".$select_name." class='formfld'>\n";
				echo "	<option></option>\n";
				//recordings
					$tmp_selected = false;
					if (is_array($recordings)) {
						echo "<optgroup label='Recordings'>\n";
						foreach ($recordings as $row) {
							$recording_name = $row["recording_name"];
							$recording_filename = $row["recording_filename"];
							if ($parallel_recording == $recording_filename && strlen($parallel_recording) > 0) {
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
				if (strlen($chazara_ivr_recording_uuid) > 0) {
					echo "		<input name=\"parallel_class_recordings[".$pg["grade"]."][chazara_ivr_recording_uuid]\" type='hidden' value=\"".escape($chazara_ivr_recording_uuid)."\">\n";
				}
				echo "<br />\n";
				echo $text['description-grade']."\n";
				echo "</td>\n";
				echo "</tr>\n";
			}
		}


	echo "</table>";
	echo "<br><br>";

	if (is_numeric($page)) {
		echo "<input type='hidden' name='page' value='".$page."'>\n";
	}
	echo "<input type='hidden' name='chazara_ivr_uuid' value='".escape($chazara_ivr_uuid)."'>\n";
	if (!permission_exists('extension_domain')) {
		echo "<input type='hidden' name='domain_uuid' id='domain_uuid' value='".$_SESSION['domain_uuid']."'>";
	}
	
	
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
