<?php

declare(strict_types=1);

namespace Lits\Action;

use Slim\Exception\HttpInternalServerErrorException;

final class LogoutAction extends AuthAction
{
    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        $this->auth->logout();
        $this->message('warning', 'You have been logged out.');

        $return = $this->request->getQueryParam('return');

        if (!\is_string($return) || $return === '') {
            $this->redirect();

            return;
        }

        try {
            $url = $this->routeCollector->getRouteParser()->urlFor('login');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not determine URL for redirect.',
                $exception
            );
        }

        $this->redirect($url . '?return=' . \urlencode($return));
    }
}
