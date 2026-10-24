<?php

namespace Tests\Harness\Race;

/**
 * The line protocol between a race test and its workers (TS-20): each line is
 * a word and its payload. From the worker: READY <backend pid>, PASSED <point>,
 * REACHED <point>, DONE <json result>, FAILED <json failure>. To the worker:
 * RUN, after READY, to start the command; GO or ABORT, after a REACHED.
 */
final class Protocol
{
    /**
     * @param  resource  $output
     */
    public static function send($output, string $word, string $payload): void
    {
        fwrite($output, $word.' '.str_replace("\n", ' ', $payload)."\n");
        fflush($output);
    }
}
