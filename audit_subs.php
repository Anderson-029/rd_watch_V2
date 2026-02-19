<?php
require_once 'src/backend/config.php';

echo "AUDITORÍA DE INTEGRIDAD: SUB-CATEGORÍAS\n";
echo "--------------------------------------\n";

try {
    // 1. Listar subcategorías y conteo de productos reales (Optimización: JOIN en lugar de subconsulta)
    $sql = "SELECT s.id_categoria, s.id_subcategoria, s.nom_subcategoria, 
                   COUNT(p.id_producto) as total_productos
            FROM tab_Subcategorias s
            LEFT JOIN tab_Productos p ON s.id_categoria = p.id_categoria AND s.id_subcategoria = p.id_subcategoria
            GROUP BY s.id_categoria, s.id_subcategoria, s.nom_subcategoria
            ORDER BY s.id_categoria, s.id_subcategoria";

    $stmt = $pdo->query($sql);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Subcategorías registradas:\n";
    foreach ($subs as $s) {
        echo "CAT[{$s['id_categoria']}] SUB[{$s['id_subcategoria']}] NAME[{$s['nom_subcategoria']}] PRODS: {$s['total_productos']}\n";
    }

    echo "\nVerificando productos con categorías inválidas...\n";
    $sqlOrphans = "SELECT id_producto, nom_producto, id_categoria, id_subcategoria FROM tab_Productos 
                   WHERE (id_categoria, id_subcategoria) NOT IN (SELECT id_categoria, id_subcategoria FROM tab_Subcategorias)";
    $stmtOrphans = $pdo->query($sqlOrphans);
    $orphans = $stmtOrphans->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orphans)) {
        echo "No hay productos huérfanos.\n";
    }
    else {
        foreach ($orphans as $o) {
            echo "ORPHAN: ID[{$o['id_producto']}] NAME[{$o['nom_producto']}] LINKS TO INVALID CAT[{$o['id_categoria']}] SUB[{$o['id_subcategoria']}]\n";
        }
    }

}
catch (Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage() . "\n";
}