<?php

namespace Phug\Test;

use JsPhpize\JsPhpizePhug;
use Phug\Renderer;

abstract class AbstractRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Renderer
     */
    protected $renderer;

    public function setUp()
    {
        include_once __DIR__.'/Date.php';
        $lastCompiler = null;
        $this->renderer = new Renderer([
            'basedir' => __DIR__.'/..',
            'pretty'  => true,
            'modules' => [JsPhpizePhug::class],
        ]);
    }

    public static function flatContent($content)
    {
        return implode('', array_map(function ($line) {
            $line = trim($line);
            $line = preg_replace_callback('/(\s+[a-z:_-]+="(?:\\\\[\\S\\s]|[^"\\\\])*"){2,}/', function ($matches) {
                $attributes = [];
                $input = $matches[0];
                while (mb_strlen($input) && preg_match('/^\s+[a-z:_-]+="(?:\\\\[\\S\\s]|[^"\\\\])*"/', $input, $match)) {
                    $attributes[] = trim($match[0]);
                    $input = mb_substr($input, mb_strlen($match[0]));
                }
                sort($attributes);

                return ' '.implode(' ', $attributes);
            }, $line);

            return $line;
        }, preg_split('/\r|\n/', self::standardLines($content))));
    }

    public static function standardLines($content)
    {
        $content = preg_replace('/\s*<!--\s*(\S[\s\S]*?\S)\s*-->/', '<!--$1-->', $content);

        return str_replace(["\r\n", '/><', ' />'], ["\n", "/>\n<", '/>'], trim($content));
    }

    public static function assertSameLines($expected, $actual, $message = null)
    {
        $flatExpected = self::flatContent($expected);
        $flatActual = self::flatContent($actual);
        if ($flatExpected === $flatActual) {
            self::assertSame($flatExpected, $flatActual, $message);

            return;
        }
        $expected = self::standardLines($expected);
        $actual = self::standardLines($actual);

        if (is_callable($message)) {
            $message = $message();
        }

        self::assertSame($expected, $actual, $message);
    }
}
