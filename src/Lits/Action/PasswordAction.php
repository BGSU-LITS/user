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

        $post = $this->request->getParsedBody();

        if (!\is_array($post)) {
            throw new HttpInternalServerErrorException($this->request);
        }

        if (isset($post['token'])) {
            $this->postUpdate($post);

            return $this->response;
        }

        $this->redirectPassword();

        if (
            !isset($post['username']) ||
            \filter_var($post['username'], \FILTER_VALIDATE_EMAIL) === false
        ) {
            $this->message(
                'failure',
                'You must provide a valid email address.'
            );

            return $this->response;
        }

        try {
            $user = UserData::fromUsername(
                (string) $post['username'],
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
            try {
                $expire = new DateTimeImmutable('+1 hour');
            } catch (\Throwable $exception) {
                throw new HttpInternalServerErrorException(
                    $this->request,
                    'Could not create token expiration datetime',
                    $exception
                );
            }

            $token = $this->auth->confirm('password')
                ->getToken($user, $expire);

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

        $this->message(
            'success',
            'Check your email for instructions on how to change your password.'
        );

        return $this->response;
    }

    /** @throws HttpInternalServerErrorException */
    private function confirmToken(string $token): ?UserData
    {
        try {
            $user = $this->auth
                ->confirm('password')
                ->from($token);

            if ($user instanceof UserData) {
                return $user;
            }
        } catch (InvalidTokenException $exception) {
            $this->message(
                'failure',
                'The link to change your password is invalid or has ' .
                ' expired. Please make another request.'
            );

            $this->redirectPassword();
        }

        return null;
    }

    /**
     * @param mixed[] $post
     * @throws HttpInternalServerErrorException
     */
    private function postUpdate(array $post): void
    {
        if (!isset($post['token']) || !\is_string($post['token'])) {
            return;
        }

        $user = $this->confirmToken($post['token']);

        if (!isset($user)) {
            return;
        }

        $this->redirectPassword($post['token']);

        if (!isset($post['password']) || $post['password'] === '') {
            $this->message(
                'failure',
                'You must provide a valid new password.'
            );

            return;
        }

        if (
            !isset($post['confirm']) ||
            $post['password'] !== $post['confirm']
        ) {
            $this->message(
                'failure',
                'You must confirm your new password.'
            );

            return;
        }

        try {
            $token = TokenData::fromSubjectToken(
                'password',
                $post['token'],
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

        if (\is_null($token)) {
            $this->message(
                'failure',
                'The link to change your password is invalid or has ' .
                ' expired. Please make another request.'
            );

            return;
        }

        try {
            $token->remove();
        } catch (\PDOException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not remove token',
                $exception
            );
        }

        $this->auth->logout();

        try {
            $user->setPassword((string) $post['password']);
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
    private function redirectPassword(?string $token = null): void
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
}
