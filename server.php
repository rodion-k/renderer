<?php

use Phug\Renderer;

include __DIR__.'/vendor/autoload.php';

$renderer = new Renderer([
    'enable_profiler' => true,
]);

$renderer->display('div');
