<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('notifications'),
            'Expected table [notifications] to exist.',
        );
    }

    public function test_notifications_have_expected_columns(): void
    {
        foreach ([
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('notifications', $column),
                "Expected notifications.{$column} to exist.",
            );
        }
    }

    public function test_notifications_table_is_distinct_from_admin_notifications(): void
    {
        $this->assertTrue(Schema::hasTable('admin_notifications'));
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertFalse(
            Schema::hasColumn('notifications', 'dedupe_key'),
            'Expected Laravel notifications table not to use admin_notifications columns.',
        );
    }
}
