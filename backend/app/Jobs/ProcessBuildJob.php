<?php

namespace App\Jobs;

use App\Models\Build;
use App\Services\CapacitorBuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Build $build
    ) {}

    public function handle(): void
    {
        $this->build->update(['status' => 'building']);

        try {
            $templatePath = config('build.template_path', 'build-templates/webview-app');
            $outputPath = config('build.output_path', 'builds');

            $service = new CapacitorBuildService($templatePath, $outputPath);
            $service->build($this->build);

            $this->build->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->build->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
