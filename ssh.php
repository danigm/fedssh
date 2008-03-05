<?php 
//Esta cosita sirve para poder cerrar una aplicacion, aunque
//las lazy sessions del shibboleth permitan el acceso
session_start();
$GLOBALS['shib_Https'] = false;
$GLOBALS['shib_AssertionConsumerServiceURL'] = "/federacion21.us.es/Shibboleth.sso";
$GLOBALS['shib_WAYF'] = "federacion21.us.es";

//si no esta autenticado, redirigimos al wayf
if( $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'] != "") {
        $_SESSION["user"] = $_SERVER['REMOTE_USER'];
}
else{
	$pageurl = "http://federacion21.us.es/protegido/ssh.php";
	$url = ($GLOBALS['shib_Https'] ? 'https' :  'http') .'://' .
		$GLOBALS['shib_AssertionConsumerServiceURL'] . "/WAYF/" . $GLOBALS['shib_WAYF'] .
		'?target=' . $pageurl;
	header("Location: ".$url);
}

$base_dn ='o=People,dc=us,dc=es';

function modify($ds, $uid, $pubkey){
    global $base_dn;
    // preparar los datos
    $timeout = 5 * 60; //5 minutos
    $hoy = getdate();
    $timeout = $hoy[0]+$timeout;
    $dn = "uid=". $uid .",". $base_dn;
    $info2["sshPublicKey"] = $pubkey;
    $info["schacUserStatus"][0] = "schac:userStatus:us.es:timeout:" . $timeout;

    // anadir la informacion al directorio
    $r=ldap_modify($ds, $dn, $info);
    if ($r){
	    $r = @ldap_mod_add($ds, $dn, $info2);
	    if (!$r)
		    echo "<p>Ya est&aacute; esta clave, actualizado el timeout</p>";
    }
    return $r;
}

//TODO el add no funciona, Object class violation in /var/www/protegido/ssh.php on line 59
function add($ds, $uid, $sn, $cn, $pubkey){
    global $base_dn;
    // preparar los datos
    $timeout = 5 * 60; //5 minutos
    $hoy = getdate();
    $timeout = $hoy[0]+$timeout;
    $dn = "uid=". $uid .",". $base_dn;
    $info["objectClass"][0] = "person";
    $info["objectClass"][1] = "ldapPublicKey";
    $info["objectClass"][2] = "schacUserEntitlements";
    $info["uid"] = $uid;
    $info["sn"] = $sn;
    $info["cn"] = $cn;

    $info["sshPublicKey"] = $pubkey;
    $info["schacUserStatus"] = "schac:userStatus:us.es:timeout:" . $timeout;

    // anadir la informacion al directorio
    $r=ldap_add($ds, $dn, $info);
    return $r;
}

?>

<html>
<head>
<title>SSH por federacion</title>
</head>
<body>

<?php

$servidor_ldap = "goonie.us.es";
$puerto_ldap = 389;

$ds=ldap_connect($servidor_ldap, $puerto_ldap) or die("No ha sido posible conectarse al servidor ".$servidor_ldap."");
ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

function display_form($color) {
	?>
		<form action="" method="POST">
		<textarea name="key" cols="60" rows="5"  style="border: 1px solid black;">texto</textarea><br/>
		<input type="submit" style="border: 1px solid black;"></input>
		<input type="submit" style="border: 1px solid black;" value="Enviar"></input>
		</form>
		<?php
}
$cad = $_SERVER["REMOTE_USER"];

//$cad = $_SERVER["REMOTE_USER"];
//partiendo el nombre, para crear danigm-us a partir de danigm@us.es
$cad = $_SESSION['user'];
$nado = explode('@', $cad);
$name = $nado[0];
$dominio = substr($nado[1], 0, stripos($nado[1], '.'));
$name = $name.'-'.$dominio;
echo "<p>Bienvenido ".$name."</p>";



// Check if userCertificate attribute is set in SAML response
if (isset($_SERVER["HTTP_USERCERTIFICATE"])) {
    $certificate = $_SERVER["HTTP_USERCERTIFICATE"];
    echo "<p>Se ha recibido la clave publica asociada a su cuenta:</p>";
    $certificate = base64_decode($certificate);
    echo $certificate;
} else {

    // Public key was not received from IdP. First check if user is posting its public key
    if (isset($_POST['key'])) {
        $certificate = $_POST['key'];
    } else {

        // Display form where public key can be submitted
        echo "<p>No se ha recibido clave publica asosciada a esta cuenta, puedes asignar una temporal en el siguiente formulario:</p>";
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
	    //$q = sprintf("insert into pubkey (uid, init, timeout, pubkey) values ('%s', NOW(), '5', '%s')",
		//mysql_real_escape_string($name), mysql_real_escape_string($certificate));
	    //$response = mysql_query($q);
        //$response = exec($command, $output, $return);
	$uid = $name;
	$pubkey = $certificate;
        $bn = 'cn=admin,dc=us,dc=es';
        $pw = 'fedssh';
        ldap_bind($ds, $bn, $pw) or die("No ha sido posible enlazar con el servidor ".$servidor_ldap." con el usuario ".$bn."");
        $filter = '(uid='.$uid.')';
        $resource = ldap_search($ds, $base_dn, $filter);
        $info = ldap_get_entries($ds, $resource);

        if ($info["count"] > 0){
            //solo hay que anadir un pubkey, si es distinto
            //y modificar el timeout.
            $response = modify($ds, $uid, $pubkey);
        }else {
            //nueva entrada
            $cn = $_SERVER["HTTP_SHIB_PERSON_COMMONNAME"];
            $sn = $_SERVER["HTTP_SHIB_PERSON_SURNAME"];
            $response = add($ds, $uid, $sn, $cn, $pubkey);
        }

        ldap_unbind($ds);


        // Check if command executed successfully
        if($response) {
            echo "<p>DEBUG: OK:</p>";
            echo "<p>Ahora puedes entrar por ssh en los servidores de la federaci&oacute;n, utilizando como nombre de usuario:". $name ."</p>";
        } else {
            echo "<p>DEBUG: NOK</p>";
        }
    } else {

        // Certificate syntax is not valid
        echo "<p><font color=\"#FF0000\">No ha introducido un certificado valido.</font></p>";
        display_form("#FF0000");
    }
}



?>

</body>
</html>
