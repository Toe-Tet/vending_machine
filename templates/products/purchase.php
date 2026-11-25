<?php
/**
 * Purchase view (spec §5). The quantity input's limits mirror the server rules;
 * /js/app.js previews the Purchase Total, and the server re-checks everything.
 */
/** @var App\Models\Product $product */
/** @var array<string, string> $old */
/** @var string|null $error */
/** @var string|null $csrfToken */

$soldOut = $product->isSoldOut();
$quantity = $old['quantity'] ?? '1';
?>
<p class="back-link"><a href="/products/<?= e($product->id) ?>">← Back to <?= e($product->name) ?></a></p>

<article class="product-card">
    <header>
        <h1>Buy <?= e($product->name) ?></h1>
        <?php if ($soldOut): ?>
            <span class="badge badge-out">Sold out</span>
        <?php endif; ?>
    </header>

    <dl class="facts">
        <div>
            <dt>Unit Price (USD)</dt>
            <dd class="price"><?= e($product->displayPrice()) ?></dd>
        </div>
        <div>
            <dt>Quantity Available</dt>
            <dd><?= e($product->quantityAvailable) ?></dd>
        </div>
    </dl>

    <form method="post" action="/products/<?= e($product->id) ?>/purchase" class="purchase-form"
          data-unit-price="<?= e($product->displayPrice()) ?>">
        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" value="<?= e($quantity) ?>"
               min="1" max="<?= e(max(1, $product->quantityAvailable)) ?>" step="1" inputmode="numeric" required
               <?= $soldOut ? 'disabled' : '' ?>
               aria-describedby="quantity-error"<?= $error !== null ? ' aria-invalid="true"' : '' ?>>
        <small id="quantity-error" data-error-for="quantity"><?= e($error ?? '') ?></small>

        <p class="total">Purchase Total (USD) <strong data-purchase-total>—</strong></p>

        <button type="submit" <?= $soldOut ? 'disabled' : '' ?>><?= $soldOut ? 'Sold out' : 'Buy' ?></button>
    </form>
</article>
