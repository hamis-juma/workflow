<?php

namespace HamisJuma\Workflow\Jobs;

use HamisJuma\Workflow\Notifications\WorkflowNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WorkflowSendEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $users;
    protected $email_resource;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($users, $email_resource)
    {
        //
        $this->users =  $users;
        $this->email_resource =  $email_resource;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        // $this->users->notify(new WorkflowNotification($this->email_resource));

        try {
            $this->users->notify(new WorkflowNotification($this->email_resource));
        } catch (\Swift_TransportException $exception) {
            // Log the exception for later analysis
            \Log::error('Error sending email: ' . $exception->getMessage());

            // Laravel will automatically retry the job if it's within the maximum attempts
            // throw $exception;
        }
    }
}
