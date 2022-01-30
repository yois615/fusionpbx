<?php

	//application details
		$apps[$x]['name'] = "Destinations";
		$apps[$x]['uuid'] = "5ec89622-b19c-3559-64f0-afde802ab139";
		$apps[$x]['category'] = "Switch";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.1";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "http://www.fusionpbx.com";
		$apps[$x]['description']['en-us'] = "Used to define external destination numbers.";
		$apps[$x]['description']['en-gb'] = "Used to define external destination numbers.";
		$apps[$x]['description']['ar-eg'] = "";
		$apps[$x]['description']['de-at'] = "Wird verwendet um externe Ziele zu definieren.";
		$apps[$x]['description']['de-ch'] = "";
		$apps[$x]['description']['de-de'] = "Wird verwendet um externe Ziele zu definieren.";
		$apps[$x]['description']['es-cl'] = "Utilizado para definir números de destino externos.";
		$apps[$x]['description']['es-mx'] = "Utilizado para definir numeros destinos externos.";
		$apps[$x]['description']['fr-ca'] = "Usé pour définir cibler nombres externe.";
		$apps[$x]['description']['fr-fr'] = "Défini les numéros externes.";
		$apps[$x]['description']['he-il'] = "";
		$apps[$x]['description']['it-it'] = "";
		$apps[$x]['description']['nl-nl'] = "Gebruikt om externe bestemmingen vast te leggen.";
		$apps[$x]['description']['pl-pl'] = "";
		$apps[$x]['description']['pt-br'] = "Usado para gerenciar números de destinos externos.";
		$apps[$x]['description']['pt-pt'] = "Utilizado para definir os números de destino externos.";
		$apps[$x]['description']['ro-ro'] = "";
		$apps[$x]['description']['ru-ru'] = "";
		$apps[$x]['description']['sv-se'] = "";
		$apps[$x]['description']['uk-ua'] = "";

	//destination details
		$y=0;
		$apps[$x]['destinations'][$y]['type'] = "sql";
		$apps[$x]['destinations'][$y]['label'] = "destinations";
		$apps[$x]['destinations'][$y]['name'] = "destinations";
		$apps[$x]['destinations'][$y]['sql'] = "select destination_uuid, destination_number, destination_context, destination_description from v_destinations ";
		$apps[$x]['destinations'][$y]['where'] = "where (domain_uuid = '\${domain_uuid}' or domain_uuid is null) and (destination_type = 'outbound' or destination_type = 'local') and destination_enabled = 'true' ";
		$apps[$x]['destinations'][$y]['order_by'] = "destination_number asc";
		$apps[$x]['destinations'][$y]['field']['destination_uuid'] = "destination_uuid";
		$apps[$x]['destinations'][$y]['field']['destination_number'] = "destination_number";
		$apps[$x]['destinations'][$y]['field']['destination_context'] = "destination_context";
		$apps[$x]['destinations'][$y]['field']['destination_description'] = "destination_description";
		$apps[$x]['destinations'][$y]['select_value']['dialplan'] = "transfer:\${destination_number} XML \${destination_context}";
		$apps[$x]['destinations'][$y]['select_value']['ivr'] = "menu-exec-app:transfer \${destination_number} XML \${destination_context}";
		$apps[$x]['destinations'][$y]['select_value']['user_contact'] = "loopback/\${destination_number}";
		$apps[$x]['destinations'][$y]['select_label'] = "\${destination_number} \${destination_description}";
		/*
		$y++;
		$apps[$x]['destinations'][$y]['type'] = 'array';
		$apps[$x]['destinations'][$y]['label'] = 'other';
		$apps[$x]['destinations'][$y]['name'] = 'dialplans';
		$apps[$x]['destinations'][$y]['field']['name']  = 'name';
		$apps[$x]['destinations'][$y]['field']['destination'] = 'destination';
		$apps[$x]['destinations'][$y]['select_value']['dialplan'] = "transfer:\${destination}";
		$apps[$x]['destinations'][$y]['select_value']['ivr'] = "menu-exec-app:transfer \${destination}";
		$apps[$x]['destinations'][$y]['select_label'] = "\${name}";
		$z=0;
		$apps[$x]['destinations'][$y]['result']['data'][$z]['name'] = 'check_voicemail';
		$apps[$x]['destinations'][$y]['result']['data'][$z]['destination'] = '*98 XML ${context}';
		$z++;
		$apps[$x]['destinations'][$y]['result']['data'][$z]['name'] = 'company_directory';
		$apps[$x]['destinations'][$y]['result']['data'][$z]['destination'] = '*411 XML ${context}';
		$z++;
		$apps[$x]['destinations'][$y]['result']['data'][$z]['name'] = 'hangup';
		$apps[$x]['destinations'][$y]['result']['data'][$z]['destination'] = 'hangup';
		$z++;
		$apps[$x]['destinations'][$y]['result']['data'][$z]['name'] = 'company_directory';
		$apps[$x]['destinations'][$y]['result']['data'][$z]['destination'] = '*732 XML ${context}';
		*/

	//permission details
		$y=0;
		$apps[$x]['permissions'][$y]['name'] = "destination_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "fd2a708a-ff03-c707-c19d-5a4194375eba";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_add";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_edit";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_delete";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_import";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_export";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_upload";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_domain";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_all";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_record";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_trunk_prefix";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_area_code";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_number";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_condition_field";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_caller_id_name";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_caller_id_number";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_context";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_accountcode";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_fax";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_user_uuid";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_emergency";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "destination_destinations";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "other_destinations";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;

	//default settings
		$y = 0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "70d8538a-89ab-4db6-87b1-f5e447680283";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "limit";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "destinations";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "3";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "911412de-e0a6-49db-a3c8-65f05c9d847f";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "destinations";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "dialplan_details";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "3141e4ad-a892-4a51-8789-aa27be54ee94";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "destinations";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "dialplan_mode";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "multiple";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Options: multiple, single";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "b132ff5a-da8d-4846-b46d-2f0bfa9ae96b";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "destinations";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "unique";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Require destinations to be unique true or false.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d6cf39aa-edc0-4682-9868-5f8198b3383c";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "destinations";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "select_mode";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "default";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Options: default, dynamic";
		$y++;

	//cache details
		$apps[$x]['cache']['key'] = "dialplan.\${destination_context}";

	//schema details
		$y=0;
		$apps[$x]['db'][$y]['table']['name'] = "v_destinations";
		$apps[$x]['db'][$y]['table']['parent'] = "";
		$z=0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "domain_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_domains";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "domain_uuid";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "primary";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "dialplan_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_dialplans";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "dialplan_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "fax_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_fax";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "fax_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "user_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_users";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "user_uuid";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "group_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_groups";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "group_uuid";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name']['text'] = "destination_type";
		$apps[$x]['db'][$y]['fields'][$z]['name']['deprecated'] = "destination_name";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Select the type.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name']['text'] = "destination_number";
		$apps[$x]['db'][$y]['fields'][$z]['name']['deprecated'] = "destination_extension";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the number.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_trunk_prefix";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the trunk prefix.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_area_code";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the area code.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_prefix";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the prefix.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name']['text'] = "destination_condition_field";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the condition.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_number_regex";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Regular Expression version of destination number";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_caller_id_name";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the caller id name.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_caller_id_number";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the caller id number.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_cid_name_prefix";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the caller id name prefix.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_context";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the context.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_record";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Select whether to record the call.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_hold_music";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Select whether to set music on hold.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_accountcode";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the accountcode.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_type_voice";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "numeric";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Number is used for voice calls.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_type_fax";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "numeric";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Number is used for fax calls.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_type_emergency";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "numeric";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Number is used to place emergency calls.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_type_text";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "numeric";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Number is used for text messages.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_app";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the application.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_data";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the data.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_alternate_app";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the alternate application.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_alternate_data";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the alternate data.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_order";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "numeric";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Set the destination order.";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_enabled";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "";
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "destination_description";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['search'] = 'true';
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enter the description.";

?>
