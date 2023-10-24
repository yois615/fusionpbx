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
	James Rose <james.o.rose@gmail.com>
*/

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('chazara_recording_add') || permission_exists('chazara_recording_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get recording id
	//set the action as an add or an update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$recording_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}


//get the form value and set to php variables
	if (count($_POST) > 0) {
		//Get teacher's uuid

		// If we're show all, we don't know what teacher, so we base off the recording
		if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
			if ($action == 'add') {
				echo "You cannot add a recording as an administrator.<br>We don't know what teacher to assign it to.<br>";
				exit;
			}

			$sql = "select chazara_teacher_uuid ";
			$sql .= "from v_chazara_recordings ";
			$sql .= "WHERE chazara_recording_uuid = :chazara_recording_uuid ";
			$parameters['chazara_recording_uuid'] = $_GET['id'];
			
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && @sizeof($row) != 0) {
				$chazara_teacher_uuid = $row['chazara_teacher_uuid'];
			}
			unset($sql, $parameters, $row);
		} else {
			// If we're not show all, we know the teacher from the session
			$sql = "select chazara_teacher_uuid ";
			$sql .= "from v_chazara_teachers ";
			$sql .= "WHERE domain_uuid = :domain_uuid ";
			$sql .= "and user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
			$parameters['domain_uuid'] = $domain_uuid;
			
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && @sizeof($row) != 0) {
				$chazara_teacher_uuid = $row['chazara_teacher_uuid'];
			}
			unset($sql, $parameters, $row);
		}
		$recording_uuid = $_POST["chazara_recording_uuid"];
		$recording_filename = $_POST["recording_filename"];
		$recording_filename_original = $_POST["recording_filename_original"];
		$recording_id = $_POST["recording_id"];
		$chazara_teacher_uuid = $chazara_teacher_uuid;
		$recording_name = $_POST["recording_name"];
		$recording_description = $_POST["recording_description"];
		$daf_number = $_POST["daf_number"];
		$daf_amud = $_POST["daf_amud"];
		$daf_start_line = $_POST["daf_start_line"];
		$daf_end_line = $_POST["daf_end_line"];
		$recording_length = 0;
		$uploaded = 1;
		if(!file_exists($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
			$uploaded = 0;
		}
		if ($uploaded) {
			//remove special characters
			$recording_filename = str_replace(" ", "_", $_FILES['file']['name']);
			$recording_filename = str_replace("'", "", $recording_filename);

			//sanitize recording filename and name
			$recording_filename_ext = strtolower(pathinfo($recording_filename, PATHINFO_EXTENSION));
			if (!in_array($recording_filename_ext, ['wav','mp3','ogg'])) {
				$recording_filename = pathinfo($recording_filename, PATHINFO_FILENAME);
				$recording_filename = str_replace('.', '', $recording_filename);
			}
			$recording_filename = str_replace("\\", '', $recording_filename);
			$recording_filename = str_replace('/', '', $recording_filename);
			$recording_filename = str_replace('..', '', $recording_filename);
			$recording_filename = str_replace(' ', '_', $recording_filename);
			$recording_filename = str_replace("'", '', $recording_filename);
			$recording_filename = str_replace("(", '_', $recording_filename);
			$recording_filename = str_replace(")", '_', $recording_filename);
			$recording_filename = str_replace(".WAV", '.wav', $recording_filename);
			$recording_name = str_replace("'", '', $recording_name);

			//make sure the destination directory exists
			$file_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid;
			$sox_file_dir = $file_dir.'/sox';
			if (!is_dir($file_dir)) {
				mkdir($file_dir, 0770, false);
			}
			if (!is_dir($sox_file_dir)) {
				mkdir($sox_file_dir, 0770, false);
			}

			$file_name = $file_dir.'/'.pathinfo($recording_filename, PATHINFO_FILENAME).'.wav';
			$sox_file_name = $sox_file_dir.'/'.$recording_filename;

			//move the uploaded files
			$result = move_uploaded_file($_FILES['file']['tmp_name'], $sox_file_name);

			exec('sox '.$sox_file_name.' -b 16 -r 16000 -c 1 '.$file_name, $out, $return_code);

			//Delete sox tmp file
			unlink($sox_file_name);

			if ($return_code != 0) {
				message::add('Uploaded file in unsupported audio format','negative');
				$header = 'Location: recordings.php';
				if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
					$header .= "?show=all";
				}
				header($header);
				exit;
			}

			//We've changed the filename, use the new one
			$recording_filename = pathinfo($recording_filename, PATHINFO_FILENAME).'.wav';

			//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

			$recording_length = ceil(shell_exec('soxi -D '.$file_name));
		} else {
			$file_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid.'/';
			$recording_length = ceil(shell_exec('soxi -D '.$file_dir.$recording_filename_original));
		}
	}

	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
	//get recording uuid to edit
		$recording_uuid = $_POST["recording_uuid"];
		if (!isset($recording_uuid)) {
			$recording_uuid = uuid();
		}
	//delete the recording
		if (permission_exists('chazara_recording_delete')) {
			if ($_POST['action'] == 'delete' && is_uuid($recording_uuid)) {
				//prepare
					$array[0]['checked'] = 'true';
					$array[0]['uuid'] = $recording_uuid;
				//delete
					$obj = new chazara_program;
					$obj->delete($array);
				//redirect
					$header = 'Location: recordings.php';
					if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
						$header .= "?show=all";
					}
					header($header);
					exit;
			}
		}

	//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			$header = 'Location: recordings.php';
			if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
				$header .= "?show=all";
			}
			header($header);
			exit;
		}

	//check for all required data
		$msg = '';
		if (strlen($recording_filename) == 0) { $msg .= $text['label-edit-file']."<br>\n"; }
		if (strlen($recording_name) == 0) { $msg .= $text['label-edit-recording']."<br>\n"; }
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

	//update the database
	if ($_POST["persistformvar"] != "true") {
		if (permission_exists('chazara_recording_edit')) {
			//if file name is not the same then rename the file
				if ($recording_filename != $recording_filename_original) {
					rename($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid.'/'.$recording_filename_original, $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid.'/'.$recording_filename);
				}

			//build array
				if ($action == 'add') {
					$recording_uuid = uuid();
				}
				$array['chazara_recordings'][0]['chazara_recording_uuid'] = $recording_uuid;
				$array['chazara_recordings'][0]['domain_uuid'] = $domain_uuid;
				$array['chazara_recordings'][0]['recording_id'] = $recording_id;
				$array['chazara_recordings'][0]['daf_number'] = $daf_number;
				$array['chazara_recordings'][0]['daf_amud'] = $daf_amud;
				$array['chazara_recordings'][0]['daf_start_line'] = $daf_start_line;
				$array['chazara_recordings'][0]['daf_end_line'] = $daf_end_line;
				$array['chazara_recordings'][0]['chazara_teacher_uuid'] = $chazara_teacher_uuid;
				$array['chazara_recordings'][0]['recording_name'] = $recording_name;
				$array['chazara_recordings'][0]['recording_filename'] = $recording_filename;
				$array['chazara_recordings'][0]['recording_description'] = $recording_description;
				$array['chazara_recordings'][0]['length'] = $recording_length;
			//execute update
				$database = new database;
				$database->app_name = 'chazara_program';
				$database->app_uuid = '37a9d861-c7a2-9e90-925d-29e3c2e0b60e';
				$database->save($array);
				unset($array);
				// print_r($database->message);
			//set message
				message::add($text['message-update']);

			//redirect
			$header = 'Location: recordings.php';
			if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
				$header .= "?show=all";
			}
			header($header);
			exit;
		}
	}
}

