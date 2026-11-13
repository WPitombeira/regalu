<?php

namespace App\Livewire\Home;

use App\Models\Newsletter;
use App\Services\NewsletterSignupLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        return view('livewire.home.index');
    }

    public string $email = '';

    public bool $newsletterConsent = false;

    #[Locked]
    public bool $newsletterSubmitted = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['email', 'newsletterConsent'], true)) {
            $this->newsletterSubmitted = false;
            $this->resetValidation([$property, 'newsletter']);
        }
    }

    public function registerNewsletter(NewsletterSignupLimiter $limiter): void
    {
        $this->newsletterSubmitted = false;
        $this->resetValidation(['email', 'newsletterConsent', 'newsletter']);
        if (! $limiter->reserve(request()->ip())) {
            $this->addError('newsletter', __('newsletter.throttled'));

            return;
        }

        $this->email = strtolower(trim($this->email));
        $this->validate([
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'newsletterConsent' => ['accepted'],
        ], [
            'email.required' => __('newsletter.email_required'),
            'email.string' => __('newsletter.email_invalid'),
            'email.email' => __('newsletter.email_invalid'),
            'email.max' => __('newsletter.email_max'),
            'newsletterConsent.accepted' => __('newsletter.consent_required'),
        ]);
        try {
            // Preserve legacy mixed-case and inactive records; signup never reactivates them.
            if (! Newsletter::query()->useWritePdo()->whereRaw('LOWER(email) = ?', [$this->email])->exists()) {
                Newsletter::query()->createOrFirst(['email' => $this->email]);
            }
        } catch (QueryException) {
            $this->addError('newsletter', __('newsletter.unavailable'));

            return;
        }

        $this->reset('email', 'newsletterConsent');
        $this->newsletterSubmitted = true;
    }

    public function registerFeedback(): void
    {
        // Delivery is not configured; retain the draft without claiming success.
        $this->addError('feedback', __('contact.unavailable'));
    }

    #[Rule(['required', 'min:5'])]
    public string $feedback_message = '';

    public string $feedback_email = '';

    public string $feedback_name = '';
}
