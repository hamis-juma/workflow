<?php

namespace HamisJuma\Workflow\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use HamisJuma\Workflow\Models\WfTrack;

class RejectWorkflow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $wfTrack;

    public $level;

    /**
     * RejectWorkflow constructor.
     * @param WfTrack $wfTrack
     */
    public function __construct(WfTrack $wfTrack, $level)
    {
        $this->wfTrack = $wfTrack;
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