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
	if (permission_exists('recording_add') || permission_exists('recording_edit')) {
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
		$recording_uuid = $_POST["chazara_recording_uuid"];
		$recording_filename = $_POST["recording_filename"];
		$recording_filename_original = $_POST["recording_filename_original"];
		$chazara_teacher_uuid = $_POST["chazara_teacher_uuid"];
		$recording_name = $_POST["recording_name"];
		$recording_description = $_POST["recording_description"];
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
			$recording_name = str_replace("'", '', $recording_name);

			//make sure the destination directory exists
			$file_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid;
			if (!is_dir($file_dir)) {
				mkdir($file_dir, 0770, false);
			}

			$file_name = $file_dir.'/'.$recording_filename;

			//move the uploaded files
			$result = move_uploaded_file($_FILES['file']['tmp_name'], $file_name);

			//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

			$recording_length = ceil(shell_exec('soxi -D '.$file_name));
		}
	}

	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
	//get recording uuid to edit
		$recording_uuid = $_POST["recording_uuid"];
		if (!isset($recording_uuid)) {
			$recording_uuid = uuid();
		}
	//delete the recording
		if (permission_exists('recording_delete')) {
			if ($_POST['action'] == 'delete' && is_uuid($recording_uuid)) {
				//prepare
					$array[0]['checked'] = 'true';
					$array[0]['uuid'] = $recording_uuid;
				//delete
					$obj = new switch_recordings;
					$obj->delete($array);
				//redirect
					header('Location: recordings.php');
					exit;
			}
		}

	//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: recordings.php');
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
		if (permission_exists('recording_edit')) {
			//if file name is not the same then rename the file
				if ($recording_filename != $recording_filename_original) {
					rename($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$recording_filename_original, $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$recording_filename);
				}

			//build array
				if ($action == 'add') {
					$recording_uuid = uuid();
				}
				$array['chazara_recordings'][0]['chazara_recording_uuid'] = $recording_uuid;
				$array['chazara_recordings'][0]['domain_uuid'] = $domain_uuid;
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
				header("Location: recordings.php");
				exit;
		}
	}
}

//pre-populate the form
	if (count($_GET)>0 && $_POST["persistformvar"] != "true") {
		$recording_uuid = $_GET["id"];
		$sql = "select r.recording_name, r.recording_filename, r.recording_description, t.name, t.chazara_teacher_uuid  from v_chazara_recordings r ";
		$sql .= " left join v_chazara_teachers t ON r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
		$sql .= "where r.domain_uuid = :domain_uuid ";
		$sql .= "and r.chazara_recording_uuid = :chazara_recording_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['chazara_recording_uuid'] = $recording_uuid;

		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$chazara_teacher_uuid = $row["chazara_teacher_uuid"];
			$chazara_teacher_name = $row["name"];
			$recording_filename = $row["recording_filename"];
			$recording_name = $row["recording_name"];
			$recording_description = $row["recording_description"];
		}
		unset($sql, $parameters, $row);
	}

// get teacher list
	$sql = "select chazara_teacher_uuid, name from v_chazara_teachers ";
	$sql .= "where domain_uuid = :domain_uuid ";
	// $sql .= "and user_uuid = :user_uuid ";
	$parameters['domain_uuid'] = $domain_uuid;
	// $parameters['recording_uuid'] = $recording_uuid;
	$database = new database;
	$teachers = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

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
	if (permission_exists('recording_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('recording_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "    ".$text['label-teachers']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "    <select name='chazara_teacher_uuid' class='formfld' \">\n";
	foreach($teachers as $teacher) {
		if ($teacher['chazara_teacher_uuid'] == $row['chazara_teacher_uuid']) {
			echo " <option value=\"".$teacher['chazara_teacher_uuid']."\" selected='selected'>".$teacher['name']."</option>";
		} else {
			echo " <option value=\"".$teacher['chazara_teacher_uuid']."\">".$teacher['name']."</option>";
		}
	}
	echo "    </select>\n";
	echo "<br />\n";
	echo $text['description-teachers']."\n";
	echo "</td>\n";
	echo "</tr>\n";


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
	echo "    <input class='formfld' readonly type='text' name='recording_filename' maxlength='255' value=\"".escape($recording_filename)."\">\n";
	// echo "    <input type='hidden' name='recording_filename_original' value=\"".escape($recording_filename)."\">\n";
	echo escape($recording_filename);
	echo "<br />\n";
	echo $text['message-file']."\n";
	echo "</td>\n";
	echo "</tr>\n";

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
