<?php

namespace Orchestra\Testbench\BrowserKit;

use Mockery;
use Laravel\BrowserKitTesting\WithoutEvents;
use Orchestra\Testbench\Traits\WithFactories;
use Laravel\BrowserKitTesting\WithoutMiddleware;
use Orchestra\Testbench\Traits\ApplicationTrait;
use Laravel\BrowserKitTesting\DatabaseMigrations;
use Laravel\BrowserKitTesting\DatabaseTransactions;
use Laravel\BrowserKitTesting\Concerns\ImpersonatesUsers;
use Laravel\BrowserKitTesting\Concerns\MakesHttpRequests;
use Laravel\BrowserKitTesting\Concerns\InteractsWithConsole;
use Laravel\BrowserKitTesting\Concerns\InteractsWithSession;
use Laravel\BrowserKitTesting\Concerns\InteractsWithDatabase;
use Laravel\BrowserKitTesting\Concerns\InteractsWithContainer;
use Laravel\BrowserKitTesting\Concerns\MocksApplicationServices;
use Laravel\BrowserKitTesting\Concerns\InteractsWithAuthentication;
use Orchestra\Testbench\BrowserKit\Contracts\TestCase as TestCaseContract;

abstract class TestCase extends \PHPUnit_Framework_TestCase implements TestCaseContract
{
    use ApplicationTrait,
        InteractsWithContainer,
        MakesHttpRequests,
        ImpersonatesUsers,
        InteractsWithAuthentication,
        InteractsWithConsole,
        InteractsWithDatabase,
        InteractsWithSession,
        MocksApplicationServices,
        WithFactories;

    /**
     * The Illuminate application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * The callbacks that should be run after the application is created.
     *
     * @var array
     */
    protected $afterApplicationCreatedCallbacks = [];

    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * Indicates if we have made it through the base setUp function.
     *
     * @var bool
     */
    protected $setUpHasRun = false;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp()
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            call_user_func($callback);
        }

        $this->setUpHasRun = true;
    }

    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        putenv('APP_ENV=testing');

        $this->app = $this->createApplication();
    }

    /**
     * Boot the testing helper traits.
     *
     * @return void
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[DatabaseTransactions::class])) {
            $this->beginDatabaseTransaction();
        }

        if (isset($uses[DatabaseMigrations::class])) {
            $this->runDatabaseMigrations();
        }

        if (isset($uses[WithoutMiddleware::class])) {
            $this->disableMiddlewareForAllTests();
        }

        if (isset($uses[WithoutEvents::class])) {
            $this->disableEventsForAllTests();
        }
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown()
    {
        if (class_exists('Mockery')) {
            Mockery::close();
        }

        if ($this->app) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                call_user_func($callback);
            }

            $this->app->flush();

            $this->app = null;
        }

        $this->setUpHasRun = false;

        if (property_exists($this, 'serverVariables')) {
            $this->serverVariables = [];
        }

        $this->afterApplicationCreatedCallbacks    = [];
        $this->beforeApplicationDestroyedCallbacks = [];
    }

    /**
     * Register a callback to be run after the application is created.
     *
     * @param  callable  $callback
     *
     * @return void
     */
    protected function afterApplicationCreated(callable $callback)
    {
        $this->afterApplicationCreatedCallbacks[] = $callback;

        if ($this->setUpHasRun) {
            call_user_func($callback);
        }
    }

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @param  string|array  $realpah
     *
     * @return void
     */
    protected function loadMigrationsFrom($realpath)
    {
        $options = is_array($realpath) ? $realpath : ['--realpath' => $realpath];

        $this->artisan('migrate', $options);

        $this->app[ConsoleKernel::class]->setArtisan(null);

        $this->beforeApplicationDestroyed(function () use ($options) {
            $this->artisan('migrate:rollback', $options);
        });
    }

    /**
     * Register a callback to be run before the application is destroyed.
     *
     * @param  callable  $callback
     *
     * @return void
     */
    protected function beforeApplicationDestroyed(callable $callback)
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application   $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Define your environment setup.
    }
}
