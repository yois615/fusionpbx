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
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!(permission_exists('call_center_agent_add') || permission_exists('call_center_agent_edit'))) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the defaults
	$agent_id = '';
	$agent_name = '';
	$agent_password = '';

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$call_center_agent_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get the users array
	$sql = "select * from v_users ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by username asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$users = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$call_center_agent_uuid = $_POST["call_center_agent_uuid"] ?? null;
		$user_uuid = $_POST["user_uuid"];
		$agent_name = $_POST["agent_name"];
		$agent_type = $_POST["agent_type"];
		$agent_call_timeout = $_POST["agent_call_timeout"];
		$agent_id = $_POST["agent_id"];
		$agent_password = $_POST["agent_password"];
		$agent_status = $_POST["agent_status"];
		$agent_contact = $_POST["agent_contact"];
		$agent_no_answer_delay_time = $_POST["agent_no_answer_delay_time"];
		$agent_max_no_answer = $_POST["agent_max_no_answer"];
		$agent_wrap_up_time = $_POST["agent_wrap_up_time"];
		$agent_reject_delay_time = $_POST["agent_reject_delay_time"];
		$agent_busy_delay_time = $_POST["agent_busy_delay_time"];
		$agent_record = $_POST["agent_record"];
		$agent_use_system_caller_id = $_POST["use_system_caller_id"];
		$agent_enabled = $_POST["agent_enabled"];
		$agent_confirm_prompt = $_POST["agent_confirm_prompt"];
		$call_center_tiers = $_POST["call_center_tiers"];
		$agent_schedules_ = $_POST["agent_schedules"];
		//$agent_logout = $_POST["agent_logout"];

		$agent_schedules = [];

		if (is_array($agent_schedules_)) {
			$i = 0;
			foreach($agent_schedules_ as $k => $v) {
				$dow = $v['dow'];
				$days_of_week = ((int)$dow['sun']) + ((int)$dow['mon'] << 1) + ((int)$dow['tue'] << 2) + ((int)$dow['wed'] << 3) + ((int)$dow['thu'] << 4) + ((int)$dow['fri'] << 5) + ((int)$dow['sat'] << 6);

				if (empty($v['call_center_queue_uuid']) && $days_of_week == 0 && empty($v['login_time']) && empty($v['logout_time']))
					continue;

				$agent_schedules[$i]['call_center_agent_schedule_uuid'] = $v['call_center_agent_schedule_uuid'];
				$agent_schedules[$i]['call_center_agent_uuid'] = $call_center_agent_uuid;
				$agent_schedules[$i]['call_center_queue_uuid'] = $v['call_center_queue_uuid'];
				$agent_schedules[$i]['tier'] = $v['tier'];
				$agent_schedules[$i]['days_of_week'] = $days_of_week;
				$agent_schedules[$i]['login_time'] = $v['login_time'];
				$agent_schedules[$i]['logout_time'] = $v['logout_time'];
				$agent_schedules[$i]['enabled'] = $v['enabled'];
				$agent_schedules[$i]['delete'] = $v['delete'];

				$i++;
			}
		}
	}

