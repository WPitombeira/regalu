<?php

namespace App\Models;

use Database\Factories\NewsletterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Newsletter extends Model
{
    /** @use HasFactory<NewsletterFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
    ];
}
