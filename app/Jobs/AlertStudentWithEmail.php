<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Exception;

class AlertStudentWithEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Student object
     * @var mixed
     */
    protected $student;

    /**
     * Statistics of particular student
     * @var array 
     */
    protected $stats;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $retryAfter = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($student, $stats)
    {
        $this->student = $student;
        $this->stats = $stats;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        //return [new ClassName];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        Log::info("Job Processing started | Attempt : " . $this->attempts());
        $subject = "Low Attendance";
        $mailer->send(
            'email.attendance',
            ['name' => $this->student['name'], 'prn' => $this->student['prn'], 'percentage' => $this->stats['percentage'], 'subject_name' => $this->stats['subject_name']],
            function ($mail) use ($subject) {
                $mail->from(env('MAIL_USERNAME'), env('MAIL_PASSWORD'));
                $mail->to($this->student['email'], $this->student['name']);
                $mail->subject($subject);
            }
        );
        Log::info("Job processed");
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        Log::info("From Exception : " . $exception->getMessage());
        Log::info("From Exception : " . $exception->getMessageStack() . " With : " . $this->attempts());
    }
}
