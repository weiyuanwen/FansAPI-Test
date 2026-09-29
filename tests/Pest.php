<?php

/*
|--------------------------------------------------------------------------
| Pest Test Case Registration
|--------------------------------------------------------------------------
|
| Bind all test suites to Tests\TestCase to ensure unified access to the
| application container, database migrations, and clean teardown lifecycle.
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');
