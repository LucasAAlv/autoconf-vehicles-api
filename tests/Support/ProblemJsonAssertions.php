<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

class ProblemJsonAssertions
{
    public static function register(): void
    {
        TestResponse::macro('assertProblemJson', function (
            int $status,
            ?string $title = null,
            ?string $detail = null,
            ?array $errorKeys = null,
        ): TestResponse {
            /** @var TestResponse $this */
            $this->assertStatus($status);
            $this->assertHeader('Content-Type', 'application/problem+json');

            $body = $this->json();
            Assert::assertSame('about:blank', $body['type'] ?? null);
            Assert::assertSame($status, $body['status'] ?? null);
            Assert::assertIsString($body['title'] ?? null);
            Assert::assertNotSame('', $body['title']);
            Assert::assertStringStartsWith('/', $body['instance'] ?? '');

            if ($title !== null) {
                Assert::assertSame($title, $body['title']);
            }
            if ($detail !== null) {
                Assert::assertSame($detail, $body['detail'] ?? null);
            }
            if ($errorKeys !== null) {
                Assert::assertArrayHasKey('errors', $body);
                foreach ($errorKeys as $key) {
                    Assert::assertArrayHasKey($key, $body['errors']);
                }
            }

            return $this;
        });
    }
}
