<?php

namespace Phug\Renderer\Profiler;

use ArrayObject;
use Phug\Compiler\Event\CompileEvent;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Event;
use Phug\Formatter\Event\DependencyStorageEvent;
use Phug\Formatter\Event\FormatEvent;
use Phug\FormatterEvent;
use Phug\Lexer\Event\LexEvent;
use Phug\Lexer\Event\TokenEvent;
use Phug\LexerEvent;
use Phug\Parser\Event\NodeEvent as ParserNodeEvent;
use Phug\Parser\Event\ParseEvent;
use Phug\ParserEvent;
use Phug\Renderer;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\RendererEvent;
use Phug\Util\AbstractModule;
use Phug\Util\ModuleContainerInterface;
use SplObjectStorage;

class ProfilerModule extends AbstractModule
{
    /**
     * @var int
     */
    private $startTime = 0;

    /**
     * @var ArrayObject
     */
    private $events = null;

    public function __construct(ArrayObject $events, ModuleContainerInterface $container)
    {
        parent::__construct($container);

        $this->events = $events;
        $this->startTime = microtime(true);
    }

    private function appendParam(Event $event, $key, $value)
    {
        $event->setParams(array_merge($event->getParams(), [
            $key => $value,
        ]));
    }

    private function record(Event $event)
    {
        $this->appendParam($event, '__time', microtime(true) - $this->startTime);
        $this->events[] = $event;
    }

    private function renderProfile()
    {
        $duration = microtime(true) - $this->startTime;
        $linkedProcesses = new SplObjectStorage();
        array_walk($this->events, function (Event $event, $index) use ($linkedProcesses) {
            $link = $event->getParam('__link');
            if (!method_exists($link, 'getName')) {
                $link = new Event('event_'.$index);
            }
            if (!isset($linkedProcesses[$link])) {
                $linkedProcesses[$link] = [];
            }
            $list = $linkedProcesses[$link];
            $list[] = $event->getParam('__time');
            $linkedProcesses[$link] = $list;
        });

        $processes = [];
        $index = 0;
        foreach ($linkedProcesses as $link) {
            $list = $linkedProcesses[$link];
            $min = min($list);
            $max = max($list);
            $processes[] = (object) [
                'link'  => $link->getName(),
                'style' => [
                    'left'  => ($min * 100 / $duration).'%',
                    'width' => (($max - $min) * 100 / $duration).'%',
                    'bottom' => ((++$index) * 10).'px',
                ]
            ];
        }

        return (new Renderer([
            'debug'   => false,
            'filters' => [
                'no-php' => function ($text) {
                    return str_replace('<?', '<<?= "?" ?>', $text);
                },
            ],
        ]))->renderFile(__DIR__.'/resources/index.pug', [
            'profiler_time_precision' => $this->getContainer()->getOption('profiler_time_precision'),
            'processes'               => $processes,
        ]);
    }

    public function getEventListeners()
    {
        return [
            RendererEvent::RENDER => function (RenderEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            RendererEvent::HTML => function (HtmlEvent $event) {
                $this->appendParam($event, '__link', $event->getRenderEvent());
                $this->record($event);

                if ($event->getBuffer()) {
                    $event->setBuffer($this->renderProfile().$event->getBuffer());

                    return;
                }

                $event->setResult($this->renderProfile().$event->getResult());
            },
            CompilerEvent::COMPILE => function (CompileEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            CompilerEvent::ELEMENT => function (ElementEvent $event) {
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode());
                $this->record($event);
            },
            CompilerEvent::NODE => function (NodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            CompilerEvent::OUTPUT => function (OutputEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            FormatterEvent::DEPENDENCY_STORAGE => function (DependencyStorageEvent $event) {
                $this->appendParam($event, '__link', $event->getDependencyStorage());
                $this->record($event);
            },
            FormatterEvent::FORMAT => function (FormatEvent $event) {
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode());
                $this->record($event);
            },
            ParserEvent::PARSE => function (ParseEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            ParserEvent::DOCUMENT => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_ENTER => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_LEAVE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_STORE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            LexerEvent::LEX => function (LexEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            LexerEvent::TOKEN => function (TokenEvent $event) {
                $this->appendParam($event, '__link', $event->getToken());
                $this->record($event);
            },
        ];
    }
}