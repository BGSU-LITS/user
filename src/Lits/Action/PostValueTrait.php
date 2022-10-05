<?php

declare(strict_types=1);

namespace Lits\Action;

use Slim\Exception\HttpInternalServerErrorException;

trait PostValueTrait
{
    /** @throws HttpInternalServerErrorException */
    protected function postValue(string $key): ?string
    {
        $post = $this->request->getParsedBody();

        if (!\is_array($post)) {
            throw new HttpInternalServerErrorException($this->request);
        }

        if (
            isset($post[$key]) &&
            \is_string($post[$key]) &&
            $post[$key] !== ''
        ) {
            return $post[$key];
        }

        return null;
    }
}
