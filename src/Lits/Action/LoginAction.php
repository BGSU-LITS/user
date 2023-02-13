<?php

declare(strict_types=1);

namespace Lits\Action;

use Jasny\Auth\LoginException;
use Lits\Config\TemplateConfig;
use Lits\Config\UserConfig;
use Lits\Data\UserData;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Auth\State as SimpleState;
use SimpleSAML\Error\NoState as SimpleStateException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

use function Safe\sprintf;

final class LoginAction extends AuthDatabaseAction
{
    use PostValueTrait;

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        try {
            $context = [
                'return' => $this->request->getQueryParam('return'),
            ];

            $this->ssp();

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

    /** @throws HttpInternalServerErrorException */
    private function ssp(): void
    {
        \assert($this->settings['user'] instanceof UserConfig);
        $ssp = $this->settings['user']->ssp();

        if (\is_null($ssp)) {
            return;
        }

        $this->sspLogin(
            $ssp,
            $this->settings['user']->ssp_attribute,
            $this->settings['user']->ssp_format
        );

        /** @var string $state */
        $state = $this->request->getQueryParam('state', '');

        if ($state === '') {
            return;
        }

        try {
            $this->sspState($state);
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not check SimpleSAMLphp state',
                $exception
            );
        }
    }

    private function sspLogin(
        Simple $ssp,
        string $attribute,
        string $format
    ): void {
        $attributes = $ssp->getAttributes();

        if (
            !isset($attributes[$attribute]) ||
            !\is_array($attributes[$attribute])
        ) {
            return;
        }

        /** @var string $value */
        foreach ($attributes[$attribute] as $value) {
            $user = UserData::fromUsername(
                sprintf($format, $value),
                $this->settings,
                $this->database
            );

            if ($user instanceof UserData) {
                $this->auth->loginAs($user);
            }
        }
    }

    /** @throws \Exception */
    private function sspState(string $state): void
    {
        try {
            $logout = SimpleState::loadState($state, __NAMESPACE__);
        } catch (SimpleStateException $exception) {
            return;
        }

        if (
            !isset($logout['saml:sp:LogoutStatus']) ||
            !\is_array($logout['saml:sp:LogoutStatus'])
        ) {
            return;
        }

        $message = LogoutAction::MESSAGE_SUCCESS;
        $status = $logout['saml:sp:LogoutStatus'];

        if (
            !isset($status['Code']) ||
            $status['Code'] !== 'urn:oasis:names:tc:SAML:2.0:status:Success' ||
            isset($status['SubCode'])
        ) {
            $message = LogoutAction::MESSAGE_FAILURE;
        }

        $this->messages[] = ['level' => 'warning', 'message' => $message];
    }
}
