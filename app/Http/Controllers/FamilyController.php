<?php

namespace App\Http\Controllers;

use App\Models\Family;

class FamilyController extends Controller {
    public function index() {
        return view('livewire.family.index');
    }

    public function create() {
        return view('livewire.family.create');
    }

    public function show(Family $family) {
        return view('livewire.family.roster', ['family' => $family]);
    }

    public function settings(Family $family) {
        return view('livewire.family.settings', ['family' => $family]);
    }

    public function joinForm() {
        return view('livewire.family.join');
    }
}
