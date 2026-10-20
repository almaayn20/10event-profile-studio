<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Runs engine/studio_pdf.py: the job goes in as JSON, the result comes back as JSON. No shell string is ever built.
 *   page     HTML → one PDF page (Chrome) and its picture
 *   compose  the profile with pages inserted, replaced or removed (pypdf)
 *   inspect  page count and size of a profile, and a small picture of every page
 */
class PdfEngine
{
    public function run(string $command, array $job, string $workDir): array
    {
        File::ensureDirectoryExists($workDir);
        $in = $workDir.'/_job_'.$command.'.json';
        $out = $workDir.'/_result_'.$command.'.json';
        @unlink($out);
        $job['chrome'] ??= config('studio.chrome');
        File::put($in, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $process = new Process([config('studio.python'), config('studio.engine'), $command, '--in', $in, '--out', $out], $workDir, [
            'CHROME_BIN' => config('studio.chrome'),
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ], null, config('studio.timeout'));
        try {
            $process->run();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['code' => 'engine_crash', 'detail' => mb_substr($e->getMessage(), 0, 2000)]];
        }
        if (! is_file($out)) {
            logger()->error('studio_pdf produced no result', ['command' => $command, 'stderr' => mb_substr($process->getErrorOutput(), -2000)]);

            return ['ok' => false, 'error' => ['code' => 'engine_crash', 'detail' => mb_substr($process->getErrorOutput().$process->getOutput(), -2000)]];
        }
        $result = json_decode(File::get($out), true) ?: ['ok' => false, 'error' => ['code' => 'engine_bad_output']];
        if (! ($result['ok'] ?? false)) {
            logger()->warning('studio_pdf failed', ['command' => $command, 'error' => $result['error'] ?? null]);
        }

        return $result;
    }
}
