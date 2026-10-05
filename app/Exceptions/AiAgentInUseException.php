<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI agent still assigned to at least one department is asked to delete.
 *
 * Its own skill pivots are not a blocker — those are the agent's private
 * configuration and cascade away with it. A department assignment is somebody
 * else's configuration, so removing it has to be a deliberate act.
 */
class AiAgentInUseException extends RuntimeException
{
    /**
     * @param  list<string>  $blockers  Names of the departments still holding the agent.
     */
    public function __construct(string $agentName, public readonly array $blockers)
    {
        parent::__construct(sprintf(
            'The AI agent "%s" is still assigned to %s and cannot be deleted.',
            $agentName,
            implode(', ', $blockers),
        ));
    }
}
