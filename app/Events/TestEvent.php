<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invoiceData;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($invoiceData)
    {
        $this->invoiceData = $invoiceData;
        //so that we can do $event->invoiceData, this holds all data sent to the event
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
