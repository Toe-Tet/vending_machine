<?php
/** @var string $content */
/** @var string|null $title */
/** @var App\Auth\Auth|null $auth */
/** @var string|null $csrfToken */
$auth ??= null;
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
        <ul>
            <li><a href="/products">Products</a></li>
            <?php if ($auth?->check()): ?>
                <li><?= e($auth->name()) ?><?= $auth->isAdmin() ? ' <small>(Admin)</small>' : '' ?></li>
                <li>
                    <form method="post" action="/logout" style="margin: 0">
                        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                        <button type="submit" class="secondary outline" style="margin: 0">Sign out</button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="/login">Sign in</a></li>
                <li><a href="/register">Sign up</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main class="container">
    <?= $content ?>
</main>
</body>
</html>
