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
	if (permission_exists('chazara_teacher_add') || permission_exists('chazara_teacher_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the action as an add or an update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$teacher_uuid = $_REQUEST["id"];
		$page = $_REQUEST['page'];
	}
	else {
		$action = "add";
	}

//get total extension count from the database, check limit, if defined
	if ($action == 'add') {
		if ($_SESSION['limit']['teachers']['numeric'] != '') {
			$sql = "select count(*) ";
			$sql .= "from v_chazara_daf_teachers ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database = new database;
			$total_extensions = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			if ($total_extensions >= $_SESSION['limit']['teachers']['numeric']) {
				message::add($text['message-maximum_teachers'].' '.$_SESSION['limit']['teachers']['numeric'], 'negative');
				header('Location: daf_teachers.php'.(is_numeric($page) ? '?page='.$page : null));
				exit;
			}
		}
	}

//get the http values and set them as php variables
	if (count($_POST) > 0) {

		//get the values from the HTTP POST and save them as PHP variables
            $teacher_uuid = $_REQUEST["id"];
			$name = $_POST["name"];
			$name_recording_path = $_POST["name_recording_path"];
	}

//delete the user from the v_extension_users

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//set the domain_uuid
			if (permission_exists('chazara_teacher_domain') && is_uuid($_POST["domain_uuid"])) {
				$domain_uuid = $_POST["domain_uuid"];
			}
			else {
				$domain_uuid = $_SESSION['domain_uuid'];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: daf_teachers.php');
				exit;
			}

		//check for all required data
			$msg = '';
			 if (strlen($name) == 0) { $msg .= $text['message-required'].$text['label-name']."<br>\n"; }
            if (strlen($name_recording_path) == 0) { $msg .= $text['message-required'].'Name recording path'."<br>\n"; }

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

				//build the data array
				//extension does not exist add it
					if ($action == "add") {
						$teacher_uuid = uuid();
					}

				//create the data array
					$array["chazara_daf_teachers"][$i]["chazara_daf_teacher_uuid"] = $teacher_uuid;
					$array["chazara_daf_teachers"][$i]["domain_uuid"] = $domain_uuid;
					$array["chazara_daf_teachers"][$i]["name"] = $name;
					$array["chazara_daf_teachers"][$i]["name_recording_path"] = $name_recording_path;

// print_r($array); exit;
				//grant temporary permissions
					$p = new permissions;
					$p->add('chazara_daf_teacher_add', 'temp');

				//save to the data
					$database = new database;
					$database->app_name = 'chazara_program';
					$database->app_uuid = '37a9d861-c7a2-9e90-925d-29e3c2e0b60e';
					$database->save($array);
					$message = $database->message;
					unset($array);

				//revoke temporary permissions
					$p->delete('chazara_daf_teacher_add', 'temp');
					
				//set the message and redirect
					if ($action == "add") {
						message::add($text['message-add']);
					}
					if ($action == "update") {
						message::add($text['message-update']);
					}
					header("Location: daf_teachers.php".(is_numeric($page) ? '?page='.$page : null));
					exit;
			}
	}

//pre-populate the form
	if (count($_GET) > 0 && $_POST["persistformvar"] != "true") {
		$teacher_uuid = $_GET["id"];
		$sql = "select * from v_chazara_daf_teachers ";
		$sql .= "where chazara_daf_teacher_uuid = :teacher_uuid ";
		$parameters['teacher_uuid'] = $teacher_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$domain_uuid = $row["domain_uuid"];
			$name = $row["name"];
			$name_recording_path = $row['name_recording_path'];
		}
		unset($sql, $parameters, $row);

    }


//set the defaults


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//get the recordings
    $sql = "select recording_name, recording_filename from v_recordings ";
    $sql .= "where domain_uuid = :domain_uuid ";
    $sql .= "order by recording_name asc ";
    $parameters['domain_uuid'] = $_SESSION['domain_uuid'];
    $database = new database;
    $recordings = $database->select($sql, $parameters, 'all');
    unset($sql, $parameters);

//begin the page content
	require_once "resources/header.php";
	if ($action == "update") {
	    $document['title'] = $text['title-teacher-edit'];
	}
	elseif ($action == "add") {
		$document['title'] = $text['title-teacher-add'];
	}

	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-extension-add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-extension-edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'daf_teachers.php'.(is_numeric($page) ? '?page='.$page : null)]);
	echo button::create(['type'=>'button','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;','onclick'=>'submit_form();']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

    // name
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-name']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='name' maxlength='255' value=\"".escape($name)."\">\n";
		echo "<br />\n";
		echo $text['description-name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
    
	// name recording path
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	"."Name recording file"."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "<select name='name_recording_path' id='name_recording_path' class='formfld'>\n";
	echo "	<option></option>\n";
	//recordings
		$tmp_selected = false;
		if (is_array($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as $row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if ($name_recording_path == $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$recording_filename && strlen($name_recording_path) > 0) {
					$tmp_selected = true;
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else if ($name_recording_path == $recording_filename && strlen($name_recording_path) > 0) {
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
	echo "Recording to play for this Rebbi in menu\n";
	echo "</td>\n";
	echo "</tr>\n";
	// }

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "    ".$text['label-description']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' align='left'>\n";
	// echo "    <input type='text' class='formfld' name='description' value=\"".$description."\">\n";
	// echo "<br />\n";
	// echo $text['description-description']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	echo "</table>";
	echo "<br><br>";

	if (is_numeric($page)) {
		echo "<input type='hidden' name='page' value='".$page."'>\n";
	}
	if ($action == "update") {
		echo "<input type='hidden' name='teacher_uuid' value='".escape($teacher_uuid)."'>\n";
		echo "<input type='hidden' name='id' id='id' value='".escape($teacher_uuid)."'>";
		if (!permission_exists('extension_domain')) {
			echo "<input type='hidden' name='domain_uuid' id='domain_uuid' value='".$_SESSION['domain_uuid']."'>";
		}
		echo "<input type='hidden' name='delete_type' id='delete_type' value=''>";
		echo "<input type='hidden' name='delete_uuid' id='delete_uuid' value=''>";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";
	echo "<script>\n";

//hide password fields before submit
	echo "	function submit_form() {\n";
	echo "		hide_password_fields();\n";
	echo "		$('form#frm').submit();\n";
	echo "	}\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
