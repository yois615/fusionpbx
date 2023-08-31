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

//define functions
	function array2csv(array &$array) {
		if (count($array) == 0) {
			return null;
		}
		ob_start();
		$df = fopen("php://output", 'w');
		fputcsv($df, array_keys(reset($array)));
		foreach ($array as $row) {
			fputcsv($df, $row);
		}
		fclose($df);
		return ob_get_clean();
	}

	function download_send_headers($filename) {
		// disable caching
		$now = gmdate("D, d M Y H:i:s");
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		// force download
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");

		// disposition / encoding on response body
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");
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

	if ($_REQUEST['show'] == "all" && permission_exists('chazara_cdrs_all')) {
		$param .= "&show=all";
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
	$sql_rows = "select c.call_uuid, t.name as teacher_name, t.grade, r.recording_id,c.caller_id_name, c.caller_id_number, c.start_epoch, c.duration ";
	if ($_SESSION['chazara']['daf_mode']['boolean'] == "true") {
		$sql_rows .= ", r.daf_number, r.daf_amud, r.daf_start_line ";
	}
	$sql_cnt = "select count(*) as cnt ";
	$sql = "from v_chazara_cdrs c ";
	$sql .= "join v_chazara_teachers t on c.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	$sql .= "join v_chazara_recordings r on r.chazara_recording_uuid = c.chazara_recording_uuid ";
	$sql .= "where c.domain_uuid = :domain_uuid ";
	if (!permission_exists('chazara_cdrs_all') || $_GET['show'] != "all") {
		$sql .= " and t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	}

	$parameters['domain_uuid'] = $domain_uuid;

	if (permission_exists('chazara_cdrs_all') && strlen($teacher_uuid) > 0) {
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

	if (!$_GET["action"] == "download") {
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

	if ($_GET["action"] == "download") {
		download_send_headers("cdr_export_".date("Y-m-d").".csv");
		echo array2csv($result);
		exit;
	}



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

	if (permission_exists('chazara_cdrs_all')) {
		if ($_GET['show'] == 'all') {
			echo "		<input type='hidden' name='show' value='all'>";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$_SESSION['theme']['button_icon_all'],'link'=>'?type=&show=all']);
		}
	}

	echo button::create(['type'=>'button','label'=>$text['button-export'],
						'icon'=>$_SESSION['theme']['button_icon_export'],
						'link'=>'?action=download'. (http_build_query($_GET) ? '&'.http_build_query($_GET) : null)]);

	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description']."\n";
	echo "<br /><br />\n";


//basic search of call detail records
    echo "<form name='frm' id='frm' method='get'>\n";

    echo "<div class='form_grid'>\n";

    if (permission_exists('chazara_cdrs_all') && $_REQUEST['show'] == 'all') {
		echo "		<input type='hidden' name='show' value='all'>";

        $sql = "select chazara_teacher_uuid, name, grade from v_chazara_teachers ";
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
                echo "		<option value='".escape($row['chazara_teacher_uuid'])."' ".escape($selected).">".escape($row['grade'])."-".escape($row['name'])."</option>";
            }
        }
        echo "			</select>\n";
        echo "		</div>\n";
        echo "	</div>\n";
        unset($sql, $parameters, $result_e, $row, $selected);
     }

        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			".$text['label-caller_id']."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld' name='caller_id_name' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-name']."\" value='".escape($caller_id_name)."'>\n";
        echo "			<input type='text' class='formfld' name='caller_id_number' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-number']."\" value='".escape($caller_id_number)."'>\n";
        echo "		</div>\n";
        echo "	</div>\n";


        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			"."Start Range"."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_begin' onblur=\"$(this).datetimepicker('hide');\" style='min-width: 115px; width: 115px;' name='start_stamp_begin' id='start_stamp_begin' placeholder='"."From"."' value='".escape($start_stamp_begin)."' autocomplete='off'>\n";
        echo "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_end' onblur=\"$(this).datetimepicker('hide');\" style='min-width: 115px; width: 115px;' name='start_stamp_end' id='start_stamp_end' placeholder='"."To"."' value='".escape($start_stamp_end)."' autocomplete='off'>\n";
        echo "		</div>\n";
        echo "	</div>\n";


        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			"."Duration"." ("."Sec".")\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_min' value='".escape($duration_min)."' placeholder=\"".$text['label-minimum']."\">\n";
        echo "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_max' value='".escape($duration_max)."' placeholder=\"".$text['label-maximum']."\">\n";
        echo "		</div>\n";
        echo "	</div>\n";


        echo "	<div class='form_set'>\n";
        echo "		<div class='label'>\n";
        echo "			".$text['label-order']."\n";
        echo "		</div>\n";
        echo "		<div class='field no-wrap'>\n";
        echo "			<select name='order_by' class='formfld'>\n";
        if (permission_exists('chazara_cdrs_all') && $_REQUEST['show'] == 'all') {
            echo "			<option value='c.chazara_teacher_uuid' ".($order_by == 'c.chazara_teacher_uuid' ? "selected='selected'" : null).">".$text['label-teacher_name']."</option>\n";
        }
		echo "			<option value='caller_id_name' ".($order_by == 'caller_id_name' ? "selected='selected'" : null).">".$text['label-caller_id_name']."</option>\n";
		echo "			<option value='caller_id_number' ".($order_by == 'caller_id_number' ? "selected='selected'" : null).">".$text['label-caller_id_number']."</option>\n";
		echo "			<option value='c.start_epoch' ".($order_by == 'c.start_epoch' || $order_by == '' ? "selected='selected'" : null).">".$text['label-created']."</option>\n";
		echo "			<option value='duration' ".($order_by == 'duration' ? "selected='selected'" : null).">".$text['label-duration']."</option>\n";
        echo "			</select>\n";
        echo "			<select name='order' class='formfld'>\n";
        echo "				<option value='desc' ".($order == 'desc' ? "selected='selected'" : null).">".$text['label-descending']."</option>\n";
        echo "				<option value='asc' ".($order == 'asc' ? "selected='selected'" : null).">".$text['label-ascending']."</option>\n";
        echo "			</select>\n";
        echo "		</div>\n";
        echo "	</div>\n";

    echo "</div>\n";

    button::$collapse = false;
    echo "<div style='float: right; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>";
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
	echo th_order_by('start_epoch', $text['label-created'], $order_by, $order, null, "class='left'");
	$col_count++;
	echo "<th class='left'>".$text['label-callerid_name']."</th>\n";
	$col_count++;
	echo "<th class='left'>".$text['label-callerid_number']."</th>\n";
	$col_count++;
	if (permission_exists('chazara_cdrs_all') && $_REQUEST['show'] == 'all') {
		echo th_order_by('teacher_name', $text['label-teacher_name'], $order_by, $order, $null, "class='left'");
		$col_count++;
	}
	if ($_SESSION['chazara']['daf_mode']['boolean'] == "false") {
		echo th_order_by('recording_id', $text['label-recording_id'], $order_by, $order, $null, "class='left'");
		$col_count++;
	} else {
		// Daf and line
		echo th_order_by('daf_number', $text['label-daf_number'], $order_by, $order, $null, "class='left'");
		$col_count++;
		echo "<th class='left'>".$text['label-daf_start_line']."</th>\n";
		$col_count++;
	}
	echo "<th class='left'>".$text['label-duration']."</th>\n";
	$col_count++;

	echo "</tr>\n";

	if (is_array($result) && @sizeof($result) != 0) {
		$x = 0;
		foreach ($result as $row) {
			echo "<tr class = 'list-row'>";
			// print_r($row);
			$created_time = date("M d, Y H:i:s", substr($row['start_epoch'], 0, 10));
			echo "	<td class='left'>".$created_time."</td>\n";
			// print_r($row['start_epoch']); print_r($created_time);

			echo "	<td class='left'>".$row['caller_id_name']."</td>\n";
			echo "	<td class='left'>".$row['caller_id_number']."</td>\n";

			if (permission_exists('chazara_cdrs_all') && $_REQUEST['show'] == 'all') {
				echo "	<td class='left'>".$row['grade']."-".$row['teacher_name']."</td>\n";
			}

			if ($_SESSION['chazara']['daf_mode']['boolean'] == "false") {
				echo "	<td class='left'>";
				echo escape($row['recording_id']);
				echo "	</td>\n";
			} else {
				echo "	<td class='left'>";
				echo escape($row['daf_number'].$row['daf_amud']);
				echo "	</td>\n";
				echo "	<td class='left'>";
				//line
				echo escape($row['daf_start_line']);
				echo "	</td>\n";
			}

			$message_minutes = floor($row['duration'] / 60);
			$message_seconds = $row['duration'] % 60;
			//use International System of Units (SI) - Source: https://en.wikipedia.org/wiki/International_System_of_Units
			$row['message_duration_label'] = ($message_minutes > 0 ? $message_minutes.' min' : null).($message_seconds > 0 ? ' '.$message_seconds.' s' : null);
			echo "	<td class='left'>".$row['message_duration_label']."</td>\n";
			
			echo "</tr>\n";
			$x++;
		}
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
