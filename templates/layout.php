<?php
/** @var string $content */
/** @var string|null $title */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($title) ? $title . ' · Vending Machine' : 'Vending Machine') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.min.css">
</head>
<body>
<header class="container">
    <nav>
        <ul><li><a href="/products"><strong>Vending Machine</strong></a></li></ul>
        <ul><li><a href="/products">Products</a></li></ul>
    </nav>
</header>
<main class="container">
    <?= $content ?>
</main>
</body>
</html>
