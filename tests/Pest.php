<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProblemJsonAssertions;

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Unit');

ProblemJsonAssertions::register();
