<?php

namespace App\Http\Controllers;

use App\Models\AmigoSecretoEvent;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class AmigoSecretoController extends Controller
{
    public function index(): View
    {
        return view('livewire.amigo-secreto.index');
    }

    public function create(): View
    {
        return view('livewire.amigo-secreto.create');
    }

    #[Authorize('view', 'event')]
    public function show(AmigoSecretoEvent $event): View
    {
        return view('livewire.amigo-secreto.detail', ['event' => $event]);
    }

    #[Authorize('manage', 'event')]
    public function participants(AmigoSecretoEvent $event): View
    {
        return view('livewire.amigo-secreto.participants', ['event' => $event]);
    }

    #[Authorize('manage', 'event')]
    public function exclusions(AmigoSecretoEvent $event): View
    {
        return view('livewire.amigo-secreto.exclusions', ['event' => $event]);
    }

    #[Authorize('view', 'event')]
    public function drawPage(AmigoSecretoEvent $event): View
    {
        return view('livewire.amigo-secreto.draw', ['event' => $event]);
    }

    public function joinForm(?string $code = null): View
    {
        return view('livewire.amigo-secreto.join', ['code' => $code]);
    }
}
