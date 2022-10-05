<?php

declare(strict_types=1);

namespace Lits\Action;

use Jasny\Auth\LoginException;
use Lits\Config\TemplateConfig;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class LoginAction extends AuthAction
{
    use PostValueTrait;

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        try {
            $context = [
                'return' => $this->request->getQueryParam('return'),
            ];

            if ($this->auth->isLoggedIn()) {
                $this->redirect();

                if (
                    \is_string($context['return']) &&
                    $context['return'] !== ''
                ) {
                    $this->redirect($context['return']);
                }

                return;
            }

            \assert($this->settings['template'] instanceof TemplateConfig);
            $this->settings['template']->site = null;
            $this->settings['template']->menu = null;

            $this->render($this->template(), $context);
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception
            );
        }
    }

    /**
     * @param array<string, string> $data
     * @throws HttpInternalServerErrorException
     */
    public function post(
        ServerRequest $request,
        Response $response,
        array $data
    ): Response {
        $this->setup($request, $response, $data);

        try {
            $url = $this->routeCollector->getRouteParser()->urlFor('login');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not determine URL for redirect.',
                $exception
            );
        }

        $this->redirect($url);

        $return = $this->postValue('return');

        if (!\is_null($return)) {
            $this->redirect($url . '?return=' . \urlencode($return));
        }

        if (!$this->auth->isLoggedIn()) {
            $this->postLogin($return);
        }

        return $this->response;
    }

    /** @throws HttpInternalServerErrorException */
    private function postLogin(?string $return): void
    {
        $username = $this->postValue('username');

        if (\is_null($username)) {
            $this->message('failure', 'A username must be specified.');

            return;
        }

        $password = $this->postValue('password');

        if (\is_null($password)) {
            $this->message('failure', 'A password must be specified.');

            return;
        }

        try {
            $this->auth->login($username, $password);
            $this->redirect($return);
        } catch (LoginException $exception) {
            if ($exception->getCode() === LoginException::CANCELLED) {
                $this->message('failure', $exception->getMessage() . '.');

                return;
            }

            $this->message('failure', 'The username or password is invalid.');
        }
    }
}
