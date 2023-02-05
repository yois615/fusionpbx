<?php

	//application details
		$apps[$x]['name'] = 'Circle Survey';
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

	//Votes
		$y = 0;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_survey_customer';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'customer_id';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'serial';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'caller_id_number';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'caller_id_name';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_survey_votes';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'vote';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'customer_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'circle_customer';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'customer_id';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'call_uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = 'uuid';
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = 'text';
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = 'char(36)';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'week_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'article_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;


?>
