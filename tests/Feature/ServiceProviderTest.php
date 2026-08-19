<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tests\Feature;

use Qredit\LaravelQredit\Tests\TestCase;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Facades\Qredit as QreditFacade;

class ServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_the_service_provider()
    {
        $this->assertTrue($this->app->providerIsLoaded('Qredit\LaravelQredit\QreditServiceProvider'));
    }

    /** @test */
    public function it_registers_qredit_as_singleton()
    {
        $qredit1 = $this->app->make(Qredit::class);
        $qredit2 = $this->app->make(Qredit::class);

        $this->assertSame($qredit1, $qredit2);
    }

    /** @test */
    public function it_provides_qredit_facade()
    {
        $this->assertInstanceOf(
            Qredit::class,
            QreditFacade::getFacadeRoot()
        );
    }

    /** @test */
    public function it_publishes_config_file()
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Qredit\LaravelQredit\QreditServiceProvider',
            '--tag' => 'qredit-config',
        ]);

        $this->assertFileExists(config_path('qredit.php'));

        // Clean up
        @unlink(config_path('qredit.php'));
    }

    /** @test */
    public function it_merges_config_correctly()
    {
        config(['qredit.custom_key' => 'custom_value']);

        $this->assertEquals('custom_value', config('qredit.custom_key'));
        $this->assertEquals('test-api-key', config('qredit.api_key'));
    }

    /** @test */
    public function it_registers_webhook_route_when_enabled()
    {
        config(['qredit.webhook.enabled' => true]);
        config(['qredit.webhook.path' => '/test-webhook']);

        $this->app->register('Qredit\LaravelQredit\QreditServiceProvider');

        $routes = collect($this->app['router']->getRoutes()->getRoutes())
            ->map(function ($route) {
                return $route->uri();
            })
            ->toArray();

        $this->assertContains('test-webhook', $routes);
    }

    /** @test */
    public function it_registers_artisan_commands()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('qredit:test', $commands);
        $this->assertArrayHasKey('qredit:webhook', $commands);
    }
}