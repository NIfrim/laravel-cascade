<?php

namespace Nifrim\LaravelCascade\Tests\Feature;

use Carbon\Carbon;
use Nifrim\LaravelCascade\Tests\TestCase;
use Nifrim\LaravelCascade\Tests\Models\DummyDestination;
use Nifrim\LaravelCascade\Tests\Models\DummyUser;
use PHPUnit\Framework\Attributes\Group;

#[Group('laravel-cascade')]
class CascadeWithHistoricalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::now());
    }

    public function test_it_forges_with_and_without_associations()
    {
        // Arrange: A user with a profile and multiple flights.
        $data = [
            ...$this->userWithProfileData(),
            'flights' => [
                $this->flightData(),
                $this->flightData(),
            ],
        ];

        // Act: Forge the user model.
        $userWithoutAssociations = $this->createDummyUser(['code' => 'test_single_forge'], true);
        $userWithAssociations = $this->createDummyUser($data, true);

        // Assert: Check that the profile and flights relations are as expected.
        $this->assertNotNull($userWithoutAssociations);
        $this->assertEquals('test_single_forge', $userWithoutAssociations->code);
        $this->assertNotNull($userWithAssociations);
        $this->assertEquals($data['code'], $userWithAssociations->code);
        $this->assertNotNull($userWithAssociations->profile, 'Profile relation should be set');
        $this->assertNotNull($userWithAssociations->flights, 'Flights relation should be set');
        $this->assertCount(2, $userWithAssociations->flights, 'There should be 2 flights');
        $this->assertEquals($data['flights'][0]['title'], $userWithAssociations->flights->first()->title);
    }

    public function test_it_saves_with_and_without_associations()
    {
        // Arrange: Create a user with a profile and articles.
        $data = [
            ...$this->userWithProfileData(),
            'flights' => [
                [
                    ...$this->flightData(),
                    'destination_id' => $this->destination->getKey(),
                ]
            ],
        ];

        // Act: Save the users
        $userWithoutAssociations = $this->createDummyUser(['code' => 'test_single_create']);
        $userWithAssociations = $this->createDummyUser($data);

        // Assert: Check persisted data is as expected
        $userWithoutAssociations = DummyUser::find($userWithoutAssociations->getKey());
        $userWithAssociations = DummyUser::with(['profile', 'flights.destination'])->find($userWithAssociations->getKey());
        $expectedFlightTitle = $data['flights'][0]['title'];
        $this->assertNotNull($userWithoutAssociations);
        $this->assertEquals('test_single_create', $userWithoutAssociations->code);
        $this->assertNotNull($userWithAssociations);
        $this->assertEquals($data['code'], $userWithAssociations->code);
        $this->assertNotNull($userWithAssociations->profile);
        $this->assertEquals($data['profile']['name'], $userWithAssociations->profile->name);
        $this->assertNotNull($userWithAssociations->flights);
        $this->assertCount(count($data['flights']), $userWithAssociations->flights);
        $this->assertNotNull($userWithAssociations->flights->first()->destination);
        $this->assertEquals(DummyDestination::class, $userWithAssociations->flights->firstWhere('title', $expectedFlightTitle)->destination::class);
    }

    public function test_it_updates_self_and_associations()
    {
        // Arrange: User with flight and destination
        $destinationData = $this->destinationData();
        $userData = $this->userWithProfileData();
        $updateData = [
            'flights' => [
                [
                    ...$this->flightData(),
                    'destination_id' => $this->destination->getKey(),
                ]
            ]
        ];

        $user = $this->createDummyUser($userData);
        $destination = $this->createDummyDestination($destinationData);
        $firstTimestamp = $user->getUpdatedAt();

        // Act: Update the user
        Carbon::setTestNow(Carbon::now()->addMinute());
        $user->update($updateData);
        $flight = $user->flights()->firstWhere('title', $updateData['flights'][0]['title']);
        $secondTimestamp = $user->getUpdatedAt();

        // Act: Update the flight destination
        Carbon::setTestNow(Carbon::now()->addMinute());
        $user->update([
            'flights' => [
                'id' => $flight->getKey(),
                'destination_id' => $destination->getKey(),
            ]
        ]);

        // Assert: Verify the persisted data
        $savedUser = DummyUser::with(['profile', 'flights.destination'])->find($user->getKey());
        $this->assertNotNull($savedUser);
        $this->assertEquals($userData['code'], $savedUser->code);
        $this->assertNotNull($savedUser->profile);
        $this->assertEquals($userData['profile']['name'], $savedUser->profile->name);
        $this->assertNotNull($savedUser->flights);
        $this->assertCount(1, $savedUser->flights);
        $this->assertNotNull($savedUser->flights->first()->destination);
        $this->assertEquals($destination->title, $savedUser->flights->first()->destination->title);

        // Assert: Verify historic data exists
        $historicRecords = DummyUser::onlyTrashed()
            ->with([
                'profile' => fn($query) => $query->onlyTrashed(),
                'flights' => fn($query) => $query
                    ->with([
                        'destination' => fn($query) => $query->onlyTrashed()->withDefault(null)
                    ])
                    ->onlyTrashed()->onlyTrashedPivots(),
            ])
            ->where($user->getKeyName(), $user->getKey())->get();

        // Assert: Verify first revision record tree is as expected
        $firstRevision = $historicRecords->firstWhere($user->getUpdatedAtColumn(), $firstTimestamp);
        $this->assertNotNull($firstRevision);
        $this->assertEquals($userData['code'], $firstRevision->code);
        $this->assertNotNull($firstRevision->profile);
        $this->assertEquals($userData['profile']['name'], $firstRevision->profile->name);
        $this->assertNotNull($firstRevision->flights);
        $this->assertCount(0, $firstRevision->flights);

        // Assert: Verify second revision record tree is as expected
        $secondRevision = $historicRecords->firstWhere($user->getUpdatedAtColumn(), $secondTimestamp);
        $this->assertNotNull($secondRevision);
        $this->assertEquals($userData['code'], $secondRevision->code);
        $this->assertNotNull($secondRevision->profile);
        $this->assertEquals($userData['profile']['name'], $secondRevision->profile->name);
        $this->assertNotNull($secondRevision->flights);
        $this->assertCount(1, $secondRevision->flights);
        $this->assertNotNull($secondRevision->flights->first()->destination);
        $this->assertEquals($secondRevision->flights->first()->destination->title, $flight->destination->title);
    }

    public function test_can_unlink_associations_via_update()
    {
        // Arrange: Data for a user with a profile and articles
        $data = [
            ...$this->userWithProfileData(),
            'flights'   => [
                [
                    ...$this->flightData(),
                    'pivot' => ['destination_id' => $this->destination->getKey()],
                ],
            ]
        ];

        // Act: Create the user with the profile
        $user = DummyUser::create($data);

        // Act: Clear the user flights
        $user->update(['flights' => []]);

        // Assert: Verify that the user and its relations have been persisted.
        $savedUser = DummyUser::with(['profile', 'flights'])->find($user->getKey());
        $this->assertNotNull($savedUser);
        $this->assertEquals($data['code'], $savedUser->code);
        $this->assertNotNull($savedUser->profile);
        $this->assertEquals($data['profile']['name'], $savedUser->profile->name);
        $this->assertNotNull($savedUser->flights);
        $this->assertCount(0, $savedUser->flights);
    }
}
