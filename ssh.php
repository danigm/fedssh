<html>
<head>
<title>SSH por federacion</title>
</head>
<body>

<?php

$mysqlhost = "localhost";
$mysqlu = "federacionssh";
$mysqlp = "fedssh.[pass]";
$mysqldb = "federacionssh";

if (!mysql_connect($mysqlhost, $mysqlu, $mysqlp)) {
	print "Mejor lo dejamos, que no puedo ni conectar a la BD";
	exit;
}

if (!mysql_select_db($mysqldb)) {
	print "No puedo abrir la BD, ¡pasando!";
	exit;
}


function display_form($color) {
?>
    <form action="" method="POST">
        <textarea name="key" cols="60" rows="5"  style="border: 1px solid black;">texto</textarea><br/>
        <input type="submit" style="border: 1px solid black;"></input>
    </form>
<?php
}
$cad = $_SERVER["REMOTE_USER"];
$nado = explode('@', $cad);
$name = $nado[0];
$dominio = substr($nado[1], 0, stripos($nado[1], '.'));
$name = $name.'-'.$dominio;
echo "<p>Welcome ".$name."</p>";

// Check if userCertificate attribute is set in SAML response
if (isset($_SERVER["userCertificate"])) {
    $certificate = $_SERVER["userCertificate"];
    echo "<p>Your public key was fetched from your home organisation LDAP</p>";
} else {

    // Public key was not received from IdP. First check if user is posting its public key
    if (isset($_POST['key'])) {
        $certificate = $_POST['key'];
    } else {

        // Display form where public key can be submitted
        echo "<p>Your public key could not be fetched from your home organisation. You may upload your key using the form below, 
        using the following syntax:</p>";
        display_form("#000000");
    }
}

// If $certificate is set, public key has been successfully received
if (isset($certificate)) {

    // Trim certificate string
    $certificate = trim($certificate);
    $certificate = str_replace("\r","",$certificate);
    $certificate = str_replace("\n","",$certificate);

    // Check if certificate syntax is correct
    if (substr($certificate, 0, 7) == "ssh-rsa" || substr($certificate, 0, 7) == "ssh-dss") {

        // Store public key in user's authorized_keys file
        //$command = "sudo /var/www/sshfed/addkey.sh testuser \"$certificate\"";
        //$command = 'echo "'.$name.':'.$certificate.'" >> applog';
	$q = sprintf("insert into pubkey (uid, init, timeout, pubkey) values ('%s', NOW(), '300', '%s')",
			mysql_real_escape_string($name), mysql_real_escape_string($certificate));
	$response = mysql_query($q);
        //$response = exec($command, $output, $return);

        // Check if command executed successfully
        if($response) {
            echo "<p>DEBUG: OK:</p>";
            echo "<p>You may now log in using SSH, authenticating with ". $name ."</p>";
        } else {
            echo "<p>DEBUG: NOK</p>";
        }
    } else {

        // Certificate syntax is not valid
        echo "<p><font color=\"#FF0000\">Certificate syntax is not valid! Valid syntax for the key is:</font></p>";
        display_form("#FF0000");
    }
}



?>

</body>
</html>
