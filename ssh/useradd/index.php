<?php 

$servers[0] = 'federacion21.us.es';
$desc[0] = "servidor1";
$servers[1] = 'federacion22.us.es';
$desc[1] = "servidor2";
$error = '';
$info = 'En esta p&aacute;gina podr&aacute;s solicitar la creaci&oacute;n
para el acceso ssh a los servidores';

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
        $pageurl = "http://federacion21.us.es/protegido/ssh/useradd/";
        $url = ($GLOBALS['shib_Https'] ? 'https' :  'http') .'://' .
                $GLOBALS['shib_AssertionConsumerServiceURL'] . "/WAYF/" . $GLOBALS['shib_WAYF'] .
                '?target=' . $pageurl;
        header("Location: ".$url);
}

function useradd($uid, $servidor){
        if ($servidor == '')
                $servidor = "federacion21.us.es";

	$val = system("sudo ssh root@".$servidor." useradd -m -s /bin/bash -p xx -d /home/".$uid." ".$uid, $retval);
	return $retval;
}

function check_user($uid, $servidor){
        if ($servidor == '')
                $servidor = "federacion21.us.es";

	exec("sudo ssh root@".$servidor." id ".$uid, $array, $retval);
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
?>

<html>
        <head>
                <title>SSH por federacion</title>
                <link rel="stylesheet" type="text/css" media="screen" href="style.css" />
		<script type="text/javascript" src="js/jquery-1.2.1.min.js"></script>
		<script type="text/javascript" src="js/useradd.js"></script>

        </head>
        <body>

                <div id="head"></div>
                <div id="main">
                        <h2> <?php echo _('Bienvenido ') . htmlentities(get_remote_user()); ?></h2>
                        <?php
                        if(isset($_GET['server'])){
                                $s = $servers[$_GET['server']];
                                $var = useradd(get_remote_user(), $s);
                                if ($var != 0 && $var != 9)
                                        $error = 'No ha sido posible crear la cuenta';
                                else
                                        $info = '<p>Se ha creado la cuenta correctamente en el servidor'.$s.'</p>';
                        }
                        //$var = useradd(get_remote_user(), 'federacion22.us.es');
                        else{
                                /*
                                Mostrar lista de servidores
                                disponibles, junto con enlace para crear cuenta.  Se crean por ssh, estando
                                este servidor autorizado para entrar como root en los demas.  Quizas es
                                mejor no hacerlo con usuario root, sino con un usuario que tenga permiso
                                solo para el useradd.
                                */
                                echo "<h3>Servidores ssh ofrecidos por la Universidad de Sevilla</h3>";
                                ?>
                                <table>
                                        <tr>
                                                <th>Nombre</th>
                                                <th>Descripci&oacute;n</th>
                                                <th></th>
                                        </tr>
                                <?php
                                for($i = 0; $i<count($servers); $i++){
                                        echo '<tr>';
                                        echo '<td>'.$servers[$i].'</td>';
                                        echo '<td>'.$desc[$i].'</td>';
					if (check_user(get_remote_user(), $servers[$i]))
						echo '<td>Ya tienes cuenta en este servidor</td>';
					else
						echo '<td><a href="?server='.$uid.'" class="solicitar" id="'.$i.'" >solicitar cuenta</a></td>';
                                        echo '</tr>';
                                }
                                echo "</table>";
                        }
                        ?>
                        <div class="info">
                                <p class="warning">
                                        <?php echo $error;?>
                                </p>
                                <?php echo $info ?>
                        </div>
                </div>
        </body>
</html>
