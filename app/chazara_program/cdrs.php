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

//get post or get variables from http
	if (count($_REQUEST) > 0) {
		$teacher_uuid = $_REQUEST["teacher_uuid"];
		$caller_id_name = $_REQUEST["caller_id_name"];
		$caller_id_number = $_REQUEST["caller_id_number"];
		$start_stamp_begin = $_REQUEST["start_stamp_begin"];
		$start_stamp_end = $_REQUEST["start_stamp_end"];
		$duration_min = $_REQUEST["duration_min"];
		$duration_max = $_REQUEST["duration_max"];
		$order_by = $_REQUEST["order_by"];
		$order = $_REQUEST["order"];
	}

	//validate the order
	switch ($order) {
		case 'asc':
			break;
		case 'desc':
			break;
		default:
			$order = '';
	}

	//set the param variable which is used with paging
	$param = "&teacher_uuid=".urlencode($teacher_uuid);
	$param .= "&caller_id_name=".urlencode($caller_id_name);
	$param .= "&caller_id_number=".urlencode($caller_id_number);
	$param .= "&start_stamp_begin=".urlencode($start_stamp_begin);
	$param .= "&start_stamp_end=".urlencode($start_stamp_end);
	$param .= "&duration_min=".urlencode($duration_min);
	$param .= "&duration_max=".urlencode($duration_max);

	if (isset($order_by)) {
		$param .= "&order_by=".urlencode($order_by)."&order=".urlencode($order);
	}

	//create the sql query to get the xml cdr records
	if (strlen($order_by) == 0) { $order_by  = "c.start_epoch"; }
	if (strlen($order) == 0) { $order  = "desc"; }

	//limit the number of results
	if ($_SESSION['cdr']['limit']['numeric'] > 0) {
		$num_rows = $_SESSION['cdr']['limit']['numeric'];
	}

	//set the default paging
	$rows_per_page = $_SESSION['domain']['paging']['numeric'];

	//prepare to page the results
	//$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50; //set on the page that includes this page
	if (is_numeric($_GET['page'])) { $page = $_GET['page']; }
	if (!isset($_GET['page'])) { $page = 0; $_GET['page'] = 0; }
	$offset = $rows_per_page * $page;

	//set the time zone
	if (isset($_SESSION['domain']['time_zone']['name'])) {
		$time_zone = $_SESSION['domain']['time_zone']['name'];
	}
	else {
		$time_zone = date_default_timezone_get();
	}
	// $parameters['time_zone'] = $time_zone;


