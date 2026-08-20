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
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

function persistformvar($form_array) {
	// Remember Form Input Values
	if (!empty($form_array)) {
		echo "<form method='post' action='".escape($_SERVER["HTTP_REFERER"] ?? '')."' target='_self'>\n";
		foreach ($form_array as $key => $val) {
			if ($key == "XID" || $key == "ACT" || $key == "RET" || $key == "persistform") continue;
			if (is_array($val))
				persistformarray($key, $val);
			else
				echo "	<input type='hidden' name='".escape($key)."' value='".escape($val ?? '')."' />\n";
		}
		echo "	<input type='hidden' name='persistformvar' value='true' />\n"; //sets persistform to yes
		echo "	<input class='btn' type='submit' value='Back' />\n";
		echo "</form>\n";
	}
}
//persistformvar($_POST);
//persistformvar($_GET);

function persistformarray($path, $array) {
	foreach ($array as $key => $val) {
		if (is_array($val))
			persistformarray($path . "[".$key."]", $val);
		else
			echo "	<input type='hidden' name='".escape($path . "[".$key."]")."' value='".escape($val ?? '')."' />\n";
	}
}

?>