//delete the tier (agent from the queue)
	if (!empty($_REQUEST["a"]) && $_REQUEST["a"] == "delete" && is_uuid($_REQUEST["id"]) && permission_exists("call_center_tier_delete")) {
		//set the variables
			$call_center_queue_uuid = $_REQUEST["id"];
			$call_center_tier_uuid = $_REQUEST["call_center_tier_uuid"];

		//get the agent details
			$sql = "select t.call_center_agent_uuid, t.call_center_queue_uuid, q.queue_extension  ";
			$sql .= "from v_call_center_tiers as t, v_call_center_queues as q ";
			$sql .= "where t.domain_uuid = :domain_uuid  ";
			$sql .= "and t.call_center_tier_uuid = :call_center_tier_uuid ";
			$sql .= "and t.call_center_queue_uuid = q.call_center_queue_uuid; ";
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['call_center_tier_uuid'] = $call_center_tier_uuid;
			$tiers = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

			if (!empty($tiers)) {
				foreach ($tiers as $row) {
					$call_center_agent_uuid = $row["call_center_agent_uuid"];
					$call_center_queue_uuid = $row["call_center_queue_uuid"];
					$queue_extension = $row["queue_extension"];
				}
			}

		//delete the agent from freeswitch
			//setup the event socket connection
			$esl = event_socket::create();
			//delete the agent over event socket
			if ($esl->is_connected()) {
				//callcenter_config tier del [queue_name] [agent_name]
				if (is_numeric($queue_extension) && is_uuid($call_center_agent_uuid)) {
					$cmd = "callcenter_config tier del ".$queue_extension."@".$domain_name." ".$call_center_agent_uuid;
					$response = event_socket::api($cmd);
				}
			}

		//delete the tier from the database
			if (!empty($call_center_tier_uuid)) {
				$array['call_center_tiers'][0]['call_center_tier_uuid'] = $call_center_tier_uuid;
				$array['call_center_tiers'][0]['domain_uuid'] = $domain_uuid;

				$p = permissions::new();
				$p->add('call_center_tier_delete', 'temp');

				$database->delete($array);
				unset($array);

				$p->delete('call_center_tier_delete', 'temp');
			}
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: call_center_agents.php');
				exit;
			}

		//check for all required data
			$msg = '';
			//if (empty($call_center_agent_uuid)) { $msg .= $text['message-required']." ".$text['label-call_center_agent_uuid']."<br>\n"; }
			//if (empty($domain_uuid)) { $msg .= $text['message-required']." ".$text['label-domain_uuid']."<br>\n"; }
			//if (empty($user_uuid)) { $msg .= $text['message-required']." ".$text['label-user_uuid']."<br>\n"; }
			if (empty($agent_name)) { $msg .= $text['message-required']." ".$text['label-agent_name']."<br>\n"; }
			//if (empty($agent_type)) { $msg .= $text['message-required']." ".$text['label-agent_type']."<br>\n"; }
			if (empty($agent_call_timeout)) { $msg .= $text['message-required']." ".$text['label-agent_call_timeout']."<br>\n"; }
			//if (empty($agent_id)) { $msg .= $text['message-required']." ".$text['label-agent_id']."<br>\n"; }
			//if (empty($agent_password)) { $msg .= $text['message-required']." ".$text['label-agent_password']."<br>\n"; }
			//if (empty($agent_status)) { $msg .= $text['message-required']." ".$text['label-agent_status']."<br>\n"; }
			if (empty($agent_contact)) { $msg .= $text['message-required']." ".$text['label-agent_contact']."<br>\n"; }
			//if (empty($agent_logout)) { $msg .= $text['message-required']." ".$text['label-agent_logout']."<br>\n"; }

			$tier_queue_uuids = [];
			foreach ($_POST["call_center_tiers"] as $k => $v) {
				if (in_array($v['call_center_queue_uuid'], $tier_queue_uuids)) {
					$msg .= "Agent is assigned to the same queue multiple times";
				} else if (!empty($v['call_center_queue_uuid'])) {
					array_push($tier_queue_uuids, $v['call_center_queue_uuid']);
				}
			}

			if (!empty($msg) && empty($_POST["persistformvar"])) {
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

		//set default values
			$agent_call_timeout = $agent_call_timeout ?? "20";
			$agent_max_no_answer = $agent_max_no_answer ?? "0";
			$agent_wrap_up_time = $agent_wrap_up_time ?? "10";
			$agent_no_answer_delay_time = $agent_no_answer_delay_time ?? "30";
			$agent_reject_delay_time = $agent_reject_delay_time ?? "90";
			$agent_busy_delay_time = $agent_busy_delay_time ?? "90";
			$agent_record = $agent_record ?? false;

		//add the call_center_agent_uuid
			if (empty($call_center_agent_uuid)) {
				$call_center_agent_uuid = uuid();
			}

		//change the contact string to loopback - Not recommended added for backwards comptability causes multiple problems
			if ($settings->get('call_center', 'agent_contact_method') == 'loopback') {
				$agent_contact = str_replace("user/", "loopback/", $agent_contact);
				$agent_contact = str_replace("@", "/", $agent_contact);
			}

		//freeswitch expands the contact string, so we need to sanitize it.
			$agent_contact = str_replace('$', '', $agent_contact);

		//update the call center tiers array
			$x = 0;
			if (!empty($_POST["call_center_tiers"])) {
				foreach ($_POST["call_center_tiers"] as $row) {
					//add the domain_uuid
						if (empty($row["domain_uuid"])) {
							$_POST["call_center_tiers"][$x]["domain_uuid"] = $domain_uuid;
						}
					//unset ring_group_destination_uuid if the field has no value
						if (empty($row["call_center_queue_uuid"])) {
							unset($_POST["call_center_tiers"][$x]);
						}
					//increment the row
						$x++;
				}
			}

		//prepare the array
			$array['call_center_agents'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['call_center_agents'][0]['call_center_agent_uuid'] = $call_center_agent_uuid;
			$array['call_center_agents'][0]['agent_name'] = $agent_name;
			$array['call_center_agents'][0]['agent_type'] = $agent_type;
			$array['call_center_agents'][0]['agent_call_timeout'] = $agent_call_timeout;
			$array['call_center_agents'][0]['user_uuid'] = $user_uuid;
			$array['call_center_agents'][0]['agent_id'] = $agent_id;
			$array['call_center_agents'][0]['agent_password'] = $agent_password;
			$array['call_center_agents'][0]['agent_contact'] = $agent_contact;
			$array['call_center_agents'][0]['agent_status'] = $agent_status;
			$array['call_center_agents'][0]['agent_no_answer_delay_time'] = $agent_no_answer_delay_time;
			$array['call_center_agents'][0]['agent_max_no_answer'] = $agent_max_no_answer;
			$array['call_center_agents'][0]['agent_wrap_up_time'] = $agent_wrap_up_time;
			$array['call_center_agents'][0]['agent_reject_delay_time'] = $agent_reject_delay_time;
			$array['call_center_agents'][0]['agent_busy_delay_time'] = $agent_busy_delay_time;
			$array['call_center_agents'][0]['agent_record'] = $agent_record;
			$array['call_center_agents'][0]['agent_use_system_caller_id'] = $agent_use_system_caller_id;
			$array['call_center_agents'][0]['agent_enabled'] = $agent_enabled;
			$array['call_center_agents'][0]['agent_confirm_prompt'] = $agent_confirm_prompt;
			if (is_uuid($user_uuid)) {
				$array['users'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
				$array['users'][0]['user_uuid'] = $user_uuid;
				$array['users'][0]['user_status'] = $agent_status;
			}

			$y = 0;
			if (!empty($_POST["call_center_tiers"])) {
				foreach ($_POST["call_center_tiers"] as $row) {
					if (is_uuid($row['call_center_tier_uuid'])) {
						$call_center_tier_uuid = $row['call_center_tier_uuid'];
					}
					else {
						$call_center_tier_uuid = uuid();
					}
					if (!empty($row['call_center_queue_uuid'])) {
						$array["call_center_tiers"][$y]["call_center_tier_uuid"] = $call_center_tier_uuid;
						$array["call_center_tiers"][$y]["call_center_queue_uuid"] = $row['call_center_queue_uuid'];
						$array["call_center_tiers"][$y]["call_center_agent_uuid"] = $call_center_agent_uuid;
						$array["call_center_tiers"][$y]["tier_level"] = $row['tier_level'];
						$array["call_center_tiers"][$y]["tier_position"] = $row['tier_position'];
						$array["call_center_tiers"][$y]["domain_uuid"] = $domain_uuid;
					}
					$y++;
				}
			}

			$delete_array = [];

			$i = 0;
			foreach ($agent_schedules as $k => $v) {
				if ($v['delete']) {
					$delete_array['call_center_agent_schedules'][count($delete_array)]['call_center_agent_schedule_uuid'] = $v['call_center_agent_schedule_uuid'];
					continue;
				}

				$array['call_center_agent_schedules'][$i]['call_center_agent_schedule_uuid'] = $v['call_center_agent_schedule_uuid'] ?? uuid();
				$array['call_center_agent_schedules'][$i]['call_center_agent_uuid'] = $call_center_agent_uuid;
				$array['call_center_agent_schedules'][$i]['call_center_queue_uuid'] = $v['call_center_queue_uuid'];
				$array['call_center_agent_schedules'][$i]['tier'] = $v['tier'];
				$array['call_center_agent_schedules'][$i]['days_of_week'] = $v['days_of_week'];
				$array['call_center_agent_schedules'][$i]['login_time'] = $v['login_time'];
				$array['call_center_agent_schedules'][$i]['logout_time'] = $v['logout_time'];
				$array['call_center_agent_schedules'][$i]['enabled'] = $v['enabled'];

				if (empty($v['call_center_queue_uuid'])) { $msg .= 'Agent schedule queue cannot be empty<br>'; }
				if ($v['days_of_week'] == 0) { $msg .= 'Agent schedule must have at least one day selected<br>'; }
				if (empty($v['login_time'])) { $msg .= 'Agent schedule login time cannot be empty<br>'; }
				if (empty($v['logout_time'])) { $msg .= 'Agent schedule logout time cannot be empty<br>'; }
				if ($v['logout_time'] < $v['login_time']) { $msg .= 'Agent schedule logout time cannot be before login time<br>'; }

				$dow_labels = [0=>'Sun', 1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat'];
				foreach ($agent_schedules as $l => $w) {
					if ($l == $k || $w['delete'])
						continue;

					for ($d = 0; $d < 7; $d++) {
						if ($v['days_of_week'] & (1 << $d) && $w['days_of_week'] & (1 << $d) && $v['call_center_queue_uuid'] == $w['call_center_queue_uuid'] &&
							(($w['login_time'] >= $v['login_time'] && $w['login_time'] <= $v['logout_time']) || ($w['logout_time'] >= $v['login_time'] && $w['logout_time'] <= $v['logout_time']))) {
							$msg .= 'Schedules overlap on '.$dow_labels[$d].' between '.max($v['login_time'], $w['login_time']).' and '.min($v['logout_time'], $w['logout_time']).'';
						}
					}
				}

				if (!empty($msg) && empty($_POST["persistformvar"])) {
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

				$i++;
			}

		//save to the database
			$p = permissions::new();
			$p->add('call_center_agent_schedule_add', 'temp');
			$p->add('call_center_agent_schedule_edit', 'temp');
			$p->add('call_center_agent_schedule_delete', 'temp');

			$database->save($array);
			$database->delete($delete_array);

			$p->delete('call_center_agent_schedule_add', 'temp');
			$p->delete('call_center_agent_schedule_edit', 'temp');
			$p->delete('call_center_agent_schedule_delete', 'temp');

		//syncrhonize configuration
			save_call_center_xml();

		//clear the cache
			$cache = new cache;
			$cache->delete('configuration:callcenter.conf');

	//get and then set the complete agent_contact with the call_timeout, recording and when necessary confirm
		//if you change this variable, also change resources/switch.php
		if (!$settings->get('call_center', 'use_modern_call_center', null)) {
			$confirm = "group_confirm_file=custom/press_1_to_accept_this_call.wav,group_confirm_key=1,group_confirm_read_timeout=2000,leg_timeout=".$agent_call_timeout;
			if(strstr($agent_contact, '}') === FALSE) {
				//not found
				if(stristr($agent_contact, 'sofia/gateway') === FALSE) {
					//add the call_timeout and recording
					$orig_agent_contact = $agent_contact;
					$agent_contact = "{call_timeout=".$agent_call_timeout.",domain_name=".$_SESSION['domain_name'].",domain_uuid=".$_SESSION['domain_uuid'];
					$agent_contact .= ',sip_h_caller_destination=${caller_destination}';
					if ($agent_record == "true") {
						$agent_contact .= ',execute_on_pre_bridge="record_session ${recordings_dir}/'.$_SESSION['domain_name'].'/archive/${strftime(%Y)}/${strftime(%b)}/${strftime(%d)}/${uuid}.${record_ext}"';
					}
					$agent_contact .= "}".$orig_agent_contact;
				}
				else {
					//add the call_timeout and confirm
					$agent_contact = "{".$confirm.",call_timeout=".$agent_call_timeout.",sip_invite_domain=".$_SESSION['domain_name']."}".$agent_contact;
				}
			}
			else {
				$position = strrpos($agent_contact, "}");
				$first = substr($agent_contact, 0, $position);
				$last = substr($agent_contact, $position);
				//add call_timeout and sip_invite_domain, only if missing
				$call_timeout = (stristr($agent_contact, 'call_timeout') === FALSE) ? ',call_timeout='.$agent_call_timeout : null;
				$sip_invite_domain = (stristr($agent_contact, 'sip_invite_domain') === FALSE) ? ',sip_invite_domain='.$_SESSION['domain_name'] : null;
				if ($agent_record == "true" && stristr($agent_contact, 'record_session') === FALSE) {
					$recording_string = ',execute_on_pre_bridge="record_session ${recordings_dir}/'.$_SESSION['domain_name'].'/archive/${strftime(%Y)}/${strftime(%b)}/${strftime(%d)}/${uuid}.${record_ext}"';
				}
				//compose
				if(stristr($agent_contact, 'sofia/gateway') === FALSE) {
					$agent_contact = $first.$sip_invite_domain.$call_timeout.$recording_string.$last;
				}
				else {
					$agent_contact = $first.','.$confirm.$sip_invite_domain.$call_timeout.$recording_string.$last;
				}
			}
		
		//add the agent
			//setup the event socket connection
				$esl = event_socket::create();
			//add the agent using event socket
				if ($esl->connected()) {
					if (!$settings->get('call_center', 'use_modern_call_center', null)) {
					//add the agent
						$cmd = "callcenter_config agent add ".$call_center_agent_uuid." '".$agent_type."'";
						$response = event_socket::api($cmd);
						usleep(200);
					//agent set contact
						$cmd = "callcenter_config agent set contact ".$call_center_agent_uuid." '".$agent_contact."'";
						$response = event_socket::api($cmd);
						usleep(200);
					//agent set status
						$cmd = "callcenter_config agent set status ".$call_center_agent_uuid." '".$agent_status."'";
						$response = event_socket::api($cmd);
						usleep(200);
					//agent set reject_delay_time
						$cmd = 'callcenter_config agent set reject_delay_time '.$call_center_agent_uuid.' '. $agent_reject_delay_time;
						$response = event_socket::api($cmd);
						usleep(200);
					//agent set busy_delay_time
						$cmd = 'callcenter_config agent set busy_delay_time '.$call_center_agent_uuid.' '.$agent_busy_delay_time;
						$response = event_socket::api($cmd);
					//agent set no_answer_delay_time
						$cmd = 'callcenter_config agent set no_answer_delay_time '.$call_center_agent_uuid.' '.$agent_no_answer_delay_time;
						$response = event_socket::api($cmd);
					//agent set max_no_answer
						$cmd = 'callcenter_config agent set max_no_answer '.$call_center_agent_uuid.' '.$agent_max_no_answer;
						$response = event_socket::api($cmd);
					//agent set wrap_up_time
						$cmd = 'callcenter_config agent set wrap_up_time '.$call_center_agent_uuid.' '.$agent_wrap_up_time;
						$response = event_socket::api($cmd);
					} else {
						$cmd = "sendevent CUSTOM\n";
						$cmd .= "Event-Name: CUSTOM\n";
						$cmd .= "Event-Subclass: callcenter::command\n";
						$cmd .= "CC-Command: update_agent\n";
						$cmd .= "Agent: " . $call_center_agent_uuid . "\n";
						$response = event_socket::command($cmd);
					}
				}
			}

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					message::add($text['message-add']);
				}
				if ($action == "update") {
					message::add($text['message-update']);
				}
				header("Location: call_center_agent_edit.php?id=".urlencode($call_center_agent_uuid));
				return;
			}
	} //(is_array($_POST) && empty($_POST["persistformvar"]))

//initialize the destinations object
	$destination = new destinations;

//pre-populate the form
	if (!empty($_GET["id"]) && is_uuid($_GET["id"]) && empty($_POST["persistformvar"])) {
		$call_center_agent_uuid = $_GET["id"];
		$sql = "select ";
		$sql .= "call_center_agent_uuid, ";
		$sql .= "user_uuid, ";
		$sql .= "agent_name, ";
		$sql .= "agent_type, ";
		$sql .= "agent_call_timeout, ";
		$sql .= "agent_id, ";
		$sql .= "agent_password, ";
		$sql .= "agent_status, ";
		$sql .= "agent_contact, ";
		$sql .= "agent_no_answer_delay_time, ";
		$sql .= "agent_max_no_answer, ";
		$sql .= "agent_wrap_up_time, ";
		$sql .= "agent_reject_delay_time, ";
		$sql .= "agent_busy_delay_time, ";
		$sql .= "agent_record, ";
		$sql .= "agent_use_system_caller_id, ";
		$sql .= "agent_enabled, ";
		$sql .= "agent_confirm_prompt ";
		$sql .= "from v_call_center_agents ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and call_center_agent_uuid = :call_center_agent_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['call_center_agent_uuid'] = $call_center_agent_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (!empty($row)) {
			$call_center_agent_uuid = $row["call_center_agent_uuid"];
			$user_uuid = $row["user_uuid"];
			$agent_name = $row["agent_name"];
			$agent_type = $row["agent_type"];
			$agent_call_timeout = $row["agent_call_timeout"];
			$agent_id = $row["agent_id"];
			$agent_password = $row["agent_password"];
			$agent_status = $row["agent_status"];
			$agent_contact = $row["agent_contact"];
			$agent_no_answer_delay_time = $row["agent_no_answer_delay_time"];
			$agent_max_no_answer = $row["agent_max_no_answer"];
			$agent_wrap_up_time = $row["agent_wrap_up_time"];
			$agent_reject_delay_time = $row["agent_reject_delay_time"];
			$agent_busy_delay_time = $row["agent_busy_delay_time"];
			$agent_record = $row["agent_record"];
			$agent_enabled = $row["agent_enabled"];
			$agent_confirm_prompt = $row["agent_confirm_prompt"];
			$agent_use_system_caller_id = $row["agent_use_system_caller_id"];
			//$agent_logout = $row["agent_logout"];
		}
		unset($sql, $parameters, $row);

		if ($settings->get("call_center", "use_modern_call_center", null)) {
			$sql = "select ";
			$sql .= "call_center_agent_schedule_uuid, ";
			$sql .= "call_center_queue_uuid, ";
			$sql .= "tier, ";
			$sql .= "days_of_week, ";
			$sql .= "login_time, ";
			$sql .= "logout_time, ";
			$sql .= "enabled ";
			$sql .= "from v_call_center_agent_schedules ";
			$sql .= "where call_center_agent_uuid = :call_center_agent_uuid ";
			$parameters['call_center_agent_uuid'] = $call_center_agent_uuid;
			$agent_schedules = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters, $row);
		}
	}

	$sql = "select call_center_queue_uuid, queue_name from v_call_center_queues ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by queue_name asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$call_center_queues = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters, $row);