//pre-populate the form
	if (count($_GET)>0 && $_POST["persistformvar"] != "true") {
		$recording_uuid = $_GET["id"];
		$sql = "select * from v_chazara_recordings ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and chazara_recording_uuid = :chazara_recording_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['chazara_recording_uuid'] = $recording_uuid;

		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$recording_filename = $row["recording_filename"];
			$recording_name = $row["recording_name"];
			$recording_description = $row["recording_description"];
			$recording_id = $row['recording_id'];
			$daf_number = $row['daf_number'];
			$daf_amud = $row['daf_amud'];
			$daf_start_line = $row['daf_start_line'];
			$daf_end_line = $row['daf_end_line'];
		}
		unset($sql, $parameters, $row);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-edit'];
	require_once "resources/header.php";

//file type check script
	// echo "<script language='JavaScript' type='text/javascript'>\n";
	// echo "	function check_file_type(file_input) {\n";
	// echo "		file_ext = file_input.value.substr((~-file_input.value.lastIndexOf('.') >>> 0) + 2);\n";
	// echo "		if (file_ext != 'mp3' && file_ext != 'wav' && file_ext != 'ogg' && file_ext != '') {\n";
	// echo "			display_message(\"".$text['message-unsupported_file_type']."\", 'negative', '2750');\n";
	// echo "		}\n";
	// echo "	}\n";
	// echo "</script>";

