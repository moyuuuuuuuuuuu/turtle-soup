<?php

declare(strict_types=1);

namespace Tests\Ai;

use App\Ai\Services\CozeContentParser;
use App\Ai\Services\MockContentParser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class CozeContentParserTest extends TestCase
{
    public function testParsesNestedResult(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $parser = $this->parser([
            new Response(200, [], json_encode([
                'code' => 0,
                'data' => ['result' => json_encode($result, JSON_THROW_ON_ERROR)],
            ], JSON_THROW_ON_ERROR)),
        ]);

        self::assertSame($result, $parser->parse(['story' => 'safe input']));
    }

    public function testDoesNotRetryAuthenticationFailure(): void
    {
        $handler = new MockHandler([new Response(401), new Response(200)]);
        $parser = $this->parserFromHandler($handler, retries: 2);

        try {
            $parser->parse(['story' => 'safe input']);
            self::fail('Expected authentication failure');
        } catch (RuntimeException $exception) {
            self::assertSame('ai.auth_failed', $exception->getMessage());
            self::assertCount(1, $handler);
        }
    }

    public function testRetriesServerFailureAndThenSucceeds(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $handler = new MockHandler([
            new Response(503),
            new Response(200, [], json_encode(['code' => 0, 'data' => $result], JSON_THROW_ON_ERROR)),
        ]);
        $parser = $this->parserFromHandler($handler, retries: 1);

        self::assertSame($result, $parser->parse(['story' => 'safe input']));
        self::assertCount(0, $handler);
    }

    public function testMapsConnectionFailureAfterRetries(): void
    {
        $request = new Request('POST', '/v1/workflow/run');
        $handler = new MockHandler([
            new ConnectException('connection failed', $request),
            new ConnectException('connection failed', $request),
        ]);
        $parser = $this->parserFromHandler($handler, retries: 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ai.workflow_timeout');
        $parser->parse(['story' => 'safe input']);
    }

    #[DataProvider('invalidResponseProvider')]
    public function testRejectsInvalidResponse(Response $response): void
    {
        $parser = $this->parser([$response]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ai.invalid_response');
        $parser->parse(['story' => 'safe input']);
    }

    /** @return iterable<string, array{Response}> */
    public static function invalidResponseProvider(): iterable
    {
        yield 'invalid json' => [new Response(200, [], '{')];
        yield 'invalid nested json' => [new Response(200, [], '{"code":0,"data":{"result":"{"}}')];
        yield 'missing data' => [new Response(200, [], '{"code":0}')];
    }

    /** @param list<Response> $responses */
    private function parser(array $responses): CozeContentParser
    {
        return $this->parserFromHandler(new MockHandler($responses));
    }

    private function parserFromHandler(MockHandler $handler, int $retries = 0): CozeContentParser
    {
        return new CozeContentParser(
            new Client(['handler' => HandlerStack::create($handler)]),
            [
                'base_url' => 'https://api.coze.cn',
                'token' => 'test-token',
                'workflow_id' => 'test-workflow',
                'workflow_version' => 'test-version',
                'timeout' => 1,
                'retries' => $retries,
                'retry_delay_ms' => 0,
            ],
            new NullLogger(),
        );
    }
}
