<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// include 'list.html.twig';

// require_once dirname(__DIR__).'/scr/Controller/PostsListContoller.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
