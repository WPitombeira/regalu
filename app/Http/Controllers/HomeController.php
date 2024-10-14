<?php

namespace App\Http\Controllers;

use App\Livewire\Home\Login;
use Illuminate\Http\Request;

class HomeController extends Controller {
    public function index() {
        return view('home');
    }

    public function login() {
        return (new Login())->render();
    }
}
