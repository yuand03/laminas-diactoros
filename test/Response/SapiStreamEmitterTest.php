<?php

/**
 * @see       https://github.com/laminas/laminas-diactoros for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diactoros/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Diactoros\Response;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\SapiStreamEmitter;
use LaminasTest\Diactoros\TestAsset\HeaderStack;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class SapiStreamEmitterTest extends SapiEmitterTest
{
    public function setUp()
    {
        HeaderStack::reset();
        $this->emitter = new SapiStreamEmitter();
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown()
    {
        $stream = $this->prophesize('Psr\Http\Message\StreamInterface');
        $stream->__toString()->willReturn('Content!');
        $stream->isSeekable()->willReturn(false);
        $stream->eof()->willReturn(true);
        $stream->rewind()->willReturn(true);
        $stream->getSize()->willReturn(null);
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();
        foreach (HeaderStack::stack() as $header) {
            $this->assertNotContains('Content-Length:', $header);
        }
    }

    public function contentRangeProvider()
    {
        return [
            ['bytes 0-2/*', 'Hello world', 'Hel'],
            ['bytes 3-6/*', 'Hello world', 'lo w'],
        ];
    }

    /**
     * @dataProvider contentRangeProvider
     */
    public function testContentRange($header, $body, $expected)
    {
        $response = (new Response())
            ->withHeader('Content-Range', $header);

        $response->getBody()->write($body);

        ob_start();
        $this->emitter->emit($response);
        $this->assertEquals($expected, ob_get_clean());
    }
}
