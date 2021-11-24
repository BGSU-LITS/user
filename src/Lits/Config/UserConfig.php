<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;

final class UserConfig extends Config
{
    public ?string $note_index = null;
    public ?string $note_password = null;
}
