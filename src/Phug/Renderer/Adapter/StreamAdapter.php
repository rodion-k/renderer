<?php

namespace Phug\Renderer\Adapter;

use Phug\Renderer\AbstractAdapter;
use Phug\Renderer\Adapter\Stream\Template;

class StreamAdapter extends AbstractAdapter
{
    public function __construct(array $options)
    {
        $this->setOptions([
            'stream_name'   => 'pug',
            'stream_suffix' => '.stream',
        ]);

        parent::__construct($options);
    }

    public function display($__pug_php, array $__pug_parameters)
    {
        $stream = $this->getOption('stream_name').
            $this->getOption('stream_suffix');
        if (!in_array($stream, stream_get_wrappers())) {
            stream_register_wrapper($stream, Template::class);
        }
        extract($__pug_parameters);
        include $stream.'://data;'.$__pug_php;
    }
}
