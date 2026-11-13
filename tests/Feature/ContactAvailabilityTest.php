<?php

use App\Livewire\Home;
use App\Livewire\Home\Index;
use App\Models\Newsletter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('refuses contact delivery without changing the draft or emitting success', function (string $alias, string $class, string $locale, string $message, string $newsletterEmail) {
    app()->setLocale($locale);
    Mail::fake();
    Bus::fake();
    Notification::fake();
    $this->withoutVite();
    $subscription = Newsletter::factory()->create();
    $original = $subscription->refresh()->getAttributes();

    $component = Livewire::test($alias);
    expect($component->instance())->toBeInstanceOf($class);
    $component->set('email', $newsletterEmail)
        ->set('feedback_name', 'Contact draft')
        ->set('feedback_email', 'draft@example.test')
        ->set('feedback_message', 'Please keep this unsent draft.');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $component->call('registerFeedback')
        ->assertHasErrors(['feedback'])
        ->assertSet('email', $newsletterEmail)
        ->assertSet('feedback_name', 'Contact draft')
        ->assertSet('feedback_email', 'draft@example.test')
        ->assertSet('feedback_message', 'Please keep this unsent draft.')
        ->assertNotDispatched('notification')
        ->assertSeeHtml('role="alert"')
        ->assertSee($message);
    expect($component->errors()->get('feedback'))->toBe([$message]);
    $writes = collect(DB::getQueryLog())->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|create|drop|alter)\b/i', $query['query']) === 1);
    DB::disableQueryLog();

    expect($writes)->toBeEmpty();
    expect($subscription->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('newsletters', 1);
    Mail::assertNothingOutgoing();
    Bus::assertNothingDispatched();
    Notification::assertNothingSent();
})->with([
    'mounted' => ['home.index', Index::class],
    'legacy' => ['home', Home::class],
])->with([
    'English' => ['en', 'Contact delivery is unavailable. Your message has not been sent.'],
    'Portuguese' => ['pt_BR', 'O envio de mensagens está indisponível. Sua mensagem não foi enviada.'],
])->with(['empty newsletter field' => '', 'unrelated newsletter field' => 'newsletter@example.test']);
