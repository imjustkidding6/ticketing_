<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AI\ResolutionWriterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The engineer's rough notes, on the way to a resolution write-up.
 *
 * Note what is NOT here: no foreign key of any kind. `notes` and `mode` are not ids, and
 * the `{ticket}` route binding is a `BelongsToTenant` model, so another workspace's id is
 * already a 404. There is therefore nothing for `ScopesRulesToTenant` to scope — stated
 * deliberately, because the absence of `tenantExists()` in a request class is otherwise
 * the first thing a reviewer flags.
 *
 * `min:40` is a refusal, not a nicety: below it the model has nothing to rewrite and
 * invents the work instead. It 422s here, so nothing is sent and no run is recorded.
 * `ResolutionWriterService::write()` enforces the same floor for a direct caller.
 */
class StoreResolutionDraftRequest extends FormRequest
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
            'notes' => [
                'required',
                'string',
                'min:'.(int) config('ai.resolution.notes_min_chars', 40),
                'max:'.(int) config('ai.resolution.notes_chars', 4000),
            ],
            // A2-1 produces one mode. A2-2 adds `acknowledgement` on this same endpoint;
            // accepting the word before the mode exists would offer a control that
            // returns nothing, which is the dead-control rule in reverse.
            'mode' => ['nullable', 'string', Rule::in([ResolutionWriterService::MODE_RESOLUTION])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.min' => __('Write a few lines about what you did — the AI rewrites your notes, it cannot invent the work.'),
        ];
    }
}
