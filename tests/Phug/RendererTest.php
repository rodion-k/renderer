<?php

namespace Phug\Test;

use JsPhpize\JsPhpizePhug;
use Phug\Compiler\Event\NodeEvent;
use Phug\CompilerInterface;
use Phug\LexerException;
use Phug\Parser\Node\ElementNode;
use Phug\Renderer;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\Adapter\StreamAdapter;
use Phug\Renderer\AdapterInterface;
use Phug\RendererException;

/**
 * @coversDefaultClass \Phug\Renderer
 */
class RendererTest extends AbstractRendererTest
{
    /**
     * @covers ::compile
     * @covers ::render
     */
    public function testRender()
    {
        $actual = trim($this->renderer->render('#{"p"}.foo Hello'));

        self::assertSame('<p class="foo">Hello</p>', $actual);
    }

    /**
     * @covers ::renderFile
     * @covers ::render
     * @covers ::render
     */
    public function testRenderFile()
    {
        $actual = str_replace(
            ["\r", "\n"],
            '',
            trim($this->renderer->renderFile(__DIR__.'/../cases/code.pug'))
        );
        $expected = str_replace(
            ["\r", "\n"],
            '',
            trim(file_get_contents(__DIR__.'/../cases/code.html'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::displayFile
     * @covers ::display
     */
    public function testDisplay()
    {
        ob_start();
        $this->renderer->display('#{"p"}.foo Hello');
        $actual = str_replace(
            "\r",
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();

        self::assertSame('<p class="foo">Hello</p>', $actual);
    }

    /**
     * @covers ::displayFile
     */
    public function testDisplayFile()
    {
        ob_start();
        $this->renderer->displayFile(__DIR__.'/../cases/code.pug');
        $actual = str_replace(
            "\r",
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();
        $expected = str_replace(
            "\r",
            '',
            trim(file_get_contents(__DIR__.'/../cases/code.html'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::compileFile
     * @covers ::compile
     */
    public function testCompile()
    {
        $actual = trim($this->renderer->compile('p Hello'));

        self::assertSame('<p>Hello</p>', $actual);
    }

    /**
     * @covers ::compileFile
     * @covers ::compile
     */
    public function testCompileFile()
    {
        $actual = $this->renderer->compileFile(__DIR__.'/../cases/basic.pug');
        $actual = str_replace(
            "\r",
            '',
            trim($actual)
        );
        $expected = str_replace(
            "\r",
            '',
            trim(file_get_contents(__DIR__.'/../cases/basic.html'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::__construct
     * @covers ::initCompiler
     */
    public function testFilter()
    {
        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('script: :cdata foo'))
        );
        self::assertSame('<script><![CDATA[foo]]></script>', $actual);
    }

    /**
     * @covers ::initCompiler
     */
    public function testInitCompiler()
    {
        $renderer = new Renderer([
            'on_render' => function (Renderer\Event\RenderEvent $event) {
                $event->setInput(str_replace('section', 'blockquote', $event->getInput()));
            },
            'on_html' => function (Renderer\Event\HtmlEvent $event) {
                $event->setResult(str_replace('div', 'p', $event->getResult()));
            },
            'on_node' => function (NodeEvent $event) {
                $node = $event->getNode();
                if ($node instanceof ElementNode && $node->getName() === 'blockquote') {
                    $node->setName('div');
                }
            },
        ]);
        $html = trim($renderer->render('section: span'));

        self::assertSame('<p><span></span></p>', $html);

        $renderer->setOptions([
            'on_render' => function (Renderer\Event\RenderEvent $event) {
                $event->setInput(str_replace('span', 'strong', $event->getInput()));
            },
            'on_html' => function (Renderer\Event\HtmlEvent $event) {
                $event->setResult(str_replace('em', 'i', $event->getResult()));
            },
            'on_node' => function (NodeEvent $event) {
                $node = $event->getNode();
                if ($node instanceof ElementNode && $node->getName() === 'strong') {
                    $node->setName('em');
                }
            },
        ]);
        $renderer->initCompiler();
        $html = trim($renderer->render('section: span'));

        self::assertSame('<section><i></i></section>', $html);
    }

    /**
     * @covers ::__construct
     * @covers ::initCompiler
     * @covers ::getCompiler
     * @covers ::handleOptionAliases
     */
    public function testBasedir()
    {
        $renderer = new Renderer([
            'basedir' => ['a'],
            'paths'   => ['b'],
        ]);
        $paths = $renderer->getCompiler()->getOption('paths');
        self::assertCount(2, $paths);
        self::assertContains('a', $paths);
        self::assertContains('b', $paths);
    }

    /**
     * @covers ::share
     * @covers ::resetSharedVariables
     * @covers ::mergeWithSharedVariables
     */
    public function testShare()
    {
        $this->renderer->share([
            'foo' => 1,
            'bar' => 2,
        ]);
        $this->renderer->share('foo', 4);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('=foo + bar'))
        );
        self::assertSame('6', $actual);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('=foo - bar'))
        );
        self::assertSame('2', $actual);

        $this->renderer->resetSharedVariables();
        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('=foo || bar ? "ok" : "ko"'))
        );
        self::assertSame('ko', $actual);
    }

    public function testInterpolations()
    {
        $actual = str_replace(
            ["\n", "\r"],
            '',
            trim($this->renderer->render('p #[em #[strong Go]]'))
        );
        self::assertSame('<p><em><strong>Go</strong></em></p>', $actual);
    }

    /**
     * @covers ::getAdapter
     * @covers ::getCompiler
     * @covers ::callAdapter
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Adapter\EvalAdapter::display
     */
    public function testOptions()
    {
        $this->renderer->setOption('pretty', true);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('section: div Hello'))
        );
        self::assertSame(
            "<section>\n".
            "  <div>Hello</div>\n".
            '</section>',
            $actual
        );

        $this->renderer->setOption('pretty', false);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->render('section: div Hello'))
        );
        self::assertSame(
            '<section>'.
            '<div>Hello</div>'.
            '</section>',
            $actual
        );

        $template = "p\n    i\n\tb";
        $actual = trim($this->renderer->render($template));
        self::assertSame('<p><i></i><b></b></p>', $actual);

        $this->renderer->setOptionsRecursive([
            'adapter_class_name' => FileAdapter::class,
            'allow_mixed_indent' => false,
        ]);
        $message = '';

        try {
            $this->renderer->render($template);
        } catch (LexerException $error) {
            $message = $error->getMessage();
        }

        self::assertSame(
            'Failed to lex: Invalid indentation, you can use tabs or spaces but not both'."\n".
            'Near: b'."\n".
            'Line: 3'."\n".
            'Offset: 2',
            trim(preg_replace('/\s*\n\s*/', "\n", $message))
        );
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers ::getDebuggedException
     * @covers ::setDebugFile
     * @covers ::setDebugString
     * @covers ::setDebugFormatter
     * @covers ::getDebugFormatter
     * @covers ::hasColorSupport
     * @covers ::getRendererException
     * @covers ::getErrorMessage
     * @covers ::highlightLine
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleErrorInString()
    {
        foreach ([
            FileAdapter::class,
            EvalAdapter::class,
            StreamAdapter::class,
        ] as $adapter) {
            $renderer = new Renderer([
                'debug'              => false,
                'adapter_class_name' => $adapter,
            ]);
            $message = null;

            try {
                $renderer->render('div: p=12/0');
            } catch (\Exception $error) {
                $message = $error->getMessage();
            }

            self::assertContains(
                'Division by zero',
                $message
            );

            self::assertNotContains(
                '12/0',
                str_replace(' ', '', $message)
            );

            $message = null;
            $renderer->setOption('debug', true);

            try {
                $renderer->render('div: p=12/0');
            } catch (RendererException $error) {
                $message = $error->getMessage();
            }

            self::assertContains(
                'Division by zero on line 1',
                $message
            );

            self::assertContains(
                '12/0',
                str_replace(' ', '', $message)
            );

            $message = null;
            $renderer->setOption('color_support', true);

            try {
                $renderer->render('div: p=12/0');
            } catch (RendererException $error) {
                $message = $error->getMessage();
            }

            self::assertContains(
                'Division by zero on line 1',
                $message
            );

            self::assertContains(
                "\033[43;30m>   1 | div: p\033[43;31m=\033[43;30m12/0\e[0m\n",
                $message
            );
        }
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers ::getDebuggedException
     * @covers ::setDebugFile
     * @covers ::setDebugString
     * @covers ::setDebugFormatter
     * @covers ::getDebugFormatter
     * @covers ::hasColorSupport
     * @covers ::getRendererException
     * @covers ::getErrorMessage
     * @covers ::highlightLine
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testContextLines()
    {
        $renderer = new Renderer([
            'debug'               => true,
            'error_context_lines' => 3,
            'color_support'       => false,
            'adapter_class_name'  => EvalAdapter::class,
        ]);
        $message = null;

        try {
            $renderer->render(implode("\n", [
                '// line -5',
                '// line -4',
                '// line -3',
                '// line -2',
                '// line -1',
                'div: p=12/0',
                '// line +1',
                '// line +2',
                '// line +3',
                '// line +4',
                '// line +5',
            ]));
        } catch (\Exception $error) {
            $message = $error->getMessage();
        }

        self::assertContains('Division by zero', $message);
        self::assertContains('div: p=12/0', $message);
        self::assertContains('// line -1', $message);
        self::assertContains('// line +1', $message);
        self::assertContains('// line -2', $message);
        self::assertContains('// line +2', $message);
        self::assertContains('// line -3', $message);
        self::assertContains('// line +3', $message);
        self::assertNotContains('// line -4', $message);
        self::assertNotContains('// line +4', $message);
        self::assertNotContains('// line -5', $message);
        self::assertNotContains('// line +5', $message);
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebuggedException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFile
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugString
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::hasColorSupport
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getRendererException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getErrorMessage
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::highlightLine
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleErrorInFile()
    {
        $message = null;
        $renderer = new Renderer([
            'debug'         => false,
            'pretty'        => true,
            'error_handler' => function ($error) use (&$message) {
                /* @var \Throwable $error */
                $message = $error->getMessage();
            },
        ]);
        $path = realpath(__DIR__.'/../utils/error.pug');
        $renderer->renderFile($path);

        self::assertContains(
            defined('HHVM_VERSION')
                ? 'Invalid operand type was used: implode() '.
                'expects a container as one of the arguments'
                : 'implode(): Invalid arguments passed',
            $message
        );

        self::assertNotContains(
            'on line 3',
            $message
        );

        self::assertNotContains(
            $path,
            $message
        );

        $message = null;
        $renderer->setOption('debug', true);
        $renderer->renderFile($path);

        self::assertContains(
            defined('HHVM_VERSION')
                ? 'Invalid operand type was used: implode() '.
                'expects a container as one of the arguments on line 3'
                : 'implode(): Invalid arguments passed on line 3',
            $message
        );

        self::assertContains(
            $path,
            $message
        );

        self::assertContains(
            "implode('','')",
            $message
        );
    }

    /**
     * @covers ::__construct
     * @covers ::initCompiler
     */
    public function testCompilerClassNameException()
    {
        $message = null;

        try {
            new Renderer([
                'compiler_class_name' => Renderer::class,
            ]);
        } catch (RendererException $exception) {
            $message = $exception->getMessage();
        }

        self::assertSame('Passed compiler class '.Renderer::class.' is '.
            'not a valid '.CompilerInterface::class, $message);
    }

    /**
     * @covers ::__construct
     * @covers ::initCompiler
     */
    public function testAdapterClassNameException()
    {
        $message = null;

        try {
            new Renderer([
                'adapter_class_name' => Renderer::class,
            ]);
        } catch (RendererException $exception) {
            $message = $exception->getMessage();
        }

        self::assertSame('Passed adapter class '.Renderer::class.' is '.
            'not a valid '.AdapterInterface::class, $message);
    }

    /**
     * @covers ::__construct
     * @covers ::initCompiler
     * @covers ::callAdapter
     */
    public function testSelfOption()
    {
        $renderer = new Renderer([
            'self'    => false,
            'modules' => [JsPhpizePhug::class],
        ]);

        self::assertSame('bar', $renderer->render('=foo', [
            'foo' => 'bar',
        ]));
        self::assertSame('bar', $renderer->render('=foo', [
            'foo'  => 'bar',
            'self' => [
                'foo' => 'oof',
            ],
        ]));
        self::assertSame('oof', $renderer->render('=self.foo', [
            'foo'  => 'bar',
            'self' => [
                'foo' => 'oof',
            ],
        ]));

        $renderer->setOption('self', true);

        self::assertSame('', $renderer->render('=foo', [
            'foo' => 'bar',
        ]));
        self::assertSame('', $renderer->render('=foo', [
            'foo' => 'bar',
        ]));
        self::assertSame('', $renderer->render('=foo', [
            'foo'  => 'bar',
            'self' => [
                'foo' => 'oof',
            ],
        ]));
        self::assertSame('bar', $renderer->render('=self.foo', [
            'foo'  => 'bar',
            'self' => [
                'foo' => 'oof',
            ],
        ]));

        $renderer->setOption('self', 'locals');

        self::assertSame('', $renderer->render('=foo', [
            'foo' => 'bar',
        ]));
        self::assertSame('', $renderer->render('=self.foo', [
            'foo' => 'bar',
        ]));
        self::assertSame('bar', $renderer->render('=locals.foo', [
            'foo'  => 'bar',
        ]));
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebuggedException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getErrorAsHtml
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFile
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugString
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::hasColorSupport
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getRendererException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getErrorMessage
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::highlightLine
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleHtmlError()
    {
        $lastError = null;
        foreach ([
             FileAdapter::class,
             EvalAdapter::class,
             StreamAdapter::class,
        ] as $adapter) {
            $renderer = new Renderer([
                'debug'              => true,
                'html_error'         => true,
                'adapter_class_name' => $adapter,
                'error_handler'      => function ($error) use (&$lastError) {
                    $lastError = $error;
                },
            ]);
            $renderer->render('div: p=12/0');

            /* @var RendererException $lastError */
            self::assertInstanceOf(RendererException::class, $lastError);
            $message = $lastError->getMessage();
            self::assertContains('Division by zero on line 1, offset 7', $message);
            self::assertContains('<span class="error-line">'.
                'div: p=<span class="error-offset">1</span>2/0</span>', $message);
        }
    }

    /**
     * @group error
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebuggedException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFile
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugString
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::setDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getDebugFormatter
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::hasColorSupport
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getRendererException
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::getErrorMessage
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::highlightLine
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleParseError()
    {
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            self::markTestSkipped('Parse error can only be caught since PHP 7.');

            return;
        }

        $renderer = new Renderer([
            'debug'              => true,
            'adapter_class_name' => FileAdapter::class,
        ]);

        $message = null;

        try {
            $renderer->render(
                "doctype html\n".
                "html\n".
                "  //-\n".
                "    Many\n".
                "    Boring\n".
                "    So\n".
                "    Boring\n".
                "    Comments\n".
                "  head\n".
                "    title Foo\n".
                "  body\n".
                "    section= MyClass:::error()\n".
                "    footer\n".
                "      | End\n".
                "  //-\n".
                "    Many\n".
                "    Boring\n".
                "    So\n".
                "    Boring\n".
                "    Comments\n".
                '  // Too far to be visible error context'
            );
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertContains('ParseError:', $message);
        self::assertContains(' on line 12, offset 12', $message);
        self::assertContains('title Foo', $message);
        self::assertNotContains('Too far to be visible error context', $message);
    }

    public function testHeadIndent()
    {
        $renderer = new Renderer([
            'pretty' => true,
        ]);

        $html = str_replace("\r", '', trim($renderer->render(implode("\n", [
            'doctype html',
            'html',
            '  head',
            '    link(href="style.css")',
            '    script(src="script.js")',
            '    meta(charset="utf-8")',
        ]))));

        self::assertSame(implode("\n", [
            '<!DOCTYPE html>',
            '<html>',
            '  <head>',
            '    <link href="style.css">',
            '    <script src="script.js"></script>',
            '    <meta charset="utf-8">',
            '  </head>',
            '</html>',
        ]), $html);
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage p= $undefined()
     */
    public function testExtendsUndefinedCall()
    {
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            self::markTestSkipped('Parse error can only be caught since PHP 7.');

            return;
        }

        $renderer = new Renderer([
            'pretty' => true,
        ]);

        $renderer->renderFile(__DIR__.'/../call-undefined/extends-call-undefined.pug');
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage div= $undefined()
     */
    public function testUndefinedCallInBlock()
    {
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            self::markTestSkipped('Parse error can only be caught since PHP 7.');

            return;
        }

        $renderer = new Renderer([
            'pretty' => true,
        ]);

        $renderer->renderFile(__DIR__.'/../call-undefined/call-undefined-in-block.pug');
    }
}
