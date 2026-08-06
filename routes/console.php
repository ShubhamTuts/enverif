<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('enverif:schedules:due')->everyMinute()->withoutOverlapping();
