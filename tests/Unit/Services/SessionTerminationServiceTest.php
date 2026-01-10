<?php

namespace Tests\Unit\Services;

use App\Services\SessionTerminationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionTerminationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SessionTerminationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SessionTerminationService();
    }

    /**
     * T047: Test session termination for a single user
     */
    public function test_terminates_single_user_sessions(): void
    {
        // Create test sessions
        $userId = 1;
        DB::table('sessions')->insert([
            ['id' => 'session1', 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'payload' => 'payload', 'last_activity' => time()],
            ['id' => 'session2', 'user_id' => $userId, 'ip_address' => '127.0.0.2', 'payload' => 'payload', 'last_activity' => time()],
        ]);

        $deleted = $this->service->terminateUserSessions($userId);

        $this->assertEquals(2, $deleted);
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $userId)->count());
    }

    /**
     * T047: Test session termination returns zero when no sessions exist
     */
    public function test_returns_zero_when_no_sessions_exist(): void
    {
        $deleted = $this->service->terminateUserSessions(999);

        $this->assertEquals(0, $deleted);
    }

    /**
     * T047: Test multiple users session termination
     */
    public function test_terminates_multiple_user_sessions(): void
    {
        // Create test sessions for multiple users
        DB::table('sessions')->insert([
            ['id' => 'session1', 'user_id' => 1, 'ip_address' => '127.0.0.1', 'payload' => 'payload', 'last_activity' => time()],
            ['id' => 'session2', 'user_id' => 2, 'ip_address' => '127.0.0.2', 'payload' => 'payload', 'last_activity' => time()],
            ['id' => 'session3', 'user_id' => 3, 'ip_address' => '127.0.0.3', 'payload' => 'payload', 'last_activity' => time()],
        ]);

        $deleted = $this->service->terminateMultipleUserSessions([1, 2]);

        $this->assertEquals(2, $deleted);
        $this->assertEquals(0, DB::table('sessions')->where('user_id', 1)->count());
        $this->assertEquals(0, DB::table('sessions')->where('user_id', 2)->count());
        $this->assertEquals(1, DB::table('sessions')->where('user_id', 3)->count());
    }

    /**
     * T047: Test empty user IDs array returns zero
     */
    public function test_returns_zero_when_empty_user_ids(): void
    {
        $deleted = $this->service->terminateMultipleUserSessions([]);

        $this->assertEquals(0, $deleted);
    }
}
