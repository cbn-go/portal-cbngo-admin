<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;

class HostingerPublicPathTest extends TestCase
{
    public function test_uses_laravel_public_directory_when_public_html_is_absent(): void
    {
        $this->assertDirectoryExists(base_path('public'));
        $this->assertDirectoryDoesNotExist(base_path('public_html'));
        $this->assertSame(base_path('public'), public_path());
        $this->assertSame(base_path('public'), app()->publicPath());
    }
}
