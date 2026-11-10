<?php

namespace App\Http\Controllers;

use App\Livewire\Home\Login;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home');
    }

    public function login(): View
    {
        return (new Login)->render();
    }
}
