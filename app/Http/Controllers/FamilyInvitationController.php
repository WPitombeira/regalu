<?php

namespace App\Http\Controllers;

use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FamilyInvitationController extends Controller
{
    public function show(Request $request, int $invitation): Response
    {
        $record = $this->recipientInvitation($request, $invitation);

        return response()->view('family-invitations.show', ['invitation' => $record], $record ? 200 : 403)
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store');
    }

    public function respond(Request $request, int $invitation): RedirectResponse|Response
    {
        $record = $this->recipientInvitation($request, $invitation);
        if (! $record) {
            return $this->show($request, $invitation);
        }
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]]);
        $status = $data['decision'] === 'accept' ? 'ACCEPTED' : 'DECLINED';
        DB::transaction(function () use ($record, $request, $status): void {
            $changed = FamilyInvitation::whereKey($record->id)->valid()
                ->forRecipient((int) $request->user()->getAuthIdentifier())
                ->where('generation', $record->generation)->where('status', 'PENDING')
                ->update(['status' => $status, 'responded_at' => now()]);
            abort_unless($changed === 1, 409);
            if ($status === 'ACCEPTED') {
                $userId = (int) $request->user()->getAuthIdentifier();
                $eligible = FamilyInvitation::whereKey($record->id)->valid()->forRecipient($userId)
                    ->where('generation', $record->generation)->where('status', 'ACCEPTED');
                DB::table('family_members')->insertOrIgnoreUsing(
                    ['family_id', 'user_id', 'role', 'joined_at', 'created_at', 'updated_at'],
                    (clone $eligible)->selectRaw('family_id, ?, ?, ?, ?, ?', [$userId, 'MEMBER', now(), now(), now()])->toBase(),
                );
                abort_unless($eligible->exists() && FamilyMember::where('family_id', $record->family_id)->where('user_id', $userId)->exists(), 409);
            }
        });

        return $status === 'ACCEPTED'
            ? redirect()->route('families.show', $record->family_id)
            : redirect()->to($request->fullUrl());
    }

    private function recipientInvitation(Request $request, int $id): ?FamilyInvitation
    {
        if (! $request->hasValidSignature() || ! $request->user()) {
            return null;
        }

        return FamilyInvitation::with('family')->whereKey($id)->valid()
            ->forRecipient((int) $request->user()->getAuthIdentifier())
            ->where('generation', $request->query('generation'))
            ->first();
    }
}
