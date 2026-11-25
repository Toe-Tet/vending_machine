<?php
/**
 * Create form when $product is null, else the edit form. The HTML5 attributes
 * mirror ProductValidator; /js/app.js shows the same messages inline
 * before submit, and the server re-checks everything.
 */
/** @var App\Models\Product|null $product */
/** @var array<string, string> $old */
/** @var array<string, string> $errors field => message */
/** @var string|null $csrfToken */

use App\Validation\ProductValidator;

$invalid = static fn (string $field): string => isset($errors[$field])
    ? 'aria-invalid="true" aria-describedby="' . $field . '-error"'
    : 'aria-describedby="' . $field . '-error"';
$error = static fn (string $field): string => '<small id="' . $field . '-error" data-error-for="' . $field . '">'
    . e($errors[$field] ?? '') . '</small>';
?>
<?php $cancelUrl = $product === null ? '/products' : '/products/' . e($product->id); ?>
<p class="back-link"><a href="<?= $cancelUrl ?>">← Back to <?= $product === null ? 'Products' : e($product->name) ?></a></p>

<article class="product-card">
    <header><h1><?= $product === null ? 'New Product' : 'Edit ' . e($product->name) ?></h1></header>

    <form method="post" action="<?= $product === null ? '/products' : '/products/' . e($product->id) ?>" class="product-form">
        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
        <?php if ($product !== null): ?>
            <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>"
               maxlength="<?= ProductValidator::NAME_MAX ?>" required <?= $invalid('name') ?>>
        <?= $error('name') ?>

        <label for="price">Unit Price (USD)</label>
        <input type="number" id="price" name="price" value="<?= e($old['price'] ?? '') ?>"
               min="0.001" max="<?= ProductValidator::PRICE_MAX ?>" step="0.001" inputmode="decimal" required <?= $invalid('price') ?>>
        <?= $error('price') ?>

        <label for="quantity_available">Quantity Available</label>
        <input type="number" id="quantity_available" name="quantity_available" value="<?= e($old['quantity_available'] ?? '') ?>"
               min="0" max="<?= ProductValidator::QUANTITY_MAX ?>" step="1" inputmode="numeric" required <?= $invalid('quantity_available') ?>>
        <?= $error('quantity_available') ?>

        <div class="form-actions">
            <button type="submit"><?= $product === null ? 'Create Product' : 'Save changes' ?></button>
            <a href="<?= $cancelUrl ?>" role="button" class="secondary outline">Cancel</a>
        </div>
    </form>
</article>
