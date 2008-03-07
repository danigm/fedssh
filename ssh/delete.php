<?php 

require("ssh_backend.php");

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
        $pageurl = "http://federacion21.us.es/protegido/ssh/ssh.php";
        $url = ($GLOBALS['shib_Https'] ? 'https' :  'http') .'://' .
                $GLOBALS['shib_AssertionConsumerServiceURL'] . "/WAYF/" . $GLOBALS['shib_WAYF'] .
                '?target=' . $pageurl;
        header("Location: ".$url);
}
?>

<html>
    <head>
        <title>SSH por federacion</title>
	<link rel="stylesheet" type="text/css" media="screen" href="style.css" />
    </head>
    <body>

	<div id="main">
        <h3>Cerrar sessi&oacute;n para <?php echo htmlentities(get_remote_user()); ?></h3>
        <?php
            $name = htmlentities(get_remote_user());
	    $r = delete($name);
	    if ($r) {
		echo '<p>Se ha cerrado la sesi&oacute;n ssh para el usuario '.$name.'. A partir de ahora ya no se pueden realizar m&aacute;s conexiones a no ser que se vuelva a autenticar.</p>
	<p>
		Sin embargo, las sesiones ya abiertas no se cerrar&aacute;n.
	</p>';
	    }
	    
	?>
	</div>
    </body>
</html>
