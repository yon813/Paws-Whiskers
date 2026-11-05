
<?php
require 'database.php';

header('Content-Type: application/json');

try {
    // Fetch all products
    $prods = $pdo->query(
        'SELECT * FROM products'
    )->fetchAll();

    // Fetch product options
    $opts = [];

    foreach (
        $pdo->query(
            'SELECT product_id, `value`
             FROM product_options
             ORDER BY sort_order, id'
        ) as $r
    ) {
        $opts[$r['product_id']][] = $r['value'];
    }

    // Fetch product specifications
    $specs = [];

    foreach (
        $pdo->query(
            'SELECT product_id, spec_name, spec_value
             FROM product_specs
             ORDER BY id'
        ) as $r
    ) {
        $specs[$r['product_id']][] = [
            $r['spec_name'],
            $r['spec_value']
        ];
    }

    // Prepare product data
    $out = [];

    foreach ($prods as $p) {
        $out[] = [
            'id'  => (int)$p['id'],
            'n'   => $p['name'],
            'p'   => (float)$p['price'],
            'pet' => $p['pet'],
            'g'   => $p['category'],
            'r'   => (float)$p['rating'],
            'b'   => (int)$p['is_bestseller'],
            'w'   => (int)$p['new_rank'],
            'ol'  => $p['option_label'] ?: 'Option',
            'd'   => $p['description'],

            'o' => $opts[$p['id']] ?? ['Standard'],
            's' => $specs[$p['id']] ?? [],

            // Product image path
            'image' => 'img/product-' . (int)$p['id'] . '.jpg'
        ];
    }

    echo json_encode($out);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Server error'
    ]);
}
