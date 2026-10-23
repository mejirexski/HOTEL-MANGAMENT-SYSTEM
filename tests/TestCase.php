<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $driver = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION');

        if ($driver !== 'sqlite') {
            throw new \RuntimeException(
                'Tests must run on SQLite. Set DB_CONNECTION=sqlite and DB_DATABASE=:memory: in phpunit.xml so your MySQL data is never wiped.'
            );
        }

        parent::setUp();
    }
}