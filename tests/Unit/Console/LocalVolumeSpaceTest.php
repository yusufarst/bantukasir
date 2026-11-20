<?php

namespace Tests\Unit\Console;

use App\Console\LocalVolumeSpace;
use App\Domain\Operations\VolumeSpace;
use Tests\TestCase;

/**
 * The measure of the file volume behind "Ruang penyimpanan" (AC-039-01; OPERATIONS_RUNBOOK section 5; TASK-039
 * review R-04): a folder that exists gives whole numbers with the free space within the total; a folder that
 * does not exist gives no measurement, which the status shows as "Belum Terverifikasi"; and the application
 * binds this measure.
 */
final class LocalVolumeSpaceTest extends TestCase
{
    public function test_an_existing_folder_is_measured_in_whole_bytes_within_its_total(): void
    {
        $measured = (new LocalVolumeSpace)->measure(sys_get_temp_dir());

        $this->assertNotNull($measured);
        $this->assertGreaterThan(0, $measured['total']);
        $this->assertGreaterThanOrEqual(0, $measured['free']);
        $this->assertLessThanOrEqual($measured['total'], $measured['free']);
    }

    public function test_a_folder_that_does_not_exist_is_no_measurement(): void
    {
        $this->assertNull((new LocalVolumeSpace)->measure(sys_get_temp_dir().'/bantukasir-no-such-folder-'.bin2hex(random_bytes(8))));
        $this->assertNull((new LocalVolumeSpace)->measure(__FILE__));
    }

    public function test_the_application_measures_the_file_volume_with_it(): void
    {
        $this->assertInstanceOf(LocalVolumeSpace::class, $this->app->make(VolumeSpace::class));
    }
}
