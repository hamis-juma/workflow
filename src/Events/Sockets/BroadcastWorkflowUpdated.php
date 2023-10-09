<?php

namespace App\Events\Sockets;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastWorkflowUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resource_id;

    public $wf_module_id;

    public $level;

    /**
     * BroadcastWorkflowUpdated constructor.
     * @param $wf_module_id
     * @param $resource_id
     * @param $level
     */
    public function __construct($wf_module_id, $resource_id, $level)
    {
        $this->wf_module_id = $wf_module_id;
        $this->resource_id = $resource_id;
        $this->level = $level;
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