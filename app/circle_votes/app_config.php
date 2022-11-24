<?php

	//application details
		$apps[$x]['name'] = 'Circle Votes';
		$apps[$x]['uuid'] = '01e92702-6b4b-11ed-a1eb-0242ac1ef276';
		$apps[$x]['category'] = '';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = 'http://www.fusionpbx.com';
		$apps[$x]['description']['en-us'] = '';
		$apps[$x]['description']['en-gb'] = '';

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = 'circle_votes_view';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_votes_delete';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;

	//Votes
		$y = 0;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_tt_votes';
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

?>
