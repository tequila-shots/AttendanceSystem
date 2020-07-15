=> Installation Guide

$copy .env.example .env
$composer install
$php artisan jwt:generate
$php artisan migrate

-> add laravel.log and supervisor.log files in storage/logs
------------------------------------------------------------
=> Put Values in .env file

PERCENTAGE_CRITERIA
and other redis (if want to use; beanstalk can also be used), database and mail related values

 ---------------------
| ONLY FOR PRODUCTION |
 ---------------------
APP_ENV=PRODUCTION
APP_DEBUG=false
SENTRY_LARAVEL_DSN (if want to use Sentry for exception reporting)


----------------------------------------------------------
=> For Supervisor (PRODUCTION and UBUNTU only)

-> config file (change the directory path and stdout_logfile path)

[program:laravel-worker-attendance]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work --queue="high"
directory=/mnt/c/xampp/htdocs/laravel/AttendanceSystem
autostart=true
autorestart=true
;user=forge
numprocs=8
redirect_stderr=true
stdout_logfile=/mnt/c/xampp/htdocs/laravel/AttendanceSystem/storage/logs/supervisor.log
stopwaitsecs=3600

-> Run Commands : 
$sudo service supervisor start
$sudo supervisorctl reread
$sudo supervisorctl update
$sudo supervisorctl start laravel-worker-attendance:*
--------------------------------------------------------------------------------------------
=> To handle Job Failing on other way then change logic in AppServiceProvider.php\boot()
---------------------------------------------------------------------------------------------
=> To Start Application : 

$php artisan service
$php artisan queue:work --queue=high (in separate terminal) (only if supervisor is not used)
----------------------------------------------------------------------------------------------