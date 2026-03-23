<?php

namespace App\View\Components;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use App\Services\PlanService;
use Illuminate\View\Component;
use Illuminate\View\View;

class ClientPortalLayout extends Component
{
    public bool $hasKnowledgeBase;

    public function __construct(
        public Tenant $tenant,
    ) {
        $this->hasKnowledgeBase = app(PlanService::class)->tenantHasFeature($tenant, PlanFeature::KnowledgeBase);
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('client-portal.layout');
    }
}
