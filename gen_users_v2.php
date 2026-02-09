<?php
$output = "INSERT INTO tab_Usuarios (id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, contra, rol, direccion_principal, activo, fec_insert, usr_insert) VALUES\n";

for ($i = 2; $i <= 30; $i++) {
    $name = "Cliente " . $i;
    $email = "cliente" . $i . "@email.com";
    $phone = 3001234500 + $i;
    $pass = "Cliente123!" . $i;
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $addr = "Dirección Cliente " . $i . ", Bogotá";

    $output .= "($i, '$name', '$email', $phone, '$hash', 'cliente', '$addr', TRUE, NOW(), 'system')";
    $output .= ($i < 30) ? ",\n" : ";\n";
}

echo $output;
?>