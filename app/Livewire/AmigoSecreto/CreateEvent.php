<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class CreateEvent extends Component
{
    #[Rule(['required', 'string', 'min:3', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['required', 'in:CHRISTMAS,BIRTHDAY,WEDDING,CASUAL'])]
    public string $event_type = 'CASUAL';

    #[Rule(['required', 'in:NAMED,ANONYMOUS_UNTIL_REVEAL'])]
    public string $recipient_visibility = 'NAMED';

    #[Rule]
    public ?string $budget_min = null;

    #[Rule]
    public ?string $budget_max = null;

    #[Rule(['nullable', 'date', 'after_or_equal:today'])]
    public ?string $event_date = null;

    #[Rule(['required_if:recipient_visibility,ANONYMOUS_UNTIL_REVEAL', 'nullable', 'date', 'after_or_equal:today'])]
    public ?string $reveal_date = null;

    #[Rule(['nullable', 'exists:families,id'])]
    public ?int $family_id = null;

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'budget_min' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'budget_max' => [
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99',
                ...(! blank($this->budget_min) ? ['gte:budget_min'] : []),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'budget_min' => __('event_budget.minimum'),
            'budget_max' => __('event_budget.maximum'),
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        $messages = ['budget_max.gte' => __('event_budget.ordered')];

        foreach (['budget_min', 'budget_max'] as $field) {
            foreach (['numeric', 'decimal', 'min', 'max'] as $rule) {
                $messages[$field.'.'.$rule] = __('event_budget.'.$rule);
            }
        }

        return $messages;
    }

    public function create(): Redirector|RedirectResponse
    {
        abort_unless(Auth::check(), 403);
        $this->validate();

        if ($this->family_id !== null) {
            $family = Family::query()->findOrFail($this->family_id);
            Gate::authorize('view', $family);
            abort_if($family->is_archived, 403);
        }

        $event = AmigoSecretoEvent::create([
            'organizer_id' => Auth::id(),
            'family_id' => $this->family_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'event_type' => $this->event_type,
            'budget_min' => blank($this->budget_min) ? null : $this->budget_min,
            'budget_max' => blank($this->budget_max) ? null : $this->budget_max,
            'event_date' => $this->event_date ?: null,
            'reveal_date' => $this->reveal_date ?: null,
            'recipient_visibility' => $this->recipient_visibility,
            'status' => 'PLANNING',
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        $this->dispatch('notification', ['message' => __('messages.amigo_secreto.created'), 'type' => 'success']);

        return redirect()->route('amigo-secreto.show', $event);
    }

    public function render(): View
    {
        abort_unless(Auth::check(), 403);
        $families = Auth::user()->families()->where('is_archived', false)->get();

        return view('livewire.amigo-secreto.create-event', [
            'families' => $families,
        ]);
    }
}
