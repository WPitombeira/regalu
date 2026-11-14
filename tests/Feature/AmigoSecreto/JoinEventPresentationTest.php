<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;

it('lets the joined event heading and badges wrap on narrow screens', function () {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'name' => 'Fixture DIRECTCODE01', 'event_type' => 'CASUAL',
    ]);
    AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED',
    ]);
    $response = $this->actingAs($user)->get(route('amigo-secreto.show', $event))->assertOk();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $headings = (new DOMXPath($document))->query('//main//h1[normalize-space(.)="Fixture DIRECTCODE01"]');
    expect($headings)->not->toBeFalse()->toHaveCount(1);
    $heading = $headings->item(0);
    expect($heading)->toBeInstanceOf(DOMElement::class);
    $titleRow = $heading->parentNode;
    $titleGroup = $titleRow->parentNode;
    $header = $titleGroup->parentNode;
    foreach ([$titleRow, $titleGroup, $header] as $node) {
        expect($node)->toBeInstanceOf(DOMElement::class);
    }
    $classes = fn (DOMElement $node) => preg_split('/\s+/', trim($node->getAttribute('class')));
    expect($classes($heading))->toContain('min-w-0', 'break-words')
        ->and($classes($titleRow))->toContain('flex-wrap')
        ->and($classes($titleGroup))->toContain('min-w-0')
        ->and($classes($header))->toContain('flex-col', 'sm:flex-row', 'gap-3');
});