//get existing recording uuid
	$sql_rows = "select c.call_uuid, t.name as teacher_name, r.recording_id,c.caller_id_name, c.caller_id_number, c.start_epoch, c.duration ";
	$sql_cnt = "select count(*) as cnt ";
	$sql = "from v_chazara_cdrs c ";
	$sql .= "join v_chazara_teachers t on c.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	$sql .= "join v_chazara_recordings r on r.chazara_recording_uuid = c.chazara_recording_uuid ";
	$sql .= "where c.domain_uuid = :domain_uuid ";
	$sql .= " and t.user_uuid = :user_uuid ";

	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;

	if (strlen($teacher_uuid) > 0) {
		$sql .= "and c.chazara_teacher_uuid = :teacher_uuid \n";
		$parameters['teacher_uuid'] = $teacher_uuid;
	}
	if (strlen($caller_id_name) > 0) {
		$mod_caller_id_name = str_replace("*", "%", $caller_id_name);
		if (strstr($mod_caller_id_name, '%')) {
			$sql .= "and c.caller_id_name like :caller_id_name \n";
			$parameters['caller_id_name'] = $mod_caller_id_name;
		}
		else {
			$sql .= "and c.caller_id_name = :caller_id_name \n";
			$parameters['caller_id_name'] = $mod_caller_id_name;
		}
	}
	if (strlen($caller_id_number) > 0) {
		$mod_caller_id_number = str_replace("*", "%", $caller_id_number);
		$mod_caller_id_number = preg_replace("#[^\+0-9.%/]#", "", $mod_caller_id_number);
		if (strstr($mod_caller_id_number, '%')) {
			$sql .= "and c.caller_id_number like :caller_id_number \n";
			$parameters['caller_id_number'] = $mod_caller_id_number;
		}
		else {
			$sql .= "and caller_id_number = :caller_id_number \n";
			$parameters['caller_id_number'] = $mod_caller_id_number;
		}
	}
	if (strlen($start_stamp_begin) > 0 && strlen($start_stamp_end) > 0) {
		$sql .= "and c.start_stamp between :start_stamp_begin::timestamptz and :start_stamp_end::timestamptz \n";
		$parameters['start_stamp_begin'] = $start_stamp_begin.':00.000 '.$time_zone;
		$parameters['start_stamp_end'] = $start_stamp_end.':59.999 '.$time_zone;
	}
	else {
		if (strlen($start_stamp_begin) > 0) {
			$sql .= "and c.start_stamp >= :start_stamp_begin \n";
			$parameters['start_stamp_begin'] = $start_stamp_begin.':00.000 '.$time_zone;
		}
		if (strlen($start_stamp_end) > 0) {
			$sql .= "and c.start_stamp <= :start_stamp_end \n";
			$parameters['start_stamp_end'] = $start_stamp_end.':59.999 '.$time_zone;
		}
	}
	if (is_numeric($duration_min)) {
		$sql .= "and c.duration >= :duration_min \n";
		$parameters['duration_min'] = $duration_min;
	}
	if (is_numeric($duration_max)) {
		$sql .= "and c.duration <= :duration_max \n";
		$parameters['duration_max'] = $duration_max;
	}
	//end where

	//find total row count
    $database = new database;
    $num_rows = $database->select($sql_cnt.$sql, $parameters, 'column');
	// print_r($database->message);
	// print_r($sql_cnt.$sql);
	// print_r($num_rows); exit;

	$sql = $sql_rows.$sql;

	if (strlen($order_by) > 0) {
		$sql .= order_by($order_by, $order);
	}

	//pagination
	if ($rows_per_page == 0) {
		$sql .= " limit :limit offset 0 \n";
		$parameters['limit'] = $_SESSION['cdr']['limit']['numeric'];
	}
	else {
		$sql .= " limit :limit offset :offset \n";
		$parameters['limit'] = $rows_per_page;
		$parameters['offset'] = $offset;
	}

	$sql = str_replace("  ", " ", $sql);

    // $parameters = array();
    $database = new database;
    $result = $database->select($sql, $parameters, 'all');
	$result_count = is_array($result) ? sizeof($result) : 0;
	// print_r($database->message);
    // print_r($sql); print_r($parameters); print_r($result); exit;
    unset($sql, $parameters);
    // echo "<pre>";
    // print_r($result);
    // echo "</pre>";
    // exit;

	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true, $result_count); //top
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page, false, $result_count); //bottom

	// print_r($result);
	// print_r($paging_controls);
	// print_r($rows_per_page);
	// exit;
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
        echo "			<select class='formfld' name='teacher_uuid' id='teacher_uuid'>\n";
        echo "				<option value=''></option>";
        if (is_array($result_e) && @sizeof($result_e) != 0) {
            foreach ($result_e as &$row) {
                $selected = ($row['chazara_teacher_uuid'] == $teacher_uuid) ? "selected" : null;
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
            echo "			<option value='c.chazara_teacher_uuid' ".($order_by == 'c.chazara_teacher_uuid' ? "selected='selected'" : null).">".$text['label-teacher_name']."</option>\n";
        }
        // if (permission_exists('xml_cdr_all')) {
        //     echo "			<option value='domain_name' ".($order_by == 'domain_name' ? "selected='selected'" : null).">".$text['label-domain']."</option>\n";
        // }
        if (permission_exists('xml_cdr_caller_id_name')) {
            echo "			<option value='caller_id_name' ".($order_by == 'caller_id_name' ? "selected='selected'" : null).">".$text['label-caller_id_name']."</option>\n";
        }
        if (permission_exists('xml_cdr_caller_id_number')) {
            echo "			<option value='caller_id_number' ".($order_by == 'caller_id_number' ? "selected='selected'" : null).">".$text['label-caller_id_number']."</option>\n";
        }
        if (permission_exists('xml_cdr_start')) {
            echo "			<option value='c.start_epoch' ".($order_by == 'c.start_epoch' || $order_by == '' ? "selected='selected'" : null).">".$text['label-created']."</option>\n";
        }
        if (permission_exists('xml_cdr_duration')) {
            echo "			<option value='duration' ".($order_by == 'duration' ? "selected='selected'" : null).">".$text['label-duration']."</option>\n";
        }
        // if (permission_exists('xml_cdr_custom_fields')) {
        //     if (is_array($_SESSION['cdr']['field'])) {
        //         echo "			<option value='' disabled='disabled'></option>\n";
        //         echo "			<optgroup label=\"".$text['label-custom_cdr_fields']."\">\n";
        //         foreach ($_SESSION['cdr']['field'] as $field) {
        //             $array = explode(",", $field);
        //             $field_name = end($array);
        //             $field_label = ucwords(str_replace("_", " ", $field_name));
        //             $field_label = str_replace("Sip", "SIP", $field_label);
        //             if ($field_name != "destination_number") {
        //                 echo "		<option value='".$field_name."' ".($order_by == $field_name ? "selected='selected'" : null).">".$field_label."</option>\n";
        //             }
        //         }
        //         echo "			</optgroup>\n";
        //     }
        // }
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
