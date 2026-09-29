<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a skill still attached to at least one AI agent is asked to delete.
 *
 * Raised from the model's `deleting` event rather than a controller, on the
 * TicketService::changeStatus() principle: the rule has to hold for the admin
 * screen, a seeder, tinker and any future API alike. Callers that want a
 * validation message rather than a 500 ask Skill::deletionBlockers() first.
 */
class SkillInUseException extends RuntimeException
{
    /**
     * @param  list<string>  $blockers  Names of the agents still holding the skill.
     */
    public function __construct(string $skillName, public readonly array $blockers)
    {
        parent::__construct(sprintf(
            'The skill "%s" is still assigned to %s and cannot be deleted.',
            $skillName,
            implode(', ', $blockers),
        ));
    }
}
