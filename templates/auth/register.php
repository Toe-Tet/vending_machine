<?php
/** @var array<string, string> $old */
/** @var array<string, string> $errors field => message */
/** @var string|null $csrfToken */

$invalid = static fn (string $field): string => isset($errors[$field])
    ? 'aria-invalid="true" aria-describedby="' . $field . '-error"'
    : '';
$error = static fn (string $field): string => isset($errors[$field])
    ? '<small id="' . $field . '-error">' . e($errors[$field]) . '</small>'
    : '';
?>
<article>
    <h1>Sign up</h1>

    <form method="post" action="/register" novalidate>
        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>"
               maxlength="100" autocomplete="name" required <?= $invalid('name') ?>>
        <?= $error('name') ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
               maxlength="255" autocomplete="email" required <?= $invalid('email') ?>>
        <?= $error('email') ?>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8"
               autocomplete="new-password" required <?= $invalid('password') ?>>
        <?= $error('password') ?>

        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8"
               autocomplete="new-password" required <?= $invalid('password_confirmation') ?>>
        <?= $error('password_confirmation') ?>

        <button type="submit">Sign up</button>
    </form>

    <p>Already signed up? <a href="/login">Sign in</a></p>
</article>
