<?php

namespace App\Http\Controllers;

use App\Models\AmigoSecretoEvent;
use Illuminate\View\View;

class AmigoSecretoController extends Controller {
    public function index(): View {
        return view('livewire.amigo-secreto.index');
    }

    public function create(): View {
        return view('livewire.amigo-secreto.create');
    }

    public function show(AmigoSecretoEvent $event): View {
        return view('livewire.amigo-secreto.detail', ['event' => $event]);
    }

    public function participants(AmigoSecretoEvent $event): View {
        return view('livewire.amigo-secreto.participants', ['event' => $event]);
    }

    public function exclusions(AmigoSecretoEvent $event): View {
        return view('livewire.amigo-secreto.exclusions', ['event' => $event]);
    }

    public function drawPage(AmigoSecretoEvent $event): View {
        return view('livewire.amigo-secreto.draw', ['event' => $event]);
    }

    public function joinForm(?string $code = null): View {
        return view('livewire.amigo-secreto.join', ['code' => $code]);
    }
}
