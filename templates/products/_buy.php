<?php
/** Buy link for $product; Sold Out Products get a disabled button instead. Guests are sent to sign in by the route. */
/** @var App\Models\Product $product */
?>
<?php if ($product->isSoldOut()): ?>
    <button type="button" disabled>Sold out</button>
<?php else: ?>
    <a href="/products/<?= e($product->id) ?>/purchase" role="button">Buy</a>
<?php endif; ?>
