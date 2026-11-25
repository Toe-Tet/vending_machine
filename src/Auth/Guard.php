<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Response;
use App\Core\View;

/**
 * Access checks for controller actions. Each returns null when the visitor may
 * proceed, or the Response to send instead:
 *
 *     if ($denied = $this->guard->admin()) {
 *         return $denied;
 *     }
 */
final readonly class Guard
{
    public function __construct(private Auth $auth, private View $view)
    {
    }

    /** Any signed-in User or Admin; Guests are sent to sign in. */
    public function signedIn(): ?Response
    {
        return $this->auth->check() ? null : Response::redirect('/login');
    }

    /** Guests only (sign in, sign up); signed-in visitors go to the catalogue. */
    public function guest(): ?Response
    {
        return $this->auth->check() ? Response::redirect('/products') : null;
    }

    /** Admins only: Guests are sent to sign in, Users get 403. */
    public function admin(): ?Response
    {
        return $this->signedIn() ?? ($this->auth->isAdmin() ? null : $this->view->response('errors/403', ['title' => 'Forbidden'], 403));
    }
}
