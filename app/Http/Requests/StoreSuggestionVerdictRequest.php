<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AiSuggestionVerdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The engineer's verdict on one suggestion.
 *
 * Note what is NOT here: no `ticket_id` and no `analysis_id`. The analysis is route-model
 * bound (BelongsToTenant, so another workspace's id is a 404) and the ticket is read off
 * the analysis. There is therefore no foreign key arriving from the request and nothing
 * for `ScopesRulesToTenant` to scope — stated deliberately, because the absence of
 * `tenantExists()` in a request class is otherwise the first thing a reviewer flags.
 *
 * `applied_value` is hashed and discarded. The engineer's final text already lives on
 * the ticket; a second copy in an analytics table is another thing to scope and delete.
 */
class StoreSuggestionVerdictRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission, ticket access and the plan/opt-in gates are the controller's and
        // the route's; there is nothing request-specific to authorise here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suggestion_type' => ['required', 'string', Rule::in(AiSuggestionVerdict::TYPES)],
            'verdict' => ['required', 'string', Rule::in(AiSuggestionVerdict::VERDICTS)],
            'applied_value' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
