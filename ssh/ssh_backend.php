<?php
session_start();

$base_dn ='o=People,dc=us,dc=es';
$servidor_ldap = "goonie.us.es";
$puerto_ldap = 389;
$bn = 'cn=admin,dc=us,dc=es';
$pw = 'xxxx';
$minutes_timeout = 30;
$shib_header = "HTTP_USERCERTIFICATE";
$rsa_server_key_attr = 'sshpublickey';
$rsa_server_timeout = 'schacuserstatus';


function modify($ds, $uid, $pubkey){
        global $base_dn;
        global $minutes_timeout;
	global $rsa_server_key_attr;
	global $rsa_server_timeout;
        // preparar los datos
        $timeout = $minutes_timeout * 60; //5 minutos
        $hoy = getdate();
        $timeout = $hoy[0]+$timeout;
        $dn = "uid=". $uid .",". $base_dn;
        $info[$rsa_server_key_attr][0] = $pubkey;
        $info[$rsa_server_timeout][0] = "schac:userStatus:us.es:timeout:" . $timeout;

        // anadir la informacion al directorio
        $r=ldap_modify($ds, $dn, $info);
	$h = getdate($timeout);
	echo '<p class="info">'._('Esta sesion de ssh es valida hasta: ').$h["hours"].':'.$h["minutes"].':'.$h["seconds"].' - '.$h["mday"].' '.$h["month"].' '.$h["year"].'</p>';

        return $r;
}

function add($ds, $uid, $sn, $cn, $pubkey){
        global $base_dn;
        global $minutes_timeout;
	global $rsa_server_key_attr;
	global $rsa_server_timeout;
        // preparar los datos
        $timeout = $minutes_timeout * 60; //5 minutos
        $hoy = getdate();
        $timeout = $hoy[0]+$timeout;
        $dn = "uid=". $uid .",". $base_dn;
        $info["objectClass"][0] = "person";
        $info["objectClass"][1] = "ldapPublicKey";
        $info["objectClass"][2] = "schacUserEntitlements";
        $info["uid"] = $uid;
	if($sn == '')
		$sn = $uid;
	if($cn == '')
		$cn = $uid;
        $info["sn"] = $sn;
        $info["cn"] = $cn;

        $info[$rsa_server_key_attr] = $pubkey;
        $info[$rsa_server_timeout] = "schac:userStatus:us.es:timeout:" . $timeout;

        // anadir la informacion al directorio
        $r=ldap_add($ds, $dn, $info);
	$h = getdate($timeout);
	echo '<p class="info">'._('Esta sesion de ssh es valida hasta: ').$h["hours"].':'.$h["minutes"].':'.$h["seconds"].' - '.$h["mday"].' '.$h["month"].' '.$h["year"].'</p>';
        return $r;
}

function delete($uid) {
        global $base_dn;
        global $servidor_ldap;
        global $puerto_ldap;
        global $bn, $pw;

        $ds=ldap_connect($servidor_ldap, $puerto_ldap) or die("No ha sido posible conectarse al servidor ".$servidor_ldap."");
        //Version del protocolo que vamos a usar
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        //Bind como usuario, vamos a buscar, para ver si ya esta
        ldap_bind($ds, $bn, $pw) or die("No ha sido posible enlazar con el servidor ".$servidor_ldap." con el usuario ".$bn."");

        $dn = "uid=". $uid .",". $base_dn;
	echo $dn;
	$r = ldap_delete($ds, $uid);
	return $r;
}

