<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        // RefreshDatabase runs migrate:fresh, which drops every table in the target
        // database. Never let that point at a non-test database.
        if (preg_match('/_test(_\d+)?$/', $database) !== 1) {
            throw new RuntimeException(
                "Refusing to run the suite against database [{$database}]; expected a name ending in \"_test\"."
            );
        }
    }
}
