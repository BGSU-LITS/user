<?php

declare(strict_types=1);

use Lits\Framework;
use Lits\Package\AuthPackage;
use Lits\Package\DatabasePackage;
use Lits\Package\MailPackage;
use Lits\Package\ProjectPackage;

require_once dirname(dirname(__DIR__)) .
    DIRECTORY_SEPARATOR . 'simplesamlphp' .
    DIRECTORY_SEPARATOR . 'src' .
    DIRECTORY_SEPARATOR . '_autoload.php';

require_once dirname(__DIR__) .
    DIRECTORY_SEPARATOR . 'vendor' .
    DIRECTORY_SEPARATOR . 'autoload.php';

return new Framework([
    new AuthPackage(),
    new DatabasePackage(),
    new MailPackage(),
    new ProjectPackage(),
]);
