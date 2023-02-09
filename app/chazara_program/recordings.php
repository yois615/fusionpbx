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

//download the recording
	if ($_GET['a'] == "download" && (permission_exists('chazara_recording_play') || permission_exists('chazara_recording_download'))) {
		if ($_GET['type'] = "rec") {
			//set the path for the directory
				$path = $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name'];

			//Get recording UUID from GET
				$chazara_recording_uuid = $_GET['id'];

			// build full path
				//Get teacher's private path
				$sql = "select t.grade, t.parallel_class_id, r.recording_filename ";
				$sql .= "from v_chazara_teachers t INNER JOIN v_chazara_recordings r ";
				$sql .= "on r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
				$sql .= "WHERE r.chazara_recording_uuid = :chazara_recording_uuid ";
				$sql .= "and r.domain_uuid = :domain_uuid ";
				if (!permission_exists('chazara_recording_all')) {
					$sql .= "and t.user_uuid = :user_uuid ";
					$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
				}
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['chazara_recording_uuid'] = $chazara_recording_uuid;
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
					$recording_filename = $row['recording_filename'];
					$full_recording_path = $path."/".$grade.$parallel."/".$recording_filename;
				}
				unset($sql, $parameters, $row);

			//send the headers and then the data stream
				if (file_exists($full_recording_path)) {
					//content-range
					if (isset($_SERVER['HTTP_RANGE']) && $_GET['t'] != "bin")  {
						range_download($full_recording_path);
					}

					$fd = fopen($full_recording_path, "rb");
					if ($_GET['t'] == "bin") {
						header("Content-Type: application/force-download");
						header("Content-Type: application/octet-stream");
						header("Content-Type: application/download");
						header("Content-Description: File Transfer");
					}
					else {
						$file_ext = pathinfo($recording_filename, PATHINFO_EXTENSION);
						switch ($file_ext) {
							case "wav" : header("Content-Type: audio/x-wav"); break;
							case "mp3" : header("Content-Type: audio/mpeg"); break;
							case "ogg" : header("Content-Type: audio/ogg"); break;
						}
					}
					header('Content-Disposition: attachment; filename="'.$recording_filename.'"');
					header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
					header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
					if ($_GET['t'] == "bin") {
						header("Content-Length: ".filesize($full_recording_path));
					}
					ob_clean();
					fpassthru($fd);
				}
		}
		exit;
	}

//upload the recording
	if (
		$_POST['a'] == "upload"
		&& permission_exists('chazara_recording_upload')
		&& $_POST['type'] == 'rec'
		&& is_uploaded_file($_FILES['file']['tmp_name'])
		) {

		//remove special characters
			$recording_filename = str_replace(" ", "_", $_FILES['file']['name']);
			$recording_filename = str_replace("'", "", $recording_filename);

		//Get teacher's path
			$grade = "00";
			$parallel = "0";
			$sql = "select grade, parallel_class_id ";
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
					$parallel = "0";
				} else {
					$parallel = $row['parallel_class_id'];
				}
			}
			unset($sql, $parameters, $row);

		//make sure the destination directory exists
			if (!is_dir($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$grade.$parallel)) {
				mkdir($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$grade.$parallel, 0770, false);
			}

		//move the uploaded files
			$result = move_uploaded_file($_FILES['file']['tmp_name'], $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$grade.$parallel.'/'.$recording_filename);

		//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//set the message
			message::add($text['message-uploaded'].": ".htmlentities($recording_filename));

		//set the file name to be inserted as the recording description
			$recording_description = $_FILES['file']['name'];
			header("Location: recordings.php?rd=".urlencode($recording_description));
			exit;
	}

//check the permission
	if (permission_exists('chazara_recording_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//get existing recording uuid
	$sql = "select r.chazara_recording_uuid, r.recording_id, r.recording_filename ";
	$sql .= "from v_chazara_recordings r ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "INNER JOIN v_chazara_teachers t ON r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	}
	$sql .= "where r.domain_uuid = :domain_uuid ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "and t.user_uuid = :user_uuid ";
	}
	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$result = $database->select($sql, $parameters, 'all');
	if (is_array($result) && @sizeof($result) != 0) {
		foreach ($result as &$row) {
			$array_recordings[$row['chazara_recording_uuid']] = $row['recording_filename'];
		}
	}
	unset($sql, $parameters, $result, $row);

