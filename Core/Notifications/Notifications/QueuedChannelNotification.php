<?php

namespace Core\Notifications\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued variant of ChannelNotification for asynchronous delivery.
 */
class QueuedChannelNotification extends ChannelNotification implements ShouldQueue
{
}
