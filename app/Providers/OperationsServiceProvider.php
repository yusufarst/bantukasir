<?php

namespace App\Providers;

use App\Console\LocalDiskSpace;
use App\Domain\Operations\Backup\DiskSpace;
use App\Domain\Operations\DatabaseHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The Operations module's registrations (STACK_AND_MODULES section 2;
 * TASK-007), in bootstrap/providers.php: the database check of the health
 * route RH-01 listens to the framework's DiagnosingHealth event. The
 * framework discovers listeners only in app/Listeners, so the module's
 * listener is registered here (STK-22). TASK-013 binds the measure of the
 * backup's free space.
 */
final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DiskSpace::class, LocalDiskSpace::class);
    }

    public function boot(): void
    {
        Event::listen(DiagnosingHealth::class, DatabaseHealth::class);
    }
}
