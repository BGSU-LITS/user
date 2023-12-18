<?php

declare(strict_types=1);

use Lits\Config\TemplateConfig;
use Lits\Config\UserConfig;
use Lits\Framework;

return function (Framework $framework): void {
    $framework->addConfig('user', new UserConfig());

    $settings = $framework->settings();
    assert($settings['template'] instanceof TemplateConfig);

    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $settings['template']->paths[] = $path . 'template';

    $path .= 'settings.php';

    if (!file_exists($path)) {
        return;
    }

    $result = require $path;

    if (is_null($result)) {
        return;
    }

    assert(is_callable($result));
    $result($framework);
};
