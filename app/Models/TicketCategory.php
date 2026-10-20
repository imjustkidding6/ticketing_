<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use Database\Factories\TicketCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    /** @use HasFactory<TicketCategoryFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'department_id',
        'name',
        'description',
        'color',
        'is_active',
        'is_technical',
        'asks_impact',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_technical' => 'boolean',
            'asks_impact' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Which halves of the declared detail set (App\Support\TicketTechnicalFields) does a
     * ticket in this category get asked for?
     *
     * THE one applicability resolver in the application, and the only reader of these two
     * columns. It replaces the pair that lived on Department: the workspace owner's
     * instruction is that these questions belong to the category, so a department flag is
     * not a fallback — two sources of truth for one question drift within a month.
     *
     * `tenant_id` is stated EXPLICITLY with the global TenantScope bypassed, because this
     * is reached from a FormRequest (where the session's scope would in fact apply) AND
     * from the queued intake analysis and the draft service, where TenantScope applies no
     * predicate at all. An end-to-end test on a session-bearing surface cannot prove that
     * predicate — the model's own scope masks its absence — so it is stated rather than
     * inherited, and a foreign category id yields the no-category answer rather than
     * another workspace's configuration. One query for both flags, so the two halves can
     * never be resolved against different rows.
     *
     * A NULL, MISSING OR FOREIGN CATEGORY ANSWERS FALSE TO BOTH — and this deliberately
     * reverses the direction the deleted Department::asksImpactFor() failed closed in,
     * which kept the impact pair ASKED when no department was chosen. Do not "fix" the
     * inconsistency back: the questions are now a property of a category, so a ticket that
     * was never put in a category has not been placed in a bucket that asks them, and
     * inventing a requirement from an absent row is how a form asks for an environment
     * nobody can name. The cost is real and accepted: a ticket with no configured category
     * sends the priority rubric neither the breadth of impact nor the existence of a
     * workaround, so `critical` is not reachable for it and the priority falls back to the
     * model's reading of the prose. The category form states that cost.
     *
     * @return array{technical: bool, asks_impact: bool}
     */
    public static function detailFlagsFor(int $tenantId, ?int $categoryId): array
    {
        $absent = ['technical' => false, 'asks_impact' => false];

        if ($categoryId === null) {
            return $absent;
        }

        $row = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($categoryId)
            ->first(['is_technical', 'asks_impact']);

        if ($row === null) {
            return $absent;
        }

        return [
            'technical' => (bool) $row->is_technical,
            'asks_impact' => (bool) $row->asks_impact,
        ];
    }

    /**
     * Get the department this category belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope a query to only include active categories.
     *
     * @param  Builder<TicketCategory>  $query
     * @return Builder<TicketCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     *
     * @param  Builder<TicketCategory>  $query
     * @return Builder<TicketCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the tickets for this category.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
