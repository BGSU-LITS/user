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
                    isset($context['return']) &&
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

        $post = $this->request->getParsedBody();

        if (!\is_array($post)) {
            throw new HttpInternalServerErrorException($this->request);
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

        $this->redirect($url);

        $return = null;

        if (
            isset($post['return']) &&
            \is_string($post['return']) &&
            $post['return'] !== ''
        ) {
            $return = $post['return'];
            $this->redirect($url . '?return=' . \urlencode($return));
        }

        if ($this->auth->isLoggedIn()) {
            return $this->response;
        }

        if (
            !isset($post['username']) ||
            !\is_string($post['username']) ||
            $post['username'] === ''
        ) {
            $this->message('failure', 'A username must be specified.');

            return $this->response;
        }

        if (
            !isset($post['password']) ||
            !\is_string($post['password']) ||
            $post['password'] === ''
        ) {
            $this->message('failure', 'A password must be specified.');

            return $this->response;
        }

        try {
            $this->auth->login($post['username'], $post['password']);
            $this->redirect($return);
        } catch (LoginException $exception) {
            if ($exception->getCode() === LoginException::CANCELLED) {
                $this->message('failure', $exception->getMessage() . '.');

                return $this->response;
            }

            $this->message('failure', 'The username or password is invalid.');
        }

        return $this->response;
    }
}
