<?php
/**
 * TEST SCRIPT: RD WATCH AUTH API (WHITE-BOX)
 * -----------------------------------------
 * Este script simula peticiones HTTP a la API interna para validar comportamientos.
 */

require_once 'src/backend/config.php';

function test_signup($email, $pass, $nombre, $telefono) {
    echo "--- Test Signup: $email ---\n";
    $url = "http://192.168.1.52/rd_watch_V2/src/backend/api/signup.php";
    
    $data = [
        "nombre" => $nombre,
        "email" => $email,
        "telefono" => $telefono,
        "password" => $pass
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    echo "Respuesta: $result\n\n";
    return json_decode($result, true);
}

function test_login($email, $pass) {
    echo "--- Test Login: $email ---\n";
    $url = "http://192.168.1.52/rd_watch_V2/src/backend/api/login.php";
    
    $data = [
        "email" => $email,
        "password" => $pass
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    echo "Respuesta: $result\n\n";
    return json_decode($result, true);
}

// ESCENARIO 1: Registro Exitoso
$test_email = "tester_" . time() . "@qa.com";
test_signup($test_email, "SecurePass123!", "QA Tester Bot", "3001234567");

// ESCENARIO 2: Email Duplicado (Debe fallar)
test_signup($test_email, "AnotherPass", "Duplicate Man", "3111234567");

// ESCENARIO 3: Login Exitoso
test_login($test_email, "SecurePass123!");

// ESCENARIO 4: Login Fallido (Pass incorrecta)
test_login($test_email, "WrongPass");

// ESCENARIO 5: Login Fallido (User no existe)
test_login("non_existent@qa.com", "random");
