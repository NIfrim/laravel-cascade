<?php

namespace Nifrim\LaravelCascade\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\File;
use Nifrim\LaravelCascade\Tests\Models\DummyDestination;
use Nifrim\LaravelCascade\Tests\Models\DummyFlight;
use Nifrim\LaravelCascade\Tests\Models\DummyUser;

abstract class TestCase extends Orchestra
{
    protected static array $destinationData;
    protected DummyDestination $destination;
    protected FakerGenerator $faker;

    /**
     * @var TTemporalConfig
     */
    protected static $temporalConfig;

    /**
     * @inheritDoc
     */
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        config()->set('database.default', 'testing');

        // Make faker
        $this->faker = FakerFactory::create();
        static::$destinationData = [
            'title' => $this->faker->name()
        ];

        // Ensure database file exists
        $sqliteFile = __DIR__ . DIRECTORY_SEPARATOR . 'testing.sqlite';
        if (!File::exists($sqliteFile)) {
            touch($sqliteFile);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::migrateDirection('up');

        $this->destination = DummyDestination::query()
            ->firstOrCreate(
                static::$destinationData,
                static::$destinationData,
            );
    }

    public function tearDown(): void
    {
        static::migrateDirection('down');
        parent::tearDown();
    }

    /**
     * @param up|down $direction
     */
    public static function migrateDirection(string $direction = 'up'): void
    {
        foreach (File::allFiles(__DIR__ . '/Database/Migrations') as $migration) {
            if ($migration->getExtension() === 'php') {
                (include $migration->getRealPath())->{$direction}();
            }
        }
    }

    public function destinationData()
    {
        return [
            'title' => $this->faker->name(),
        ];
    }

    public function flightData()
    {
        return [
            'title' => $this->faker->name(),
        ];
    }

    public function userWithProfileData()
    {
        return [
            'code' => $this->faker->company(),
            'profile' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ]
        ];
    }

    public function createDummyUser(?array $data = null, ?bool $forgeOnly = false): DummyUser
    {
        if (!$data) {
            $data = $this->userWithProfileData();
        }

        return $forgeOnly ? new DummyUser($data) : DummyUser::create($data);
    }

    public function createDummyFlight(?array $data = null, ?bool $forgeOnly = false): DummyFlight
    {
        if (!$data) {
            $data = $this->flightData();
        }

        return $forgeOnly ? new DummyFlight($data) : DummyFlight::create($data);
    }

    public function createDummyDestination(?array $data = null, ?bool $forgeOnly = false): DummyDestination
    {
        if (!$data) {
            $data = static::$destinationData;
        }

        return $forgeOnly ? new DummyDestination($data) : DummyDestination::create($data);
    }
}
