# Jobs

Create a job with `php jeep make:job DailyReport`. Implement `JeePHP\Jobs\Job::handle()`, then run it with `php jeep job:run DailyReport`.

For recurring work, schedule that command with Linux cron or Windows Task Scheduler. This first runner executes a job immediately; it does not add a queue server or retry system.