//show the content
	// echo "<form name='frm' id='frm' method='post'>\n";
	echo 	"<form name='frm' id='frm' method='post' enctype='multipart/form-data'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-edit']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'recordings.php']);
	if (permission_exists('chazara_recording_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('chazara_recording_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "    ".$text['label-recording_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "    <input class='formfld' type='text' name='recording_name' maxlength='255' value=\"".escape($recording_name)."\">\n";
	echo "<br />\n";
	echo $text['description-recording']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "    ".$text['label-file_name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "    <input class='formfld' type='text' name='recording_filename' maxlength='255' value=\"".escape($recording_filename)."\">\n";
	echo "    <input type='hidden' name='recording_filename_original' value=\"".escape($recording_filename)."\">\n";
	echo "<br />\n";
	echo $text['message-file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if ($_SESSION['chazara']['daf_mode']['boolean'] == "true") {

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "    ".$text['label-daf_number']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='number' name='daf_number' maxlength='255' value=\"".escape($daf_number)."\">\n";
		echo "<br />\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "    ".$text['label-daf_amud']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='daf_amud' id='daf_amud'>\n";
		echo "    	<option value='a' ".($daf_amud == 'a' ? "selected" : "").">a</option>\n";
		echo "    	<option value='b' ".($daf_amud == 'b' ? "selected" : "").">b</option>\n";
		echo "     </select>\n";
		echo "<br />\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "    Daf Starting Line\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='number' name='daf_start_line' maxlength='255' value=\"".escape($daf_start_line)."\">\n";
		echo "<br />\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "    Daf Ending Line\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='number' name='daf_end_line' maxlength='255' value=\"".escape($daf_end_line)."\">\n";
		echo "<br />\n";
		echo "</td>\n";
		echo "</tr>\n";
	} else {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "    ".$text['label-recording_id']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='recording_id' maxlength='255' value=\"".escape($recording_id)."\">\n";
		echo "<br />\n";
		echo $text['description-recording_id']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if ($recording_filename == null || strlen(trim($recording_filename)) == 0) {
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
		echo "    ".$text['label-file_upload']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo 		"<input type='text' class='txt' style='width: 100px; cursor: pointer;' id='filename' placeholder='Select...' onclick=\"document.getElementById('ulfile').click(); this.blur();\" onfocus='this.blur();'>";
		echo 		"<input type='file' id='ulfile' name='file' style='display: none;' accept='.wav,.mp3,.ogg' onchange=\"document.getElementById('filename').value = this.files.item(0).name; check_file_type(this);\">";
		echo "<br />\n";
		echo $text['message-file_upload']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "    Description\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "    <input class='formfld' type='text' name='recording_description' maxlength='255' value=\"".escape($recording_description)."\">\n";
	echo "<br />\n";
	echo $text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	echo "<input type='hidden' name='recording_uuid' value='".escape($recording_uuid)."'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo 	"<input name='a' type='hidden' value='upload'>\n";
	echo 	"<input name='type' type='hidden' value='rec'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
