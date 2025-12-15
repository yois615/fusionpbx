<?php

	//application details
		$apps[$x]['name'] = 'Circle Raffle';
		$apps[$x]['uuid'] = '01eef202-6b4b-11ed-a1eb-0242ac152976';
		$apps[$x]['category'] = '';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = 'http://www.fusionpbx.com';
		$apps[$x]['description']['en-us'] = '';
		$apps[$x]['description']['en-gb'] = '';

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = 'circle_raffle_view';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'circle_raffle_delete';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		//$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;

	//Votes
		$y = 0;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_raffle_customer';
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
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'zip';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'text';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_raffle_numbers';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'winnning_number';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'winning_customer_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'circle_raffle_customer';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'customer_id';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'call_epoch';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;

		$y++;
		$apps[$x]['db'][$y]['table']['name'] = 'circle_raffle_cdr';
		$apps[$x]['db'][$y]['table']['parent'] = '';
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'customer_id';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'foreign';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = 'circle_raffle_customer';
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = 'customer_id';
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = 'call_epoch';
		$apps[$x]['db'][$y]['fields'][$z]['type'] = 'numeric';
		$z++;

?>
