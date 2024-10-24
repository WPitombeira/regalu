<?php

namespace App\View\Components;

use App\Http\Controllers\UserController;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Card extends Component {
    public function render(): View|Closure|string {
        return view('components.ui.user.card');
    }
}