function display_form() {
        echo('
                        <form action="" method="post">
                        <textarea name="key" cols="60" rows="5"></textarea><br/>
                        <input type="submit" value="'._('Enviar').'"></input>
                        </form>
                        ');
}

function get_remote_user(){
        $cad = $_SERVER["REMOTE_USER"];

        //$cad = $_SERVER["REMOTE_USER"];
        //partiendo el nombre, para crear danigm-us a partir de danigm@us.es
        $cad = $_SESSION['user'];
        $nado = explode('@', $cad);
        $name = $nado[0];
        $dominio = substr($nado[1], 0, stripos($nado[1], '.'));
        $name = $name.'-'.$dominio;
        return $name;
}

function get_certificate(){
	global $shib_header;
        $certificate = "";
 	if (isset($_POST['key'])) {
                // Public key was not received from IdP. First check if user is posting its public key
		$certificate = $_POST['key'];
        }
        // Check if userCertificate attribute is set in SAML response
        else if (isset($_SERVER[$shib_header])) {
                $certificate = $_SERVER[$shib_header];
                $certificate = base64_decode($certificate);
        }
        // Trim certificate string
        $certificate = trim($certificate);
        $certificate = str_replace("\r","",$certificate);
        $certificate = str_replace("\n","",$certificate);

        return $certificate;
}

function check_certificate($certificate){
        // Check if certificate syntax is correct
        if (substr($certificate, 0, 7) == "ssh-rsa" || substr($certificate, 0, 7) == "ssh-dss")
                return true;
        else
                return false;
}

/**
 * Esta funcion mira en el servidor de claves, si este usuario esta ya
 * si esta modifica la clave, y el timeout
 * si no esta lo anade
**/
function doit($uid, $pubkey){
        global $base_dn;
        global $servidor_ldap;
        global $puerto_ldap;
        global $bn, $pw;

        //Conectando con el ldap
        $ds=ldap_connect($servidor_ldap, $puerto_ldap) or die("No ha sido posible conectarse al servidor ".$servidor_ldap."");
        //Version del protocolo que vamos a usar
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        //Bind como usuario, vamos a buscar, para ver si ya esta
        ldap_bind($ds, $bn, $pw) or die("No ha sido posible enlazar con el servidor ".$servidor_ldap." con el usuario ".$bn."");

        //se guardan por uid, por lo que filtramos por este campo
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
        return $response;
}

function get_attr($uid, $attr){
        global $base_dn;
        global $servidor_ldap;
        global $puerto_ldap;
        global $bn, $pw;

        //Conectando con el ldap
        $ds=ldap_connect($servidor_ldap, $puerto_ldap) or die("No ha sido posible conectarse al servidor ".$servidor_ldap."");
        //Version del protocolo que vamos a usar
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        //Bind como usuario, vamos a buscar, para ver si ya esta
        ldap_bind($ds, $bn, $pw) or die("No ha sido posible enlazar con el servidor ".$servidor_ldap." con el usuario ".$bn."");

        //se guardan por uid, por lo que filtramos por este campo
        $filter = '(uid='.$uid.')';
        $resource = ldap_search($ds, $base_dn, $filter);
        $info = ldap_get_entries($ds, $resource);
	ldap_unbind($ds);
	if ($info["count"] == 0){
		return null;
	}
	else{
		return $info[0][$attr][0];
	}

}

function get_certificate_used($uid){
	global $rsa_server_key_attr;
    $timestamp = get_attr($uid, $rsa_server_timeout);
    $timestamp = split(":", $timestamp);
    $timestamp = $timestamp[count($timestamp)-1];
    $now = getdate();
    if ($now > $timestamp)
	return get_attr($uid, $rsa_server_key_attr);
    else
	return "";
}

/**
 * Codigos de error:
 * -1 certificado no valido
 * -2 No se ha podido completar la operacion
 * -3 El certificado esta en blanco, no lo ha establecido el idp
 *  1 Todo bien 
**/
function anadir_usuario(){
        // If $certificate is set, public key has been successfully received
        $certificate = get_certificate();
        if ($certificate != "") {
                // Check if certificate syntax is correct
                if(check_certificate($certificate)) {
                        $name = get_remote_user();
                        $response = doit($name, $certificate);
                        // Check if command executed successfully
                        if($response)
                                return 1;
                        else 
                                return -2;
                } else {
                        return -1;
                }
        }
        else return -3;
}


?>
