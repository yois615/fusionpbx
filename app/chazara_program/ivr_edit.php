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
	if (permission_exists('chazara_ivr_add') || permission_exists('chazara_ivr_edit')) {
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
			$sql .= "from v_chazara_ivrs ";
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
            $ivr_uuid = $_REQUEST["id"];
			$teacher_uuid = $_POST["teacher_uuid"];
			$ivr_greeting_recording = $_POST["greeting_recording"];
			$grade_recording = $_POST["grade_recording"];
			$enabled = $_POST["enabled"];
	}

//delete the user from the v_extension_users

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//set the domain_uuid
			if (permission_exists('chazara_ivr_domain') && is_uuid($_POST["domain_uuid"])) {
				$domain_uuid = $_POST["domain_uuid"];
			}
			else {
				$domain_uuid = $_SESSION['domain_uuid'];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: ivrs.php');
				exit;
			}

		//check for all required data
			$msg = '';
			 if (strlen($greeting_recording) == 0) { $msg .= $text['message-required'].$text['label-ivr-main-greeting']."<br>\n"; }
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
				//extension does not exist add it
					if ($action == "add") {
						$teacher_uuid = uuid();
					}

				//create the data array
					$array["chazara_ivrs"][$i]["chazara_teacher_uuid"] = $teacher_uuid;
					$array["chazara_ivrs"][$i]["domain_uuid"] = $domain_uuid;
					$array["chazara_ivrs"][$i]["user_uuid"] = $_SESSION['user']['user_uuid'];
					$array["chazara_ivrs"][$i]["pin"] = $pin;
					$array["chazara_ivrs"][$i]["grade"] = $grade;
					$array["chazara_ivrs"][$i]["parallel_class_id"] = $parallel_class_id;
					$array["chazara_ivrs"][$i]["enabled"] = $enabled;
					$array["chazara_ivrs"][$i]["name"] = $name;
					$array["chazara_ivrs"][$i]["user_uuid"] = $user_uuid;

// print_r($array); exit;
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
					header("Location: teachers.php".(is_numeric($page) ? '?page='.$page : null));
					exit;
			}
	}

//pre-populate the form
	if (count($_GET) > 0 && $_POST["persistformvar"] != "true") {
		$teacher_uuid = $_GET["id"];
		$sql = "select * from v_chazara_ivrs ";
        $sql .= "join v_chazara_teachers on  ";
        $sql .= "v_chazara_teachers.chazara_teacher_uuid=v_chazara_ivrs.teacher_uuid ";
		$sql .= "where v_chazara_ivrs.chazara_ivr_uuid = :ivr_uuid ";
		$parameters['ivr_uuid'] = $ivr_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$domain_uuid = $row["domain_uuid"];
			$user_uuid = $row["_uuid"];
			$pin = $row["pin"];
			$grade = $row["grade"];
			$parallel_class_id = $row["parallel_class_id"];
			$enabled = $row["enabled"];
			$user_uuid = $row["user_uuid"];
			$name = $row["name"];
		}
		unset($sql, $parameters, $row);

    }


//set the defaults


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//get the list of teachers for this domain
	$sql = "select * from v_chazara_teachers ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by name asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$teachers = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the recordings
    $sql = "select recording_name, recording_filename from v_chazara_recordings ";
    $sql .= "where domain_uuid = :domain_uuid ";
    $sql .= "order by recording_name asc ";
    $parameters['domain_uuid'] = $_SESSION['domain_uuid'];
    $database = new database;
    $recordings = $database->select($sql, $parameters, 'all');
    unset($sql, $parameters);

// print_r($recordings); exit;

