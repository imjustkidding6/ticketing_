<?php

namespace App\Services\AI;

/**
 * What one run of AiAgentCatalogueInstaller did, or would have done.
 *
 * A value object rather than an array because three callers read it — the command,
 * the db:seed wrapper and (P3) the settings screen — and an untyped array is three
 * chances to destructure it differently.
 */
class CatalogueInstallSummary
{
    /**
     * @param  list<string>  $missingDepartmentCodes  department codes the catalogue expects and this
     *                                                workspace does not have, so those agents were
     *                                                created but left unassigned
     */
    public function __construct(
        public readonly int $skillsCreated = 0,
        public readonly int $agentsCreated = 0,
        public readonly int $skillPivotsCreated = 0,
        public readonly int $assignmentsCreated = 0,
        public readonly int $assignmentsSkipped = 0,
        public readonly array $missingDepartmentCodes = [],
        public readonly bool $dryRun = false,
    ) {}

    /**
     * True when the workspace already held everything the catalogue installs.
     */
    public function createdNothing(): bool
    {
        return $this->skillsCreated === 0
            && $this->agentsCreated === 0
            && $this->skillPivotsCreated === 0
            && $this->assignmentsCreated === 0;
    }

    /**
     * One line for a console run or a flashed message.
     */
    public function describe(): string
    {
        return sprintf(
            '%s %d skill(s), %d agent(s), %d agent-skill link(s), %d department assignment(s).',
            $this->dryRun ? 'Would create' : 'Created',
            $this->skillsCreated,
            $this->agentsCreated,
            $this->skillPivotsCreated,
            $this->assignmentsCreated,
        );
    }
}
