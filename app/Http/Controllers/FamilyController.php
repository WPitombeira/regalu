<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class FamilyController extends Controller
{
    public function index(): View
    {
        return view('livewire.family.index');
    }

    public function create(): View
    {
        return view('livewire.family.create');
    }

    #[Authorize('view', 'family')]
    public function show(Family $family): View
    {
        return view('livewire.family.roster', ['family' => $family]);
    }

    #[Authorize('manage', 'family')]
    public function settings(Family $family): View
    {
        return view('livewire.family.settings', ['family' => $family]);
    }

    public function joinForm(): View
    {
        return view('livewire.family.join');
    }
}