//scan dir and add recordings to the database
	if ($_GET['show'] != "all") {

	//Get teacher's path
		$sql = "select grade, parallel_class_id ";
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
		}
		unset($sql, $parameters, $row);

		$current_sound_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$grade.$parallel.'/';
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

								//todo: we need to find correct teacher uuid
								$array['chazara_recordings'][0]['chazara_teacher_uuid'] = '57bd5741-e5cc-4e7e-b13a-19f3c4a1dc4c';

								$array['chazara_recordings'][0]['length'] = $recording_length;
								$array['chazara_recordings'][0]['recording_id'] = pathinfo($recording_filename, PATHINFO_FILENAME);
								$array['chazara_recordings'][0]['recording_filename'] = pathinfo($recording_filename, PATHINFO_BASENAME);
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
	$sql .= "INNER JOIN v_chazara_teachers t ";
	$sql .= "ON r.chazara_teacher_uuid = t.chazara_teacher_uuid ";
	$sql .= "where r.domain_uuid = :domain_uuid ";
	if (!permission_exists('chazara_recording_all') || $_GET['show'] != "all") {
		$sql .= "and t.user_uuid = :user_uuid ";
	}
	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
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
	$sql = "select r.chazara_recording_uuid, r.recording_id, r.recording_filename, ";
	$sql .= "r.length, r.recording_name, r.recording_description, r.enabled, ";
	$sql .= "t.grade, t.parallel_class_id ";
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
	$document['title'] = $text['title-recordings'];
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
	echo "	<div class='heading'><b>".$text['title-recordings']." (".$num_rows.")</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('chazara_recording_upload')) {
		echo 	"<form id='form_upload' class='inline' method='post' enctype='multipart/form-data'>\n";
		echo 	"<input name='a' type='hidden' value='upload'>\n";
		echo 	"<input name='type' type='hidden' value='rec'>\n";
		echo 	"<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','onclick'=>"$(this).fadeOut(250, function(){ $('span#form_upload').fadeIn(250); document.getElementById('ulfile').click(); });"]);
		echo 	"<span id='form_upload' style='display: none;'>";
		echo button::create(['label'=>$text['button-cancel'],'icon'=>$_SESSION['theme']['button_icon_cancel'],'type'=>'button','id'=>'btn_upload_cancel','onclick'=>"$('span#form_upload').fadeOut(250, function(){ document.getElementById('form_upload').reset(); $('#btn_add').fadeIn(250) });"]);
		echo 		"<input type='text' class='txt' style='width: 100px; cursor: pointer;' id='filename' placeholder='Select...' onclick=\"document.getElementById('ulfile').click(); this.blur();\" onfocus='this.blur();'>";
		echo 		"<input type='file' id='ulfile' name='file' style='display: none;' accept='.wav,.mp3,.ogg' onchange=\"document.getElementById('filename').value = this.files.item(0).name; check_file_type(this);\">";
		echo button::create(['type'=>'submit','label'=>$text['button-upload'],'icon'=>$_SESSION['theme']['button_icon_upload']]);
		echo 	"</span>\n";
		echo 	"</form>";
	}
	if (permission_exists('chazara_recording_delete') && $recordings) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if (permission_exists('chazara_recording_all')) {
		if ($_GET['show'] == 'all') {
			echo "		<input type='hidden' name='show' value='all'>";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$_SESSION['theme']['button_icon_all'],'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
		}
	}
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);
	//echo button::create(['label'=>$text['button-reset'],'icon'=>$_SESSION['theme']['button_icon_reset'],'type'=>'button','id'=>'btn_reset','link'=>'recordings.php','style'=>($search == '' ? 'display: none;' : null)]);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('chazara_recording_delete') && $recordings) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['description']."\n";
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
	echo th_order_by('recording_id', $text['label-recording_id'], $order_by, $order);
	$col_count++;
	if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
		echo th_order_by('grade', $text['label-grade'], $order_by, $order, $param, "class='shrink'");
		echo th_order_by('parallel_class_id', $text['label-parallel'], $order_by, $order, $param, "class='shrink'");
		$col_count++;
		$col_count++;
	}
	echo "<th class='center'>".$text['label-created']."</th>\n";
	$col_count++;
	echo "<th class='center'>".$text['label-length']."</th>\n";
	$col_count++;
	if (permission_exists('chazara_recording_play') || permission_exists('chazara_recording_download')) {
		echo "<th class='center shrink'>".$text['label-tools']."</th>\n";
		$col_count++;
	}
	echo th_order_by('enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	$col_count++;
	echo th_order_by('recording_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn pct-25'");
	$col_count++;

	echo "</tr>\n";

	if (is_array($recordings) && @sizeof($recordings) != 0) {
		$x = 0;
		foreach ($recordings as $row) {
			$message_minutes = floor($row['length'] / 60);
			$message_seconds = $row['length'] % 60;
			//use International System of Units (SI) - Source: https://en.wikipedia.org/wiki/International_System_of_Units
			$row['message_length_label'] = ($message_minutes > 0 ? $message_minutes.' min' : null).($message_seconds > 0 ? ' '.$message_seconds.' s' : null);

			//playback progress bar
			if (permission_exists('chazara_recording_play')) {
				echo "<tr class='list-row' id='recording_progress_bar_".escape($row['chazara_recording_uuid'])."' style='display: none;'><td class='playback_progress_bar_background' style='padding: 0; border: none;' colspan='".$col_count."'><span class='playback_progress_bar' id='recording_progress_".escape($row['chazara_recording_uuid'])."'></span></td><td class='description hide-sm-dn' style='border-bottom: none !important;'></td></tr>\n";
				echo "<tr class='list-row' style='display: none;'><td></td></tr>\n"; // dummy row to maintain alternating background color
			}
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
			echo "	<td>";
			if (permission_exists('chazara_recording_edit')) {
				echo "<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['recording_id'])."</a>";
			}
			else {
				echo escape($row['recording_id']);
			}
			echo "	</td>\n";
			if ($_GET['show'] == "all" && permission_exists('chazara_recording_all')) {
				echo "	<td>".$row['grade']."</td>\n";
				echo "	<td>".$row['parallel_class_id']."</td>\n";
			}
			$file_name = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$row['grade'].$row['parallel_class_id']."/".$row['recording_filename'];
			if (file_exists($file_name)) {
				$file_date = date("M d, Y H:i:s", filemtime($file_name));
			}
			else {
				unset($file_date);
			}
			echo "	<td class='center hide-md-dn'>".$file_date."</td>\n";
			//echo "	<td class='right no-wrap hide-xs'>".escape($row['message_length_label'])."</td>\n";

			if (permission_exists('chazara_recording_play') || permission_exists('chazara_recording_download')) {
				echo "	<td class='middle button center no-link no-wrap'>";
				if (permission_exists('chazara_recording_play')) {
					$recording_file_name = strtolower(pathinfo($file_name, PATHINFO_BASENAME));
					$recording_file_ext = pathinfo($recording_file_name, PATHINFO_EXTENSION);
					switch ($recording_file_ext) {
						case "wav" : $recording_type = "audio/wav"; break;
						case "mp3" : $recording_type = "audio/mpeg"; break;
						case "ogg" : $recording_type = "audio/ogg"; break;
					}
					echo "<audio id='recording_audio_".escape($row['chazara_recording_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['chazara_recording_uuid'])."')\" onended=\"recording_reset('".escape($row['chazara_recording_uuid'])."');\" src=\"".PROJECT_PATH."/app/chazara_program/recordings.php?a=download&type=rec&id=".urlencode($row['chazara_recording_uuid'])."\" type='".$recording_type."'></audio>";
					echo button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$_SESSION['theme']['button_icon_play'],'id'=>'recording_button_'.escape($row['chazara_recording_uuid']),'onclick'=>"recording_play('".escape($row['chazara_recording_uuid'])."')"]);
				}
				if (permission_exists('recording_download')) {
					echo button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$_SESSION['theme']['button_icon_download'],'link'=>"recordings.php?a=download&type=rec&t=bin&id=".urlencode($row['recording_uuid'])]);
				}
				echo "	</td>\n";
			}

			echo "	<td class='description overflow hide-sm-dn'>".escape($row['recording_description'])."&nbsp;</td>\n";
			if (permission_exists('recording_edit') && $_SESSION['theme']['list_row_edit_button']['boolean'] == 'true') {
				echo "	<td class='action-button'>";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>$list_row_url]);
				echo "	</td>\n";
			}
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
