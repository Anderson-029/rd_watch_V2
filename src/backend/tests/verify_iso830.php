<?php
/**
 * TEST SUITE: VERIFICACIÓN TÉCNICA ISO 830 (PORT 8000)
 * ---------------------------------------------------------
 require_once '../utils/security_utils.php';
 requireRole('admin');
 function testApi($url, $method, $payload = null)
 {
 $ch = curl_init("http://localhost:8000/src/backend/api/" . $url);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
 if ($payload) {
 if (is_array($payload)) {
 curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
 curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
 } else {
 curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
 }
 }
 $response = curl_exec($ch);
 $info = curl_getinfo($ch);
 curl_close($ch);
 return [
 'code' => $info['http_code'],
 'body' => json_decode($response, true),
 'headers' => $info,
 'raw_body' => $response
 ];
 }
 echo "=== INICIANDO AUDITORÍA TÉCNICA ISO 830 ===\n\n";
 // TEST 1: LOGIN CON DATOS MAL FORMADOS
 echo "[1] Probando Login (Email mal formado): ";
 $res = testApi('login.php', 'POST', ['email' => 'admin<script>@gmail.com', 'password' => '123456']);
 if ($res['code'] === 200 && isset($res['body']['ok']) && $res['body']['ok'] === false) {
 echo "PASSED (Interceptado/Sanitizado)\n";
 } else {
 echo "FAILED (Code: {$res['code']})\n";
 }
 // TEST 2: CONTACTO CON NOMBRE INVÁLIDO
 echo "[2] Probando Contacto (Nombre con números): ";
 $boundary = "---TestBoundary";
 $payload = "--$boundary\r\nContent-Disposition: form-data; name=\"nombre\"\r\n\r\nJuan123\r\n--$boundary\r\nContent-Disposition: form-data; name=\"email\"\r\n\r\ntest@test.com\r\n--$boundary\r\nContent-Disposition: form-data; name=\"servicio\"\r\n\r\nrepair\r\n--$boundary\r\nContent-Disposition: form-data; name=\"mensaje\"\r\n\r\nHola\r\n--$boundary--";
 $ch = curl_init("http://localhost:8000/src/backend/api/contacto.php");
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_POST, true);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
 curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: multipart/form-data; boundary=$boundary"]);
 $raw = curl_exec($ch);
 $res_body = json_decode($raw, true);
 if (isset($res_body['ok']) && $res_body['ok'] === false && strpos($res_body['msg'] ?? '', 'nombre') !== false) {
 echo "PASSED (Validación NameRegex activa)\n";
 } else {
 echo "FAILED (Res: ".substr($raw, 0, 100). ")\n";
 }
 // TEST 3: CARRITO CON SEGURIDAD DE SESIÓN
 echo "[3] Probando Carrito (Seguridad de Sesión): ";
 $res = testApi('carrito.php', 'POST', ['id_producto' => 1, 'cantidad' => 1]);
 if ($res['code'] === 401) {
 echo "PASSED (Seguridad de Sesión activa)\n";
 } else {
 echo "FAILED (Code: {$res['code']})\n";
 }
 // TEST 4: VERIFICACIÓN DE CABECERAS ANTI-CACHÉ
 echo "[4] Verificando Cabeceras Cache-Control: ";
 $ch = curl_init("http://localhost:8000/src/backend/api/login.php");
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_HEADER, true);
 $response = curl_exec($ch);
 if (strpos($response, 'Cache-Control: no-cache, no-store, must-revalidate') !== false) {
 echo "PASSED (Cabeceras detectadas)\n";
 } else {
 echo "FAILED (Headers not found)\n";
 }
 echo "\n=== AUDITORÍA FINALIZADA ===\n";