<?php
/** @var array<string, string> $old */
/** @var string|null $error */
/** @var string|null $csrfToken */
?>
<article>
    <h1>Sign in</h1>

    <?php if ($error !== null): ?>
        <p role="alert"><ins><?= e($error) ?></ins></p>
    <?php endif; ?>

    <form method="post" action="/login" novalidate>
        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
               autocomplete="username" required <?= $error !== null ? 'aria-invalid="true"' : '' ?>>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required
               <?= $error !== null ? 'aria-invalid="true"' : '' ?>>

        <button type="submit">Sign in</button>
    </form>

    <p>New here? <a href="/register">Sign up</a></p>
</article>

<article>
    <header><strong>Demo accounts</strong></header>
    <table>
        <thead><tr><th scope="col">Role</th><th scope="col">Email</th><th scope="col">Password</th></tr></thead>
        <tbody>
        <tr><td>Admin</td><td><code>admin@example.com</code></td><td><code>admin1234</code></td></tr>
        <tr><td>User</td><td><code>user@example.com</code></td><td><code>user12345</code></td></tr>
        </tbody>
    </table>
</article>