//set default values
	if (empty($agent_type)) { $agent_type = "callback"; }
	if (empty($agent_call_timeout)) { $agent_call_timeout = "20"; }
	if (empty($agent_max_no_answer)) { $agent_max_no_answer = "0"; }
	if (empty($agent_wrap_up_time)) { $agent_wrap_up_time = "10"; }
	if (empty($agent_no_answer_delay_time)) { $agent_no_answer_delay_time = "30"; }
	if (empty($agent_reject_delay_time)) { $agent_reject_delay_time = "90"; }
	if (empty($agent_busy_delay_time)) { $agent_busy_delay_time = "90"; }
	$agent_record = $agent_record ?? false;
	$agent_enabled = $agent_enabled ?? true;
	$agent_confirm_prompt = $agent_confirm_prompt ?? true;
	$agent_use_system_caller_id = $agent_use_system_caller_id ?? false;
	if (!is_array($agent_schedules)) $agent_schedules = [];
	$i = count($agent_schedules);
	$agent_schedules[$i]['call_center_agent_schedule_uuid'] = "";
	$agent_schedules[$i]['enabled'] = true;

//get the tiers
	$sql = "select t.call_center_tier_uuid, t.call_center_agent_uuid, t.call_center_queue_uuid, t.tier_level, t.tier_position, q.queue_name ";
	$sql .= "from v_call_center_tiers as t, v_call_center_queues as q ";
	$sql .= "where t.call_center_agent_uuid = :call_center_agent_uuid ";
	$sql .= "and t.call_center_queue_uuid = q.call_center_queue_uuid ";
	$sql .= "and t.domain_uuid = :domain_uuid ";
	$sql .= "order by tier_level asc, tier_position asc, q.queue_name asc";
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['call_center_agent_uuid'] = $call_center_agent_uuid ?? null;
	$tiers = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//add an empty row to the tiers array
	if (count($tiers) == 0) {
		$rows = $settings->get('call_center','agent_add_rows', null);
		$id = 0;
	}
	if (count($tiers) > 0) {
		$rows = $settings->get('call_center','agent_edit_rows', null);
		$id = count($tiers)+1;
	}
	for ($x = 0; $x < $rows; $x++) {
		$tiers[$id]['call_center_tier_uuid'] = uuid();
		$tiers[$id]['call_center_agent_uuid'] = $call_center_agent_uuid ?? null;
		$tiers[$id]['call_center_queue_uuid'] = '';
		$tiers[$id]['tier_level'] = '';
		$tiers[$id]['tier_position'] = '';
		$tiers[$id]['agent_name'] = '';
		$id++;
	}

