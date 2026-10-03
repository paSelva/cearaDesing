<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

$databaseIsolation = filter_var(
    getenv('TEST_WITH_EXISTING_SCHEMA') ?: false,
    FILTER_VALIDATE_BOOL,
) ? DatabaseTransactions::class : RefreshDatabase::class;

pest()->extend(TestCase::class)
    ->use($databaseIsolation)
    ->in('Feature');

require_once __DIR__.'/Feature/Entries/EntryFixtures.php';
require_once __DIR__.'/Feature/Evaluation/EvaluationFixtures.php';
