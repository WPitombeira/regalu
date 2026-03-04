<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Services\DrawAlgorithmService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

class ExecuteDraw extends Component {
    public AmigoSecretoEvent $event;

    public function mount(AmigoSecretoEvent $event): void {
        $this->event = $event;
    }

    public function executeDraw(): void {
        if ($this->event->organizer_id !== Auth::id()) {
            return;
        }

        if ($this->event->status === 'DRAWS_COMPLETE') {
            $this->dispatch('notification', ["message" => __("messages.amigo_secreto.already_drawn"), "type" => "error"]);
            return;
        }

        $acceptedParticipants = $this->event->participants()
            ->where('status', 'ACCEPTED')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $exclusions = $this->event->exclusions()
            ->get()
            ->map(fn ($exclusion) => [$exclusion->user_a_id, $exclusion->user_b_id])
            ->all();

        try {
            $service = new DrawAlgorithmService();
            $results = $service->execute($acceptedParticipants, $exclusions);

            DB::transaction(function () use ($results) {
                foreach ($results as $drawerId => $targetId) {
                    AmigoSecretoDraw::create([
                        'event_id' => $this->event->id,
                        'drawer_user_id' => $drawerId,
                        'target_user_id' => $targetId,
                        'draw_date' => now(),
                    ]);
                }

                $this->event->update(['status' => 'DRAWS_COMPLETE']);
            });

            $this->event->refresh();

            $this->dispatch('notification', ["message" => __("messages.amigo_secreto.draw_complete"), "type" => "success"]);
        } catch (RuntimeException $e) {
            $this->dispatch('notification', ["message" => __("messages.amigo_secreto.draw_impossible"), "type" => "error"]);
        }
    }

    public function render() {
        $draws = $this->event->draws()->with(['drawer', 'target'])->get();
        $isOrganizer = $this->event->organizer_id === Auth::id();

        $myDraw = $draws->where('drawer_user_id', Auth::id())->first();

        return view('livewire.amigo-secreto.execute-draw', [
            'draws' => $draws,
            'isOrganizer' => $isOrganizer,
            'myDraw' => $myDraw,
        ]);
    }
}
