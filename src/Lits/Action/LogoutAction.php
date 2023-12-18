<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Config\UserConfig;
use SimpleSAML\Auth\Simple;
use Slim\Exception\HttpInternalServerErrorException;

final class LogoutAction extends AuthAction
{
    public const MESSAGE_SUCCESS =
        'You have been logged out.';
    public const MESSAGE_FAILURE =
        'Could not logout from all sessions, please close your browser.';

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        $this->auth->logout();

        \assert($this->settings['user'] instanceof UserConfig);
        $ssp = $this->settings['user']->ssp();
        $url = $this->url();

        if ($ssp instanceof Simple && $ssp->isAuthenticated()) {
            $ssp->logout([
                'ReturnTo' => $url,
                'ReturnStateParam' => 'state',
                'ReturnStateStage' => __NAMESPACE__,
            ]);
        }

        $this->message('warning', self::MESSAGE_SUCCESS);
        $this->redirect($url);
    }

    /** @throws HttpInternalServerErrorException */
    private function url(): string
    {
        try {
            $url = $this->routeCollector->getRouteParser()->urlFor('login');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not determine URL for redirect.',
                $exception,
            );
        }

        $return = $this->request->getQueryParam('return', '');
        \assert(\is_string($return));

        if ($return !== '') {
            $url .= '?return=' . \urlencode($return);
        }

        return $url;
    }
}
