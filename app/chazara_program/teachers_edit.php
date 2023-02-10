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
			$sql .= "from v_chazara_teachers ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database = new database;
			$total_extensions = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			if ($total_extensions >= $_SESSION['limit']['teachers']['numeric']) {
				message::add($text['message-maximum_teachers'].' '.$_SESSION['limit']['teachers']['numeric'], 'negative');
				header('Location: teachers.php'.(is_numeric($page) ? '?page='.$page : null));
				exit;
			}
		}
	}

//get the http values and set them as php variables
	if (count($_POST) > 0) {

		//get the values from the HTTP POST and save them as PHP variables
            $chazara_teacher_uuid = $id;
			$pin = $_POST["pin"];
			$grade = $_POST["grade"];
			$parallel_class_id = $_POST["parallel_class_id"];
			$enabled = $_POST["enabled"];
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
				header('Location: extensions.php');
				exit;
			}

		//check for all required data
			$msg = '';
			// if (strlen($extension) == 0) { $msg .= $text['message-required'].$text['label-extension']."<br>\n"; }
            if (strlen($enabled) == 0) { $msg .= $text['message-required'].$text['label-enabled']."<br>\n"; }

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
					$range = 1;
					$j = 0;
					for ($i=0; $i<$range; $i++) {

						//check if the extension exists
							if ($action == "add" && extension_exists($extension)) {
								//extension exists
							}
							else {

								//extension does not exist add it
									if ($action == "add" || $range > 1) {
										$teacher_uuid = uuid();
									}

								//create the data array
									$array["chazara_teachers"][$i]["domain_uuid"] = $domain_uuid;
									$array["chazara_teachers"][$i]["user_uuid"] = $_SESSION['user']['user_uuid'];
									$array["chazara_teachers"][$i]["pin"] = $pin;
									$array["chazara_teachers"][$i]["grade"] = $grade;
									$array["chazara_teachers"][$i]["parallel_class_id"] = $parallel_class_id;
									$array["chazara_teachers"][$i]["enabled"] = $enabled;

							}
					}

				//save to the data
					$database = new database;
					$database->app_name = 'chazara_program';
					$database->app_uuid = '37a9d861-c7a2-9e90-925d-29e3c2e0b60e';
					$database->save($array);
					$message = $database->message;
					unset($array);

				//set the message and redirect
					if ($action == "add") {
						message::add($text['message-add']);
					}
					if ($action == "update") {
						message::add($text['message-update']);
					}
					if ($range > 1) {
						header("Location: teachers.php");
					}
					else {
						header("Location: teachers_edit.php?id=".$teacher_uuid.(is_numeric($page) ? '&page='.$page : null));
					}
					exit;
			}
	}

//pre-populate the form
	if (count($_GET) > 0 && $_POST["persistformvar"] != "true") {
		$teacher_uuid = $_GET["id"];
		$sql = "select * from v_chazara_teachers ";
		$sql .= "where chazara_teacher_uuid = :chazara_teacher_uuid ";
		$parameters['chazara_teacher_uuid'] = $teacher_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$domain_uuid = $row["domain_uuid"];
			$user_uuid = $row["user_uuid"];
			$pin = $row["pin"];
			$grade = $row["grade"];
			$parallel_class_id = $row["parallel_class_id"];
			$enabled = $row["enabled"];
			// $description = $row["description"];
		}
		unset($sql, $parameters, $row);

    }


//set the defaults


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

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
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'teachers.php'.(is_numeric($page) ? '?page='.$page : null)]);
	echo button::create(['type'=>'button','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;','onclick'=>'submit_form();']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

    // pin
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-pin']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='pin' maxlength='255' value=\"".escape($pin)."\">\n";
		echo "<br />\n";
		echo $text['description-pin']."\n";
		echo "</td>\n";
		echo "</tr>\n";

    // Grade
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-grade']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='grade' maxlength='255' value=\"".escape($grade)."\">\n";
		echo "<br />\n";
		echo $text['description-grade']."\n";
		echo "</td>\n";
		echo "</tr>\n";

    // Parallel Class ID
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-parallel_class_id']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='parallel_class_id' maxlength='255' value=\"".escape($parallel_class_id)."\">\n";
		echo "<br />\n";
		echo $text['description-parallel_class_id']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	// if (permission_exists('extension_enabled')) {
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-enabled']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='enabled'>\n";
		if ($enabled == "true") {
			echo "    <option value='true' selected='selected'>".$text['label-true']."</option>\n";
		}
		else {
			echo "    <option value='true'>".$text['label-true']."</option>\n";
		}
		if ($enabled == "false") {
			echo "    <option value='false' selected='selected'>".$text['label-false']."</option>\n";
		}
		else {
			echo "    <option value='false'>".$text['label-false']."</option>\n";
		}
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-enabled']."\n";
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
		echo "<input type='hidden' name='chazara_teacher_uuid' value='".escape($chazara_teacher_uuid)."'>\n";
		echo "<input type='hidden' name='id' id='id' value='".escape($chazara_teacher_uuid)."'>";
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
