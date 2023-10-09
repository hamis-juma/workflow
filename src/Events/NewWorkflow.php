<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewWorkflow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $input = [];

    public $par = [];

    public $extra = [];

    /**
     * NewWorkflow constructor.
     * @param array $input
     * @param array $par
     * @param array $extra
     */
    public function __construct(array $input, array $par = [], array $extra = [])
    {
        $this->input = $input;
        $this->par = $par;
        $this->extra = $extra;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
