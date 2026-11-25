<?php
/** Delete (archive) button for $product; Admin-only pages include it. */
/** @var App\Models\Product $product */
/** @var string|null $csrfToken */
?>
<form method="post" action="/products/<?= e($product->id) ?>" style="display: inline; margin: 0"
      data-confirm="Delete <?= e($product->name) ?>? It will be removed from sale; its Purchases are kept.">
    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit" class="secondary outline" style="width: auto; margin: 0; padding: .25rem .75rem">Delete</button>
</form>