//begin the page content
	require_once "resources/header.php";
	if ($action == "update") {
	    $document['title'] = $text['title-ivr-edit'];
	}
	elseif ($action == "add") {
		$document['title'] = $text['title-ivr-add'];
	}

	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-ivr-add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-ivr-edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'ivrs.php'.(is_numeric($page) ? '?page='.$page : null)]);
	echo button::create(['type'=>'button','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;','onclick'=>'submit_form();']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

    //teacher uuid
        echo "	<tr>";
        echo "		<td class='vncellreq' valign='top'>".$text['label-teacher-name']."</td>";
        echo "		<td class='vtable' align='left'>";
        echo "			<select name=\"user_uuid\" class='formfld' style='width: auto;min-width: 150px;'>\n";
        echo "			<option value=\"\"></option>\n";
        foreach($teachers as $field) {
            if ($teacher_uuid == $field['chazara_teacher_uuid']) {
                echo "			<option value='".escape($field['chazara_teacher_uuid'])."' selected='selected'>".escape($field['name'])."</option>\n";
            }
            else {
                echo "			<option value='".escape($field['chazara_teacher_uuid'])."' $selected>".escape($field['name'])."</option>\n";
            }
        }
        echo "			</select>";
        unset($teachers);
        echo "			<br>\n";
        echo "			".$text['description-teachers']."\n";
        echo "		</td>";
        echo "	</tr>";

    // Grade
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-grade']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='grade' maxlength='255' value=\"".escape($grade)."\">\n";
		echo "<br />\n";
		echo $text['description-grade']."\n";
		echo "</td>\n";
		echo "</tr>\n";

    // // Parallel Class ID
	// 	echo "<tr>\n";
	// 	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	// 	echo "    ".$text['label-parallel_class_id']."\n";
	// 	echo "</td>\n";
	// 	echo "<td class='vtable' align='left'>\n";
	// 	echo "    <input class='formfld' type='text' name='parallel_class_id' maxlength='255' value=\"".escape($parallel_class_id)."\">\n";
	// 	echo "<br />\n";
	// 	echo $text['description-parallel_class_id']."\n";
	// 	echo "</td>\n";
	// 	echo "</tr>\n";


        echo "<tr>\n";
        echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
        echo "	".$text['label-ivr-main-greeting']."\n";
        echo "</td>\n";
        echo "<td class='vtable' align='left'>\n";
        if (if_group("superadmin")) {
            $destination_id = "ivr_greeting_recording";
            $script = "<script>\n";
            $script .= "var objs;\n";
            $script .= "\n";
            $script .= "function changeToInput".$destination_id."(obj){\n";
            $script .= "	tb=document.createElement('INPUT');\n";
            $script .= "	tb.type='text';\n";
            $script .= "	tb.name=obj.name;\n";
            $script .= "	tb.className='formfld';\n";
            $script .= "	tb.setAttribute('id', '".$destination_id."');\n";
            $script .= "	tb.setAttribute('style', '".$select_style."');\n";
            if ($on_change != '') {
                $script .= "	tb.setAttribute('onchange', \"".$on_change."\");\n";
                $script .= "	tb.setAttribute('onkeyup', \"".$on_change."\");\n";
            }
            $script .= "	tb.value=obj.options[obj.selectedIndex].value;\n";
            $script .= "	document.getElementById('btn_select_to_input_".$destination_id."').style.visibility = 'hidden';\n";
            $script .= "	tbb=document.createElement('INPUT');\n";
            $script .= "	tbb.setAttribute('class', 'btn');\n";
            $script .= "	tbb.setAttribute('style', 'margin-left: 4px;');\n";
            $script .= "	tbb.type='button';\n";
            $script .= "	tbb.value=$('<div />').html('&#9665;').text();\n";
            $script .= "	tbb.objs=[obj,tb,tbb];\n";
            $script .= "	tbb.onclick=function(){ Replace".$destination_id."(this.objs); }\n";
            $script .= "	obj.parentNode.insertBefore(tb,obj);\n";
            $script .= "	obj.parentNode.insertBefore(tbb,obj);\n";
            $script .= "	obj.parentNode.removeChild(obj);\n";
            $script .= "	Replace".$destination_id."(this.objs);\n";
            $script .= "}\n";
            $script .= "\n";
            $script .= "function Replace".$destination_id."(obj){\n";
            $script .= "	obj[2].parentNode.insertBefore(obj[0],obj[2]);\n";
            $script .= "	obj[0].parentNode.removeChild(obj[1]);\n";
            $script .= "	obj[0].parentNode.removeChild(obj[2]);\n";
            $script .= "	document.getElementById('btn_select_to_input_".$destination_id."').style.visibility = 'visible';\n";
            if ($on_change != '') {
                $script .= "	".$on_change.";\n";
            }
            $script .= "}\n";
            $script .= "</script>\n";
            $script .= "\n";
            echo $script;
        }
        echo "<select name='ivr_greeting_recording' id='ivr_greeting_recording' class='formfld'>\n";
        // echo "	<option></option>\n";
        //misc optgroup

        //recordings
            $tmp_selected = false;
            if (is_array($recordings)) {
                echo "<optgroup label='Recordings'>\n";
                foreach ($recordings as &$row) {
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
        //phrases

        //sounds

        //select


        echo "	</select>\n";
        if (if_group("superadmin")) {
            echo "<input type='button' id='btn_select_to_input_".escape($destination_id)."' class='btn' name='' alt='back' onclick='changeToInput".escape($destination_id)."(document.getElementById(\"".escape($destination_id)."\"));this.style.visibility = \"hidden\";' value='&#9665;'>";
            unset($destination_id);
        }
        echo "	<br />\n";
        echo $text['description-greet_long']."\n";
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