//get the queues
	$sql = "select call_center_queue_uuid, queue_name from v_call_center_queues ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by queue_name asc";
	$parameters['domain_uuid'] = $domain_uuid;
	$queues = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);


//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	if ($action == "add") {
		$document['title'] = $text['title-call_center_agent_add'];
	}
	if ($action == "update") {
		$document['title'] = $text['title-call_center_agent_edit'];
	}

//include the header
	require_once "resources/header.php";

	if ($settings->get('call_center', 'use_modern_call_center', null)) {
		$sql = "select s.days_of_week, s.login_time, s.logout_time, s.tier, s.call_center_queue_uuid, q.queue_name ";
		$sql .= "from v_call_center_agent_schedules as s ";
		$sql .= "join v_call_center_queues as q on s.call_center_queue_uuid = q.call_center_queue_uuid ";
		$sql .= "where s.call_center_agent_uuid = :call_center_agent_uuid ";
		$parameters['call_center_agent_uuid'] = $call_center_agent_uuid ?? null;
		$schedule = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		echo "<script src='/resources/chartjs/chart.min.js'></script>";
		echo "<script src='./chart.js'></script>";
		$modal = "<div id='modal-coverage' class='modal-window'>\n";
		$modal .= "	<div>\n";
		$modal .= "		<span title=\"" . $text['button-close'] . "\" class='modal-close' onclick=\"modal_close();\">&times</span>\n";
		$modal .= "		<span class='modal-title'>Coverage</span>\n";
		$modal .= "		<span class='modal-message'><div style='width: 100%; height: 100%'><canvas id='coverage_chart'></canvas></div></span>\n";
		$modal .= "		<span class='modal-actions'>" . button::create(['type'=>'button','label'=>$text['button-close'],'style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close()"]) . "</span>\n";
		$modal .= "	</div>\n";
		$modal .= "</div>";
		echo $modal;

		?>
		<script>
			{
				const datasets = {}

				const schedules = JSON.parse('<?php echo json_encode($schedule); ?>')
				for (let schedule of schedules) {
					let dataset = datasets[schedule.call_center_queue_uuid]
					if (!dataset) {
						dataset = datasets[schedule.call_center_queue_uuid] = {}
						dataset.label = schedule.queue_name
						dataset.data = []
					}

					for (let day = 0; day < 7; day++) {
						if (schedule.days_of_week & (1 << day)) {
							dataset.data.push({
								y: coverage_chart_labels[day],
								x: [coverage_chart_time_to_num(schedule.login_time), coverage_chart_time_to_num(schedule.logout_time)],
								tier: schedule.tier
							})
						}
					}
				}

				window.coverage_chart = new Chart(document.getElementById('coverage_chart'), {
					type: 'bar',
					data: { labels: coverage_chart_labels, datasets: Object.values(datasets) },
					options: coverage_chart_options
				})
			}
		</script>
		<?php
	}

//show the content
	echo "<form method='post' name='frm' id='frm' onsubmit=''>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-call_center_agent_add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-call_center_agent_edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','link'=>'call_center_agents.php']);
	if ($settings->get('call_center', 'use_modern_call_center', null)) {
		echo button::create(['type'=>'button','label'=>'Coverage','icon'=>$settings->get('theme','button_icon_view', ''),'onclick'=>"modal_open('modal-coverage')"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$settings->get('theme', 'button_icon_save'),'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-agent_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='agent_name' maxlength='255' value=\"".escape($agent_name)."\" />\n";
	echo "<br />\n";
	echo $text['description-agent_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!$settings->get('call_center', 'use_modern_call_center', null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-type']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='agent_type' maxlength='255' value=\"".escape($agent_type)."\" pattern='^(callback|uuid-standby)$'>\n";
		echo "<br />\n";
		echo $text['description-type']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-call_timeout']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_call_timeout' maxlength='255' min='1' step='1' value='".escape($agent_call_timeout)."'>\n";
	echo "<br />\n";
	echo $text['description-call_timeout']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>";
	echo "		<td class='vncell' valign='top'>".$text['label-username']."</td>";
	echo "		<td class='vtable' align='left'>";
	echo "			<select name=\"user_uuid\" class='formfld' style='width: auto;'>\n";
	echo "			<option value=\"\"></option>\n";
	foreach ($users as $field) {
		echo "			<option value='".escape($field['user_uuid'])."' ".(!empty($user_uuid) && $user_uuid == $field['user_uuid'] ? "selected='selected'" : null).">".escape($field['username'])."</option>\n";
	}
	echo "			</select>";
	unset($users);
	echo "			<br>\n";
	echo "			".!empty($text['description-users'])."\n";
	echo "		</td>";
	echo "	</tr>";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-agent_id']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_id' id='agent_id' maxlength='255' min='1' step='1' value='".escape($agent_id)."'>\n";
	echo "<br />\n";
	echo $text['description-agent_id']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!$settings->get('call_center', 'use_modern_call_center', null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-agent_password']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld password' type='password' name='agent_password' autocomplete='off' onmouseover=\"this.type='text';\" onfocus=\"this.type='text';\" onmouseout=\"if (!\$(this).is(':focus')) { this.type='password'; }\" onblur=\"this.type='password';\" maxlength='255' min='1' step='1' value='".escape($agent_password)."'>\n";
		echo "<br />\n";
		echo $text['description-agent_password']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-contact']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	if ($settings->get('call_center', 'use_modern_call_center', null))
		echo "<input class='formfld' type='text' name='agent_contact' id='agent_contact' value='".escape($agent_contact)."'>\n";
	else
		echo $destination->select('user_contact', 'agent_contact', ($agent_contact ?? null));
	echo "<br />\n";
	echo $text['description-contact']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!$settings->get('call_center', 'use_modern_call_center', null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-status']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='agent_status'>\n";
		echo "		<option value=''></option>\n";
		echo "		<option value='Logged Out' ".(!empty($agent_status) && $agent_status == "Logged Out" ? "selected='selected'" : null).">".$text['option-logged_out']."</option>\n";
		echo "		<option value='Available' ".(!empty($agent_status) && $agent_status == "Available" ? "selected='selected'" : null).">".$text['option-available']."</option>\n";
		echo "		<option value='Available (On Demand)' ".(!empty($agent_status) && $agent_status == "Available (On Demand)" ? "selected='selected'" : null).">".$text['option-available_on_demand']."</option>\n";
		echo "		<option value='On Break' ".(!empty($agent_status) && $agent_status == "On Break" ? "selected='selected'" : null).">".$text['option-on_break']."</option>\n";
		echo "	</select>\n";
		echo "<br />\n";
		echo $text['description-status']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-no_answer_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_no_answer_delay_time' maxlength='255' min='0' step='1' value='".escape($agent_no_answer_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-no_answer_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!$settings->get('call_center', 'use_modern_call_center', null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-max_no_answer']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld' type='number' name='agent_max_no_answer' maxlength='255' min='0' step='1' value='".escape($agent_max_no_answer)."'>\n";
		echo "<br />\n";
		echo $text['description-max_no_answer']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-wrap_up_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_wrap_up_time' maxlength='255' min='0' step='1' value='".escape($agent_wrap_up_time)."'>\n";
	echo "<br />\n";
	echo $text['description-wrap_up_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-reject_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_reject_delay_time' maxlength='255' min='0' step='1' value='".escape($agent_reject_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-reject_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-busy_delay_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='agent_busy_delay_time' maxlength='255' min='1' step='1' value='".escape($agent_busy_delay_time)."'>\n";
	echo "<br />\n";
	echo $text['description-busy_delay_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-record_template']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	if ($input_toggle_style_switch) {
		echo "	<span class='switch'>\n";
	}
	echo "		<select class='formfld' id='agent_record' name='agent_record'>\n";
	echo "			<option value='true' ".($agent_record == true ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
	echo "			<option value='false' ".($agent_record == false ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
	echo "		</select>\n";
	if ($input_toggle_style_switch) {
		echo "		<span class='slider'></span>\n";
		echo "	</span>\n";
	}
	echo "<br />\n";
	echo $text['description-record_template']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if ($settings->get("call_center", "use_modern_call_center", null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	Use system caller ID\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		if ($input_toggle_style_switch) {
			echo "	<span class='switch'>\n";
		}
		echo "		<select class='formfld' id='agent_use_system_caller_id' name='agent_use_system_caller_id'>\n";
		echo "			<option value='true' ".($agent_use_system_caller_id == true ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "			<option value='false' ".($agent_use_system_caller_id == false ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
		echo "		</select>\n";
		if ($input_toggle_style_switch) {
			echo "		<span class='slider'></span>\n";
			echo "	</span>\n";
		}
		echo "<br />\n";
		echo "Use the default outbound caller ID instead of the caller's\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if ($settings->get("call_center", "use_modern_call_center", null)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	Play confirm prompt\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		if ($input_toggle_style_switch) {
			echo "	<span class='switch'>\n";
		}
		echo "		<select class='formfld' id='agent_confirm_prompt' name='agent_confirm_prompt'>\n";
		echo "			<option value='true' ".($agent_confirm_prompt == true ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "			<option value='false' ".($agent_confirm_prompt == false ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
		echo "		</select>\n";
		if ($input_toggle_style_switch) {
			echo "		<span class='slider'></span>\n";
			echo "	</span>\n";
		}
		echo "<br />\n";
		echo "Play a confirmation prompt with queue recording when agent picks up before connecting the call\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	Enabled\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		if ($input_toggle_style_switch) {
			echo "	<span class='switch'>\n";
		}
		echo "		<select class='formfld' id='agent_enabled' name='agent_enabled'>\n";
		echo "			<option value='true' ".($agent_enabled == true ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "			<option value='false' ".($agent_enabled == false ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
		echo "		</select>\n";
		if ($input_toggle_style_switch) {
			echo "		<span class='slider'></span>\n";
			echo "	</span>\n";
		}
		echo "</td>\n";
		echo "</tr>\n";
	}

	/*
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-agent_logout']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='text' name='agent_logout' maxlength='255' value='".escape($agent_logout)."'>\n";
	echo "<br />\n";
	echo $text['description-agent_logout']."\n";
	echo "</td>\n";
	echo "</tr>\n";
	*/

	echo "<tr>";
	echo "	<td class='vncell' valign='top'>".$text['label-queues']."</td>";
	echo "	<td class='vtable' align='left'>";
	echo "			<table border='0' cellpadding='0' cellspacing='0'>\n";
	echo "			<tr>\n";
	echo "				<td class='vtable'>".$text['label-queue_name']."</td>\n";
	echo "				<td class='vtable' style='text-align: center;'>".$text['label-tier_level']."</td>\n";
	if (!$settings->get('call_center', 'use_modern_call_center', null)) {
		echo "				<td class='vtable' style='text-align: center;'>".$text['label-tier_position']."</td>\n";
	}
	echo "				<td></td>\n";
	echo "			</tr>\n";
	$x = 0;
	if (is_array($tiers)) {
		foreach($tiers as $field) {
			echo "	<tr>\n";
			echo "		<td class=''>";
			if (!empty($field['call_center_tier_uuid'])) {
				echo "				<input name='call_center_tiers[".$x."][call_center_tier_uuid]' type='hidden' value=\"".escape($field['call_center_tier_uuid'])."\">\n";
			}
			echo "				<select name=\"call_center_tiers[$x][call_center_queue_uuid]\" class=\"formfld\" style=\"width: 200px\">\n";
			if (is_uuid($field['call_center_queue_uuid'])) {
				echo "				<option value=\"".escape($field['call_center_queue_uuid'])."\">".escape($field['queue_name'])."</option>\n";
			}
			else {
				echo "					<option value=\"\"></option>\n";
				foreach($queues as $row) {
					echo "				<option value=\"".escape($row['call_center_queue_uuid'])."\">".escape($row['queue_name'])."</option>\n";
				}
			}
			echo "				</select>";
			echo "		</td>\n";
			echo "		<td class='' style='text-align: center;'>";
			echo "				 <select name=\"call_center_tiers[$x][tier_level]\" class=\"formfld\">\n";
			$i=1;
			while($i<=9) {
				$selected = ($i == $field['tier_level']) ? "selected" : null;
				echo "				<option value=\"$i\" $selected>$i</option>\n";
				$i++;
			}
			echo "				</select>\n";
			echo "		</td>\n";

			if (!$settings->get('call_center', 'use_modern_call_center', null)) {
				echo "		<td class='' style='text-align: center;'>\n";
				echo "				<select name=\"call_center_tiers[$x][tier_position]\" class=\"formfld\">\n";
				$i=0;
				while($i<=9) {
					$selected = ($i == $field['tier_position']) ? "selected" : null;
					echo "				<option value=\"$i\" $selected>$i</option>\n";
					$i++;
				}
				echo "				</select>\n";
				echo "		</td>\n";
			}
			echo "		<td class=''>";
			if (permission_exists('call_center_tier_delete')) {
				echo "			<a href=\"call_center_queue_edit.php?id=".escape($field['call_center_queue_uuid'])."&call_center_tier_uuid=".escape($field['call_center_tier_uuid'])."&a=delete\" alt=\"".$text['button-delete']."\" onclick=\"return confirm('".$text['confirm-delete']."');\">$v_link_label_delete</a>";
				}
			echo "		</td>\n";
			echo "	</tr>\n";
			$assigned_agents[] = $field['agent_name'];
			$x++;
		}
		unset ($tiers);
		echo "		</table>\n";
		echo "		<br>\n";
		echo "		".$text['description-tiers']."\n";
		echo "		<br />\n";
		echo "	</td>";
		echo "</tr>";
	}

	if ($settings->get("call_center", "use_modern_call_center", null)) {
		echo "	<tr>";
		echo "		<td class='vncell' valign='top'>Agent schedule</td>";
		echo "		<td class='vtable' align='left'>";
		echo "			<table border='0' cellpadding='0' cellspacing='0'>\n";
		echo "				<tr>\n";
		echo "					<td class='vtable'>Queue</td>\n";
		echo "					<td class='vtable'>Tier</td>\n";
		echo "					<td class='vtable'>Days of week</td>\n";
		echo "					<td class='vtable'>Login time</td>\n";
		echo "					<td class='vtable'>Logout time</td>\n";
		echo "					<td class='vtable'>Enabled</td>\n";
		echo "					<td class='vtable edit_delete_checkbox_all' onmouseover=\"swap_display('delete_label_options', 'delete_toggle_options');\" onmouseout=\"swap_display('delete_label_options', 'delete_toggle_options');\">\n";
		echo "						<span id='delete_label_options'>".$text['label-delete']."</span>\n";
		echo "						<span id='delete_toggle_options'><input type='checkbox' id='checkbox_all_options' name='checkbox_all' onclick=\"edit_all_toggle('options');\"></span>\n";
		echo "					</td>\n";
		echo "				</tr>\n";

		$x = 0;
		foreach($agent_schedules as $field) {

			//add the primary key uuid
			if (!empty($field['call_center_agent_schedule_uuid'])) {
				echo "	<input name='agent_schedules[".$x."][call_center_agent_schedule_uuid]' type='hidden' value=\"".escape($field['call_center_agent_schedule_uuid'])."\">\n";
			}

			echo "<td class='formfld' align='left'>\n";
			echo "	<select class='formfld' name='agent_schedules[".$x."][call_center_queue_uuid]'>\n";
			echo "		<option value=''></option>";
			if (is_array($call_center_queues) && @sizeof($call_center_queues) != 0) {
				foreach ($call_center_queues as $row) {
					$selected = ($row['call_center_queue_uuid'] == $field['call_center_queue_uuid']) ? "selected" : null;
					echo "<option value='".escape($row['call_center_queue_uuid'])."' $selected>".escape($row['queue_name'])."</option>";
				}
			}
			echo "	</select>\n";
			echo "</td>";

			echo "<td class='formfld' align='left'>\n";
			echo "	<input class='formfld' type='number' min='1' max='100' name='agent_schedules[".$x."][tier]' value=\"".escape($field['tier'] ?? "1")."\">\n";
			echo "</td>\n";

			echo "<td class='vtable' style='text-align: center; padding-bottom: 3px;'>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][sun]' value='1' ".($field['days_of_week'] & (1 << 0) ? 'checked' : '')." class='chk_delete checkbox_options'> Sun</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][mon]' value='1' ".($field['days_of_week'] & (1 << 1) ? 'checked' : '')." class='chk_delete checkbox_options'> Mon</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][tue]' value='1' ".($field['days_of_week'] & (1 << 2) ? 'checked' : '')." class='chk_delete checkbox_options'> Tue</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][wed]' value='1' ".($field['days_of_week'] & (1 << 3) ? 'checked' : '')." class='chk_delete checkbox_options'> Wed</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][thu]' value='1' ".($field['days_of_week'] & (1 << 4) ? 'checked' : '')." class='chk_delete checkbox_options'> Thu</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][fri]' value='1' ".($field['days_of_week'] & (1 << 5) ? 'checked' : '')." class='chk_delete checkbox_options'> Fri</label>";
			echo "	<label><input type='checkbox' name='agent_schedules[".$x."][dow][sat]' value='1' ".($field['days_of_week'] & (1 << 6) ? 'checked' : '')." class='chk_delete checkbox_options'> Sat</label>";
			echo "</td>";

			echo "<td class='formfld' align='left'>\n";
			echo "	<input class='formfld' type='time' name='agent_schedules[".$x."][login_time]' value=\"".escape($field['login_time'])."\">\n";
			echo "</td>\n";

			echo "<td class='formfld' align='left'>\n";
			echo "	<input class='formfld' type='time' name='agent_schedules[".$x."][logout_time]' value=\"".escape($field['logout_time'])."\">\n";
			echo "</td>\n";

			echo "<td class='formfld'>\n";
			if ($input_toggle_style_switch) {
				echo "	<span class='switch'>\n";
			}
			echo "	<select class='formfld' id='agent_schedules_".$x."_enabled' name='agent_schedules[".$x."][enabled]'>\n";
			echo "		<option value='false' ".($field['enabled'] == false ? 'selected' : null).">".$text['option-false']."</option>\n";
			echo "		<option value='true' ".($field['enabled'] == false ? null : 'selected').">".$text['option-true']."</option>\n";
			echo "	</select>\n";
			if ($input_toggle_style_switch) {
				echo "		<span class='slider'></span>\n";
				echo "	</span>\n";
			}
			echo "</td>\n";

			if (!empty($field['call_center_agent_schedule_uuid']) && is_uuid($field['call_center_agent_schedule_uuid'])) {
				echo "<td class='vtable' style='text-align: center; padding-bottom: 3px;'>";
				echo "	<input type='checkbox' name='agent_schedules[".$x."][delete]' value='true' ".($field['delete'] ? 'checked' : null)." class='chk_delete checkbox_options' onclick=\"edit_delete_action('options');\">\n";
			}
			else {
				echo "<td>";
			}
			echo "</td>\n";

			echo "</tr>\n";

			$x++;
		}

		echo "</table>";
		echo "</div>\n";
		echo "<br /><br />";
	}

	if ($action == "update") {
		echo "<input type='hidden' name='call_center_agent_uuid' value='".escape($call_center_agent_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
