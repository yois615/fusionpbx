<?php

	//application details
		$apps[$x]['name'] = "Webphone";
		$apps[$x]['uuid'] = "2f10f39d-d7f7-4565-9b28-50514f915078";
		$apps[$x]['category'] = "Switch";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "http://www.fusionpbx.com";
		$apps[$x]['description']['en-us'] = "WebRTC Softphone";
		$apps[$x]['description']['en-gb'] = "WebRTC Softphone";


	//permission details
		$y=0;
		$apps[$x]['permissions'][$y]['name'] = "webphone_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "39f53cf0-05e8-43d6-a428-6953ab4b69e4";
		$apps[$x]['permissions'][$y]['groups'][] = "user";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";

?>