<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;
use SimpleSAML\Auth\Simple;

final class UserConfig extends Config
{
    public ?string $note_index = null;
    public ?string $note_password = null;
    public ?string $ssp_auth_source = null;
    public string $ssp_attribute = 'uid';
    public string $ssp_format = '%s';
    public ?string $ssp_title = null;
    public ?string $ssp_title_other = null;
    public ?string $ssp_button = null;

    private ?Simple $ssp = null;

    public function ssp(): ?Simple
    {
        if (\is_null($this->ssp) && !\is_null($this->ssp_auth_source)) {
            $this->ssp = new Simple($this->ssp_auth_source);
        }

        return $this->ssp;
    }
}
