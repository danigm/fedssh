<?php

$servers[0] = 'federacion21.us.es';
$desc[0] = "servidor1";
$servers[1] = 'federacion22.us.es';
$desc[1] = "servidor2";
$error = '';

session_start();
//soporte para traducciones
$lang=$_GET['lang'];
if($lang=='en')
$language="en_US.utf8";
else
$language="es_ES.utf8";
putenv("LC_ALL=$language");
setlocale(LC_ALL, $language);
bindtextdomain("ssh", "./locale");
textdomain("ssh");

//Esta cosita sirve para poder cerrar una aplicacion, aunque
//las lazy sessions del shibboleth permitan el acceso
$GLOBALS['shib_Https'] = false;
$GLOBALS['shib_AssertionConsumerServiceURL'] = "/federacion21.us.es/Shibboleth.sso";
$GLOBALS['shib_WAYF'] = "federacion21.us.es";

//si no esta autenticado, redirigimos al wayf
if( $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'] != "") {
        $_SESSION["user"] = $_SERVER['REMOTE_USER'];
}
else{
        $pageurl = "http://federacion21.us.es/protegido/ssh/useradd.php";
        $url = ($GLOBALS['shib_Https'] ? 'https' :  'http') .'://' .
                $GLOBALS['shib_AssertionConsumerServiceURL'] . "/WAYF/" . $GLOBALS['shib_WAYF'] .
                '?target=' . $pageurl;
        header("Location: ".$url);
}

function useradd($uid, $servidor){
        if ($servidor == '')
                $servidor = "federacion21.us.es";

	$val = system("sudo ssh root@".$servidor." useradd -m -s /bin/bash -p xx -d /home/".$uid." ".$uid, $retval);
	return $retval == 0;
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

if(isset($_POST['server'])){
	sleep(2);
	$s = $servers[$_POST['server']];
	$var = useradd(get_remote_user(), $s);
	if (!$var)
		return '<error>No ha sido posible crear la cuenta</error>';
	else
		return '<ok></ok>';
}

?>
