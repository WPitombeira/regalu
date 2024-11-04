<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;

class NewsletterController extends Controller {
    public static function register(string $email): bool {
        $newsletter = new Newsletter();

        if (empty($email) || $newsletter->where('email', $email)->exists())
            return false;

        $newsletter->email = $email;
        return $newsletter->save();
    }
}
