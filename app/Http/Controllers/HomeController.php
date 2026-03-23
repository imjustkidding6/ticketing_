<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $plans = Plan::query()->active()->get();

        return view('welcome', compact('plans'));
    }
}
