<?php

declare(strict_types=1);

use Lits\Action\IndexAction;
use Lits\Action\LoginAction;
use Lits\Action\LogoutAction;
use Lits\Action\PasswordAction;
use Lits\Framework;

return function (Framework $framework): void {
    $framework->app()
        ->get('/', IndexAction::class)
        ->setName('index')
        ->setArgument('auth', 'true');

    $framework->app()
        ->get('/login', LoginAction::class)
        ->setName('login');

    $framework->app()
        ->post('/login', [LoginAction::class, 'post']);

    $framework->app()
        ->map(['GET', 'POST'], '/logout', LogoutAction::class)
        ->setName('logout');

    $framework->app()
        ->get('/password', PasswordAction::class)
        ->setName('password');

    $framework->app()
        ->post('/password', [PasswordAction::class, 'post']);
};
