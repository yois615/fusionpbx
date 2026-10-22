<?php
//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('webphone_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the header
	require_once "resources/header.php";

//includes
?>
<style>
  nav ~ .container-fluid {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  #content_container {
	height: 100%;
  }

  #main_content {
    flex-grow: 1;
	height: 100%;
  }
</style>
<?php

$document['title'] = "Phone";


$sql = "SELECT extension_uuid FROM v_extension_users WHERE domain_uuid = '" . $domain_uuid . "' AND user_uuid = '".$_SESSION['user_uuid']."'";

$database = new database;
$extension_uuid = $database->select($sql, null, 'column');
unset($sql);

if (empty($extension_uuid)){
		echo "Your user is not set up to use the webphone.<br>Please contact your administrator.";
		exit;
}

 $sql = "SELECT extension, password FROM v_extensions WHERE extension_uuid = '".$extension_uuid."'";
$database = new database;
$row = $database->select($sql, null, 'all');
 $extension = $row[0]['extension'];
 $password = $row[0]['password'];
unset($sql);

$sql = "SELECT contact_name from view_users where domain_name = '$_SESSION[domain_name]' AND username = '$_SESSION[username]'";
$database = new database;
$contactName = $database->select($sql, null, 'column');
 if($contactName == ""){
    $contactName = $extension;
 }
unset($sql);



echo "<iframe src='https://$_SESSION[domain_name]/app/webphone/resources/Phone/index.php?server=$_SESSION[domain_name]&extension=$extension&password=$password&fullname=$contactName' width='100%' height='100%' frameborder='none'></iframe>";
echo "<br /><br />";

//include the footer
require_once "resources/footer.php";
?>
