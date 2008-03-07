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
        <h2>Bienvenido <?php echo htmlentities(get_remote_user()); ?></h2>
        <?php
            $var = anadir_usuario();
            $name = htmlentities(get_remote_user());
            $certificate = get_certificate_used($name);
            echo '<p>Usando el certificado: <br/>';
            $cert = str_split($certificate, 50);
	    echo '<div class="certificate">';
            foreach ($cert as $line){
                echo htmlentities($line)."<br/>";
            }
	    echo '</div>';
            echo '</p>';
            if ($var == -1)
                echo '<p class="warning">No ha introducido un certificado valido.</p>';
            else if($var == -2)
                echo '<p class="warning">La operaci&oacute;n no se ha completado.</p>';

            else if($var == -3)
                echo '<p class="warning">No se ha facilitado un certificado, introduzcalo manualmente</p>';
            else
                echo '<p class="ok">Ahora puedes entrar por ssh en los servidores de la federaci&oacute;n, utilizando como nombre de usuario: <span class="user">'. $name .'</span></p>';
                
        ?>
	<div class="info">
        <p>
            Si no est&aacute;s en tu puesto de trabajo, o no se encuentra tu clave, puedes proporcionar
            una manualmente, introducciendola en el siguiente campo. Mira en tu directorio $HOME/.ssh/id_rsa.pub
        </p>
        <p>
            Para utilizar el proceso autom&aacute;tico, ponte en contacto con tu proveedor de identidad.
        </p>
	</div>
	<div class="form">
            <?php display_form() ?>
	</div>
	</div>
    </body>
</html>
