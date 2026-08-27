<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Absorbs an accidental double submission without rejecting a legitimate repeat.
 *
 * A double-clicked button and a browser retry both send the same payload twice
 * within a second or two. Two colleagues genuinely reporting the same fault
 * minutes apart are not duplicates and must both get a ticket — so the guard is
 * a short window keyed on the submitter and the content, not a permanent
 * uniqueness rule.
 */
trait PreventsDuplicateSubmissions
{
    /**
     * Seconds a submission fingerprint is remembered for.
     */
    protected int $duplicateSubmissionWindow = 10;

    /**
     * Return the id of a submission made moments ago with identical content, or
     * null if this is the first time we have seen it.
     *
     * @param  array<int, mixed>  $parts  values that identify the submission
     */
    protected function recentDuplicateOf(string $scope, array $parts): ?int
    {
        return Cache::get($this->duplicateSubmissionKey($scope, $parts));
    }

    /**
     * Remember a submission so an immediate repeat can be recognised.
     *
     * @param  array<int, mixed>  $parts
     */
    protected function rememberSubmission(string $scope, array $parts, int $id): void
    {
        Cache::put(
            $this->duplicateSubmissionKey($scope, $parts),
            $id,
            $this->duplicateSubmissionWindow,
        );
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function duplicateSubmissionKey(string $scope, array $parts): string
    {
        $fingerprint = md5(implode('|', array_map(
            fn ($p) => is_scalar($p) ? (string) $p : json_encode($p),
            $parts,
        )));

        return sprintf(
            'dup:%s:%s:%s',
            $scope,
            session('current_tenant_id') ?? 'none',
            $fingerprint,
        );
    }
}
