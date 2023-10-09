<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\Workflow\WfTrack;
use Log;

class ApproveWorkflow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $wfTrack;

    public $input;

    /**
     * ApproveWorkflow constructor.
     * @param WfTrack $wfTrack
     * @param array $input
     */
    public function __construct(WfTrack $wfTrack,  $input = [])
    {
        // Log::info('---------------');
        // Log::info(print_r($wfTrack,true));

        // die;
        $this->wfTrack = $wfTrack;
        $this->input = $input;
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