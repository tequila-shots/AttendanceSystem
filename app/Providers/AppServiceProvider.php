<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Queue::failing(function (JobFailed $event) {
            Log::info(json_encode($event['connectionName']));
            Log::info((unserialize((json_decode($event->job->getRawBody(), true))['data']['command']))->queue);
            Log::info($event->exception->getMessageStack());
            $subject = "Student Alert Mail Job Failed";
            Mail::send(
                'email.failed_job',
                [],
                function ($mail) use ($subject) {
                    $mail->from(env('MAIL_USERNAME'), env('MAIL_PASSWORD'));
                    $mail->to(env('SYS_ADMIN_MAIL'), env('SYS_ADMIN_NAME'));
                    $mail->subject($subject);
                }
            );
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
