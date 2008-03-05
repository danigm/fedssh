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
        $pageurl = "http://federacion21.us.es/protegido/ssh.php";
        $url = ($GLOBALS['shib_Https'] ? 'https' :  'http') .'://' .
                $GLOBALS['shib_AssertionConsumerServiceURL'] . "/WAYF/" . $GLOBALS['shib_WAYF'] .
                '?target=' . $pageurl;
        header("Location: ".$url);
}
?>

<html>
    <head>
        <title>SSH por federacion</title>
    </head>
    <body>

        <h2>Bienvenido <?php echo htmlentities(get_remote_user()); ?></h2>
        <?php
            $var = anadir_usuario();
            $name = htmlentities(get_remote_user());
            $certificate = get_certificate();
            echo '<p>Usando el certificado: <br/>';
            $cert = str_split($certificate, 50);
            foreach ($cert as $line){
                echo htmlentities($line)."<br/>";
            }
            echo '</p>';
            if ($var == -1)
                echo "<p><font color=\"#FF0000\">No ha introducido un certificado valido.</font></p>";
            else if($var == -2)
                echo "<p><font color=\"#FF0000\">La operaci&oacute;n no se ha completado.</font></p>";
            else
                echo "<p>Ahora puedes entrar por ssh en los servidores de la federaci&oacute;n, utilizando como nombre de usuario: <strong>". $name ."</strong></p>";
                
        ?>
        <p>
            Si no est&aacute;s en tu puesto de trabajo, o no se encuentra tu clave, puedes proporcionar
            una manualmente, introducciendola en el siguiente campo. Mira en tu directorio $HOME/.ssh/id_rsa.pub
        </p>
        <p>
            Para utilizar el proceso autom&aacute;tico, ponte en contacto con tu proveedor de identidad.
            <?php display_form() ?>
        </p>
    </body>
</html>
