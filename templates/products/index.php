<?php
/** @var list<App\Models\Product> $products */
?>
<h1>Products</h1>

<?php if ($products === []): ?>
    <p>No products yet.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th scope="col">Name</th>
            <th scope="col">Price (USD)</th>
            <th scope="col">Quantity Available</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?= e($product->name) ?></td>
                <td><?= e($product->price) ?></td>
                <td><?= $product->isSoldOut() ? 'Sold out' : e($product->quantityAvailable) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
