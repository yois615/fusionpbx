<?php

	//application details
		$apps[$x]['name'] = 'circle_surveys';
		$apps[$x]['uuid'] = '32af1175-9f22-4073-9499-33b50bbddad5';
		$apps[$x]['category'] = '';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = 'http://www.fusionpbx.com';
		$apps[$x]['description']['en-us'] = '';
		$apps[$x]['description']['en-gb'] = '';

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_view';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_add';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_delete';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_edit';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_destinations';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_survey_all';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;

		//destination details
		$y=0;
		$apps[$x]['destinations'][$y]['type'] = "sql";
		$apps[$x]['destinations'][$y]['label'] = "circle_surveys";
		$apps[$x]['destinations'][$y]['name'] = "circle_surveys";
		$apps[$x]['destinations'][$y]['sql'] = "select circle_survey_uuid as uuid, name as name, circle_survey_uuid as destination, description as description from v_circle_surveys";
		$apps[$x]['destinations'][$y]['where'] = "where domain_uuid = '\${domain_uuid}' ";
		$apps[$x]['destinations'][$y]['order_by'] = "name asc";
		$apps[$x]['destinations'][$y]['field']['uuid'] = "circle_survey_uuid";
		$apps[$x]['destinations'][$y]['field']['name'] = "name";
		$apps[$x]['destinations'][$y]['field']['destination'] = "name";
		$apps[$x]['destinations'][$y]['field']['description'] = "description";
		$apps[$x]['destinations'][$y]['select_value']['dialplan'] = "lua:circle_survey.lua \${destination}";
		$apps[$x]['destinations'][$y]['select_value']['ivr'] = "menu-exec-app:lua circle_survey.lua \${destination}";
		$apps[$x]['destinations'][$y]['select_label'] = "\${name}";

	//Votes
		$y = 0;
		$apps[$x]['db'][$y]['table']['name'] = 'v_circle_survey_customer';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_customer_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'domain_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_domains';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'domain_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'caller_id_number';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'caller_id_name';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'gender';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'age';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'zip_code';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'v_circle_survey_votes';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'vote';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_customer_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_circle_survey_customer';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'circle_survey_customer_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_circle_survey';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'circle_survey_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'domain_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_domains';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'domain_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'call_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'sequence_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'v_circle_surveys';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'domain_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_domains';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'domain_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'name';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'description';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'greeting';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'exit_file';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'age_file';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'gender_file';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'zip_code_file';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'question_answered_file';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'exit_action';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'v_circle_survey_questions';
		$apps[$x]['db'][$y]['table']['parent'] = 'v_circle_surveys';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_question_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'circle_survey_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_circle_survey';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'circle_survey_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'domain_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'v_domains';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'domain_uuid';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'sequence_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'recording';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'description';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'highest_number';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;

?>
