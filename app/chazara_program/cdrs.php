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
	Portions created by the Initial Developer are Copyright (C) 2008-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

//set the max php execution time
	ini_set('max_execution_time', 7200);

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//add multi-lingual support
	$language = new text;
	$text = $language->get();

    $text['label-minimum'] = "Minimum";
    $text['label-maximum'] = "Maximum";
    $text['label-caller_id'] = "Caller ID";
    $text['label-name'] = "Name";
    $text['label-number'] = "Number";
	$text['label-duration'] = "Duration";
	$text['title-cdr'] = "Call Detail Records";

	$text['label-call_uuid'] = "Call ID";
	$text['label-teacher_name'] = "Teacher";
	$text['label-recording_id'] = "Recording ID";
	$text['label-callerid_name'] = "Caller Name";
	$text['label-callerid_number'] = "Caller Number";
	$text['label-created'] = "Created";

//check the permission
	if (permission_exists('chazara_cdrs_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//get existing recording uuid
	$sql = "select c.call_uuid, t.name as teacher_name, r.recording_id,c.caller_id_name, c.caller_id_number, c.start_epoch, c.duration   from v_chazara_cdrs c ";
	$sql .= "join v_chazara_teachers t on c.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	$sql .= "join v_chazara_recordings r on r.chazara_recording_uuid = c.chazara_recording_uuid ";
	$sql .= "where c.domain_uuid = :domain_uuid ";
	$sql .= " and t.user_uuid = :user_uuid ";

	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;
    // $parameters = array();
    $database = new database;
    $result = $database->select($sql, $parameters, 'all');
	$result_count = is_array($result) ? sizeof($result) : 0;
    // print_r($sql); print_r($parameters); 
    unset($sql, $parameters);
    // echo "<pre>";
    // print_r($result);
    // echo "</pre>";
    // exit;
//scan dir and add recordings to the database
	if ($_GET['show'] != "all") {

	//Get teacher's path
		$sql = "select grade, parallel_class_id, chazara_teacher_uuid ";
		$sql .= "from v_chazara_teachers ";
		$sql .= "where user_uuid = :user_uuid ";
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			if (strlen($row['grade']) < 2) {
				$grade = "0" . $row['grade'];
			} else {
				$grade = $row['grade'];
			}
			if (empty($row['parallel_class_id'])) {
				$parallel = "1";
			} else {
				$parallel = $row['parallel_class_id'];
			}
			$chazara_teacher_uuid = $row['chazara_teacher_uuid'];
		}
		unset($sql, $parameters, $row);

		$current_sound_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$chazara_teacher_uuid.'/';
		if (is_dir($current_sound_dir)) {
			if ($dh = opendir($current_sound_dir)) {
				while (($recording_filename = readdir($dh)) !== false) {
					if (filetype($current_sound_dir.$recording_filename) == "file") {

						if (!is_array($array_recordings) || !in_array($recording_filename, $array_recordings)) {
							//file not found in db, add it
								$recording_uuid = uuid();
								$recording_name = ucwords(str_replace('_', ' ', pathinfo($recording_filename, PATHINFO_FILENAME)));
								$recording_description = (isset($_GET['rd'])) ? $_GET['rd'] : $recording_name;
							//Get length of file
								$recording_length = ceil(shell_exec('soxi -D '.$current_sound_dir.$recording_filename));
							//build array
								$array['chazara_recordings'][0]['domain_uuid'] = $domain_uuid;
								$array['chazara_recordings'][0]['chazara_recording_uuid'] = $recording_uuid;

								$array['chazara_recordings'][0]['chazara_teacher_uuid'] = $chazara_teacher_uuid;

								$array['chazara_recordings'][0]['length'] = $recording_length;
								$array['chazara_recordings'][0]['recording_id'] = pathinfo($recording_filename, PATHINFO_FILENAME);
								$array['chazara_recordings'][0]['recording_filename'] = pathinfo($recording_filename, PATHINFO_BASENAME);
								//$array['chazara_recordings'][0]['recording_path'] = $grade.$parallel;
								$array['chazara_recordings'][0]['recording_name'] = $recording_name;
								$array['chazara_recordings'][0]['recording_description'] = $recording_description;
							//set temporary permissions
								$p = new permissions;
								$p->add('chazara_recording_add', 'temp');
							//execute insert
								$database = new database;
								$database->app_name = 'chazara_program';
								$database->app_uuid = '37a9d861-c7a2-9e90-925d-29e3c2e0b60e';
								$database->save($array);
								unset($array);
							//remove temporary permissions
								$p->delete('chazara_recording_add', 'temp');
						}
					}
				}
				closedir($dh);
			}
		}

	//redirect
		if ($_GET['rd'] != '') {
			header("Location: recordings.php");
			exit;
		}
	}

//get posted data
	if (is_array($_POST['recordings'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$recordings = $_POST['recordings'];
	}

//process the http post data by action
	if ($action != '' && is_array($recordings) && @sizeof($recordings) != 0) {
		switch ($action) {
			case 'delete':
				if (permission_exists('chazara_recording_delete')) {
					$obj = new chazara_program;
					$obj->delete($recordings);
				}
				break;
		}

		header('Location: recordings.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];

//add the search term
	$search = strtolower($_GET["search"]);
	if (strlen($search) > 0) {
		$sql_search = "and (";
		$sql_search .= "lower(recording_name) like :search ";
		$sql_search .= "or lower(recording_id) like :search ";
		$sql_search .= "or lower(recording_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

//get total recordings from the database
	$sql = "select count(*) from v_chazara_recordings r ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "INNER JOIN v_chazara_teachers t ";
		$sql .= "ON r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	}
	$sql .= "where r.domain_uuid = :domain_uuid ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "and t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$sql .= $sql_search;
	$database = new database;
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = "&search=".urlencode($search);
	if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
		$param .= "&show=all";
	}
	$param .= "&order_by=".$order_by."&order=".$order;
	$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the recordings from the database
	$sql = "select r.chazara_recording_uuid, r.recording_name, r.recording_id, r.recording_filename, ";
	$sql .= "r.length, r.recording_name, r.recording_description, r.enabled, r.insert_date, ";
	$sql .= "t.grade, t.parallel_class_id, r.chazara_teacher_uuid, t.name as teacher_name ";
	$sql .= "from v_chazara_recordings r ";
	$sql .= "INNER JOIN v_chazara_teachers t ON r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	$sql .= "where r.domain_uuid = :domain_uuid ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "and t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$sql .= order_by($order_by, $order, 'recording_id', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$database = new database;
	$recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-cdrs'];
	require_once "resources/header.php";

//file type check script
	echo "<script language='JavaScript' type='text/javascript'>\n";
	echo "	function check_file_type(file_input) {\n";
	echo "		file_ext = file_input.value.substr((~-file_input.value.lastIndexOf('.') >>> 0) + 2);\n";
	echo "		if (file_ext != 'mp3' && file_ext != 'wav' && file_ext != 'ogg' && file_ext != '') {\n";
	echo "			display_message(\"".$text['message-unsupported_file_type']."\", 'negative', '2750');\n";
	echo "		}\n";
	echo "	}\n";
	echo "</script>";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-cdr']." (".$result_count.")</b></div>\n";
	echo "	<div class='actions'>\n";

	if (permission_exists('chazara_teacher_view')) {
		echo button::create(['type'=>'button','label'=>$text['button-teacher-submenu'],'icon'=>$_SESSION['theme']['button_icon_users'],'link'=>'/app/chazara_program/teachers.php']);
	}
	if (permission_exists('chazara_ivr_edit')) {
		echo button::create(['type'=>'button','label'=>$text['button-ivr-submenu'],'icon'=>$_SESSION['theme']['button_icon_all'],'link'=>'/app/chazara_program/ivr_edit.php']);
	}

	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('chazara_recording_delete') && $recordings) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['description']."\n";
	echo "<br /><br />\n";


//basic search of call detail records
    echo "<form name='frm' id='frm' method='get'>\n";

    echo "<div class='form_grid'>\n";

    // if (permission_exists('xml_cdr_search_extension')) {
        $sql = "select chazara_teacher_uuid, name from v_chazara_teachers ";
        $sql .= "where domain_uuid = :domain_uuid ";
        $sql .= "order by name asc ";
        $parameters['domain_uuid'] = $_SESSION['domain_uuid'];
        $database = new database;
        $result_e = $database->select($sql, $parameters, 'all');
        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			"."Teacher"."\n";
        echo "		</div>\n";
        echo "		<div class='field'>\n";
        echo "			<select class='formfld' name='chazara_teacher_uuid' id='chazara_teacher_uuid'>\n";
        echo "				<option value=''></option>";
        if (is_array($result_e) && @sizeof($result_e) != 0) {
            foreach ($result_e as &$row) {
                $selected = ($row['chazara_teacher_uuid'] == $chazara_teacher_uuid) ? "selected" : null;
                echo "		<option value='".escape($row['chazara_teacher_uuid'])."' ".escape($selected).">".escape($row['name'])."</option>";
            }
        }
        echo "			</select>\n";
        echo "		</div>\n";
        echo "	</div>\n";
        unset($sql, $parameters, $result_e, $row, $selected);
    // }
    if (permission_exists('xml_cdr_search_caller_id')) {
        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			".$text['label-caller_id']."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld' name='caller_id_name' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-name']."\" value='".escape($caller_id_name)."'>\n";
        echo "			<input type='text' class='formfld' name='caller_id_number' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-number']."\" value='".escape($caller_id_number)."'>\n";
        echo "		</div>\n";
        echo "	</div>\n";
    }
    if (permission_exists('xml_cdr_search_start_range')) {
        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			"."Start Range"."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_begin' onblur=\"$(this).datetimepicker('hide');\" style='min-width: 115px; width: 115px;' name='start_stamp_begin' id='start_stamp_begin' placeholder='"."From"."' value='".escape($start_stamp_begin)."' autocomplete='off'>\n";
        echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_end' onblur=\"$(this).datetimepicker('hide');\" style='min-width: 115px; width: 115px;' name='start_stamp_end' id='start_stamp_end' placeholder='"."To"."' value='".escape($start_stamp_end)."' autocomplete='off'>\n";
        echo "		</div>\n";
        echo "	</div>\n";
    }
    if (permission_exists('xml_cdr_search_duration')) {
        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			"."Duration"." ("."Sec".")\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_min' value='".escape($duration_min)."' placeholder=\"".$text['label-minimum']."\">\n";
        echo "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_max' value='".escape($duration_max)."' placeholder=\"".$text['label-maximum']."\">\n";
        echo "		</div>\n";
        echo "	</div>\n";
    }
    if (permission_exists('xml_cdr_search_order')) {
        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			".$text['label-order']."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<select name='order_by' class='formfld'>\n";
        if (permission_exists('xml_cdr_extension')) {
            echo "			<option value='extension' ".($order_by == 'extension' ? "selected='selected'" : null).">".$text['label-extension']."</option>\n";
        }
        if (permission_exists('xml_cdr_all')) {
            echo "			<option value='domain_name' ".($order_by == 'domain_name' ? "selected='selected'" : null).">".$text['label-domain']."</option>\n";
        }
        if (permission_exists('xml_cdr_caller_id_name')) {
            echo "			<option value='caller_id_name' ".($order_by == 'caller_id_name' ? "selected='selected'" : null).">".$text['label-caller_id_name']."</option>\n";
        }
        if (permission_exists('xml_cdr_caller_id_number')) {
            echo "			<option value='caller_id_number' ".($order_by == 'caller_id_number' ? "selected='selected'" : null).">".$text['label-caller_id_number']."</option>\n";
        }
        if (permission_exists('xml_cdr_start')) {
            echo "			<option value='start_stamp' ".($order_by == 'start_stamp' || $order_by == '' ? "selected='selected'" : null).">".$text['label-start']."</option>\n";
        }
        if (permission_exists('xml_cdr_duration')) {
            echo "			<option value='duration' ".($order_by == 'duration' ? "selected='selected'" : null).">".$text['label-duration']."</option>\n";
        }
        if (permission_exists('xml_cdr_custom_fields')) {
            if (is_array($_SESSION['cdr']['field'])) {
                echo "			<option value='' disabled='disabled'></option>\n";
                echo "			<optgroup label=\"".$text['label-custom_cdr_fields']."\">\n";
                foreach ($_SESSION['cdr']['field'] as $field) {
                    $array = explode(",", $field);
                    $field_name = end($array);
                    $field_label = ucwords(str_replace("_", " ", $field_name));
                    $field_label = str_replace("Sip", "SIP", $field_label);
                    if ($field_name != "destination_number") {
                        echo "		<option value='".$field_name."' ".($order_by == $field_name ? "selected='selected'" : null).">".$field_label."</option>\n";
                    }
                }
                echo "			</optgroup>\n";
            }
        }
        echo "			</select>\n";
        echo "			<select name='order' class='formfld'>\n";
        echo "				<option value='desc' ".($order == 'desc' ? "selected='selected'" : null).">".$text['label-descending']."</option>\n";
        echo "				<option value='asc' ".($order == 'asc' ? "selected='selected'" : null).">".$text['label-ascending']."</option>\n";
        echo "			</select>\n";
        echo "		</div>\n";
        echo "	</div>\n";
    }

    echo "</div>\n";

    button::$collapse = false;
    echo "<div style='float: right; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>";
    if (permission_exists('xml_cdr_all') && $_REQUEST['show'] == 'all') {
        echo "<input type='hidden' name='show' value='all'>\n";
    }
    echo button::create(['label'=>$text['button-reset'],'icon'=>$_SESSION['theme']['button_icon_reset'],'type'=>'button','link'=>'cdrs.php']);
    echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_save','name'=>'submit']);
    echo "</div>\n";
    echo "<div style='font-size: 85%; padding-top: 12px; margin-bottom: 40px;'>".$text['description_search']."</div>\n";

    echo "</form>";

	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	$col_count = 0;
	if (permission_exists('chazara_recording_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".($recordings ?: "style='visibility: hidden;'").">\n";
		echo "	</th>\n";
		$col_count++;
	}
	echo th_order_by('call_uuid', $text['label-call_uuid'], $order_by, $order);
	$col_count++;
	echo th_order_by('recording_id', $text['label-recording_id'], $order_by, $order, $null, "class='center'");
	$col_count++;
	echo th_order_by('teacher_name', $text['label-teacher_name'], $order_by, $order, $null, "class='center'");
	$col_count++;
	echo "<th class='center'>".$text['label-callerid_name']."</th>\n";
	$col_count++;
	echo "<th class='center'>".$text['label-callerid_number']."</th>\n";
	$col_count++;
	echo "<th class='center'>".$text['label-duration']."</th>\n";
	$col_count++;
	echo th_order_by('start_epoch', $text['label-created'], $order_by, $order, null, "class='center'");
	$col_count++;
	echo "</tr>\n";

	if (is_array($result) && @sizeof($result) != 0) {
		$x = 0;
		foreach ($result as $row) {
			// print_r($row);
			$created_time = date("M d, Y H:i:s", substr($row['start_epoch'], 0, 10));
			// print_r($row['start_epoch']); print_r($created_time);
			$message_minutes = floor($row['duration'] / 60);
			$message_seconds = $row['duration'] % 60;
			//use International System of Units (SI) - Source: https://en.wikipedia.org/wiki/International_System_of_Units
			$row['message_duration_label'] = ($message_minutes > 0 ? $message_minutes.' min' : null).($message_seconds > 0 ? ' '.$message_seconds.' s' : null);

			if (permission_exists('chazara_recording_edit')) {
				$list_row_url = "recording_edit.php?id=".urlencode($row['chazara_recording_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('chazara_recording_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='recordings[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='recordings[$x][uuid]' value='".escape($row['chazara_recording_uuid'])."' />\n";
				echo "	</td>\n";
			}
			echo "	<td>".$row['call_uuid']."</td>\n";
			echo "	<td class='center'>";
			// if (permission_exists('chazara_recording_edit')) {
			// 	echo "<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['recording_id'])."</a>";
			// }
			// else {
				echo escape($row['recording_id']);
			// }
			echo "	</td>\n";
			echo "	<td class='center'>".$row['teacher_name']."</td>\n";
			echo "	<td class='center'>".$row['caller_id_name']."</td>\n";
			echo "	<td class='center'>".$row['caller_id_number']."</td>\n";
			echo "	<td class='center'>".$row['message_duration_label']."</td>\n";
			echo "	<td class='center'>".$created_time."</td>\n";
			echo "</tr>\n";
			$x++;
		}
		unset($recordings);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

//define the download function (helps safari play audio sources)
	function range_download($file) {
		$fp = @fopen($file, 'rb');

		$size   = filesize($file); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		// Now that we've gotten so far without errors we send the accept range header
		/* At the moment we only support single ranges.
		* Multiple ranges requires some more work to ensure it works correctly
		* and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		*
		* Multirange support annouces itself with:
		* header('Accept-Ranges: bytes');
		*
		* Multirange content must be sent with multipart/byteranges mediatype,
		* (mediatype = mimetype)
		* as well as a boundry header to indicate the various chunks of data.
		*/
		header("Accept-Ranges: 0-$length");
		// header('Accept-Ranges: bytes');
		// multipart/byteranges
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		if (isset($_SERVER['HTTP_RANGE'])) {

			$c_start = $start;
			$c_end   = $end;
			// Extract the range string
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			// Make sure the client hasn't sent us a multibyte range
			if (strpos($range, ',') !== false) {
				// (?) Shoud this be issued here, or should the first
				// range be used? Or should the header be ignored and
				// we output the whole content?
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				// (?) Echo some info to the client?
				exit;
			}
			// If the range starts with an '-' we start from the beginning
			// If not, we forward the file pointer
			// And make sure to get the end byte if spesified
			if ($range == '-') {
				// The n-number of the last bytes is requested
				$c_start = $size - substr($range, 1);
			}
			else {
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			/* Check the range and make sure it's treated according to the specs.
			* http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
			*/
			// End bytes can not be larger than $end.
			$c_end = ($c_end > $end) ? $end : $c_end;
			// Validate the requested range and return an error if it's not correct.
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {

				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				// (?) Echo some info to the client?
				exit;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1; // Calculate new content length
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		// Notify the client the byte range we'll be outputting
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: $length");

		// Start buffered download
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			set_time_limit(0); // Reset time limit for big files
			echo fread($fp, $buffer);
			flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
		}

		fclose($fp);
	}

?>
