<?php

namespace Tests\Feature\Tickets;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_tables_exist(): void
    {
        foreach (['ticket_categories', 'tickets', 'ticket_messages'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_ticket_categories_have_expected_columns(): void
    {
        foreach ([
            'id',
            'name',
            'slug',
            'description',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('ticket_categories', $column),
                "Expected ticket_categories.{$column} to exist.",
            );
        }
    }

    public function test_tickets_have_expected_columns(): void
    {
        foreach ([
            'id',
            'ticket_number',
            'client_id',
            'category_id',
            'service_id',
            'order_id',
            'invoice_id',
            'subject',
            'status',
            'priority',
            'assigned_to',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('tickets', $column),
                "Expected tickets.{$column} to exist.",
            );
        }
    }

    public function test_ticket_messages_have_expected_columns(): void
    {
        foreach ([
            'id',
            'ticket_id',
            'user_id',
            'message',
            'attachments',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('ticket_messages', $column),
                "Expected ticket_messages.{$column} to exist.",
            );
        }

        $this->assertFalse(
            Schema::hasColumn('ticket_messages', 'updated_at'),
            'Expected ticket_messages to be append-only without updated_at.',
        );

        $this->assertFalse(
            Schema::hasColumn('ticket_messages', 'deleted_at'),
            'Expected ticket_messages to not use soft deletes.',
        );
    }
}
