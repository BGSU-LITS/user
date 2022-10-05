<?php

declare(strict_types=1);

namespace Lits\Action;

use Jasny\Auth\Confirmation\InvalidTokenException;
use Lits\Config\TemplateConfig;
use Lits\Data\TokenData;
use Lits\Data\UserData;
use Lits\Exception\DuplicateInsertException;
use Lits\Exception\FailedSendingException;
use Lits\Exception\InvalidConfigException;
use Lits\Exception\InvalidDataException;
use Lits\Mail;
use Lits\Service\AuthActionService;
use Safe\DateTimeImmutable;
use Safe\Exceptions\PasswordException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class PasswordAction extends AuthAction
{
    use PostValueTrait;

    private Mail $mail;

    public function __construct(AuthActionService $service, Mail $mail)
    {
        parent::__construct($service);

        $this->mail = $mail;
    }

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        $context = [
            'token' => $this->request->getQueryParam('token'),
        ];

        if (\is_string($context['token'])) {
            $user = $this->confirmToken($context['token']);

            if (\is_null($user)) {
                return;
            }
        }

        if ($this->auth->isLoggedOut()) {
            \assert($this->settings['template'] instanceof TemplateConfig);
            $this->settings['template']->site = null;
            $this->settings['template']->menu = null;
        }

        try {
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

        $token = $this->postValue('token');

        if (!\is_null($token)) {
            $this->postUpdate($token);

            return $this->response;
        }

        $this->redirectToken();

        $username = $this->postValue('username');

        if (
            \is_null($username) ||
            \filter_var($username, \FILTER_VALIDATE_EMAIL) === false
        ) {
            $this->message(
                'failure',
                'You must provide a valid email address.'
            );

            return $this->response;
        }

        try {
            $user = UserData::fromUsername(
                $username,
                $this->settings,
                $this->database
            );
        } catch (InvalidConfigException | InvalidDataException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not retrieve user',
                $exception
            );
        }

        if ($user instanceof UserData) {
            $this->sendToken($user);
        }

        $this->message(
            'success',
            'Check your email for instructions on how to change your password.'
        );

        return $this->response;
    }

    /** @throws HttpInternalServerErrorException*/
    private function postUpdate(string $token): void
    {
        $user = $this->confirmToken($token);

        if (\is_null($user)) {
            return;
        }

        $this->redirectToken($token);

        $password = $this->postValue('password');

        if (\is_null($password)) {
            $this->message(
                'failure',
                'You must provide a valid new password.'
            );

            return;
        }

        if ($password !== $this->postValue('confirm')) {
            $this->message(
                'failure',
                'You must confirm your new password.'
            );

            return;
        }

        $this->removeToken($token);

        $this->auth->logout();

        try {
            $user->setPassword($password);
        } catch (InvalidConfigException | PasswordException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not set new password',
                $exception
            );
        }

        try {
            $user->save();
        } catch (DuplicateInsertException | \PDOException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not save user',
                $exception
            );
        }

        $this->redirect();

        $this->message(
            'success',
            'Your password has been updated. Please log in to continue.'
        );
    }

    /** @throws HttpInternalServerErrorException */
    private function confirmToken(string $token): ?UserData
    {
        try {
            $user = $this->auth->confirm('password')->from($token);

            if ($user instanceof UserData) {
                return $user;
            }
        } catch (InvalidTokenException $exception) {
            $this->message(
                'failure',
                'The link to change your password is invalid or has ' .
                ' expired. Please make another request.'
            );

            $this->redirectToken();
        }

        return null;
    }

    /** @throws HttpInternalServerErrorException */
    private function redirectToken(?string $token = null): void
    {
        try {
            $url = $this->routeCollector->getRouteParser()->urlFor('password');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not determine URL for redirect.',
                $exception
            );
        }

        if (\is_string($token)) {
            $url .= '?token=' . $token;
        }

        $this->redirect($url);
    }

    /** @throws HttpInternalServerErrorException */
    private function removeToken(string $token): void
    {
        try {
            $data = TokenData::fromSubjectToken(
                'password',
                $token,
                $this->settings,
                $this->database
            );
        } catch (InvalidDataException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not find token',
                $exception
            );
        }

        if (\is_null($data)) {
            $this->message(
                'failure',
                'The link to change your password is invalid or has ' .
                ' expired. Please make another request.'
            );

            return;
        }

        try {
            $data->remove();
        } catch (\PDOException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not remove token',
                $exception
            );
        }
    }

    /** @throws HttpInternalServerErrorException */
    private function sendToken(UserData $user): void
    {
        try {
            $expire = new DateTimeImmutable('+1 hour');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not create token expiration datetime',
                $exception
            );
        }

        $token = $this->auth->confirm('password')->getToken($user, $expire);

        $message = $this->mail->message()
            ->to($user->username)
            ->subject('Change Password')
            ->htmlTemplate('mail/password.html.twig')
            ->context(['token' => $token]);

        try {
            $this->mail->send($message);
        } catch (FailedSendingException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not send password confirmation email',
                $exception
            );
        }
    }
}
