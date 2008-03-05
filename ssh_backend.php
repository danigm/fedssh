<?php
session_start();
$base_dn ='o=People,dc=us,dc=es';
$servidor_ldap = "goonie.us.es";
$puerto_ldap = 389;
$bn = 'cn=admin,dc=us,dc=es';
$pw = 'fedssh';



function modify($ds, $uid, $pubkey){
        global $base_dn;
        // preparar los datos
        $timeout = 5 * 60; //5 minutos
        $hoy = getdate();
        $timeout = $hoy[0]+$timeout;
        $dn = "uid=". $uid .",". $base_dn;
        $info["sshPublicKey"][0] = $pubkey;
        $info["schacUserStatus"][0] = "schac:userStatus:us.es:timeout:" . $timeout;

        // anadir la informacion al directorio
        $r=ldap_modify($ds, $dn, $info);
	/**
        if ($r){
                $r = @ldap_mod_add($ds, $dn, $info2);
                if (!$r)
                        echo "<p>Ya est&aacute; esta clave, actualizado el timeout</p>";
                        return true;
        }
	**/
        return $r;
}

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
//TODO implementar el delete

function display_form() {
        echo('
                        <form action="" method="POST">
                        <textarea name="key" cols="60" rows="5"  style="border: 1px solid black;">texto</textarea><br/>
                        <input type="submit" style="border: 1px solid black;"></input>
                        <input type="submit" style="border: 1px solid black;" value="Enviar"></input>
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
        $certificate = "";
 	if (isset($_POST['key'])) {
                // Public key was not received from IdP. First check if user is posting its public key
		$certificate = $_POST['key'];
        }
        // Check if userCertificate attribute is set in SAML response
        else if (isset($_SERVER["HTTP_USERCERTIFICATE"])) {
                $certificate = $_SERVER["HTTP_USERCERTIFICATE"];
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

/**
 * Codigos de error:
 * -1 certificado no valido
 * -2 No se ha podido completar la operacion
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
        else return -1;
}

?>
