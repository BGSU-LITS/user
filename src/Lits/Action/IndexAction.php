<?php

declare(strict_types=1);

namespace Lits\Action;

use Slim\Exception\HttpInternalServerErrorException;

final class IndexAction extends AuthAction
{
    /** @throws HttpInternalServerErrorException */
    #[\Override]
    public function action(): void
    {
        try {
            $this->render($this->template());
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception,
            );
        }
    }
}
