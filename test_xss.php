<?php
require_once 'src/backend/utils/security_utils.php';

$testCase = "<script>alert('xss')</script> & \"Quotes\"";
$expected = "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt; &amp; &quot;Quotes&quot;";

$sanitized = sanitizeHtml($testCase);

echo "Original: $testCase\n";
echo "Sanitizada: $sanitized\n";

if ($sanitized === $expected) {
    echo "\n[RESULTADO] PRUEBA XSS EXITOSA: Los caracteres peligrosos han sido escapados.\n";
} else {
    echo "\n[RESULTADO] PRUEBA XSS FALLIDA: Los caracteres no coinciden.\n";
}
