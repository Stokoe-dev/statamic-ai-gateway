<?php

namespace Stokoe\AiGateway\Tests;

use Stokoe\AiGateway\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
