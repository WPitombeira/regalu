<?php

use App\Services\DrawAlgorithmService;

describe('DrawAlgorithmService', function () {
    it('generates a valid draw for participants', function () {
        $service = new DrawAlgorithmService();
        $participants = [1, 2, 3, 4, 5];

        $result = $service->execute($participants);

        expect($result)->toHaveCount(5);

        // Each participant draws exactly one target
        expect(array_keys($result))->toEqualCanonicalizing($participants);

        // Each target is drawn exactly once
        expect(array_values($result))->toEqualCanonicalizing($participants);

        // No one draws themselves
        foreach ($result as $drawer => $target) {
            expect($drawer)->not->toBe($target);
        }
    });

    it('respects exclusions', function () {
        $service = new DrawAlgorithmService();
        $participants = [1, 2, 3, 4];
        $exclusions = [[1, 2]]; // 1 cannot draw 2 and 2 cannot draw 1

        $result = $service->execute($participants, $exclusions);

        expect($result[1])->not->toBe(2);
        expect($result[2])->not->toBe(1);
    });

    it('throws exception for impossible draw', function () {
        $service = new DrawAlgorithmService();
        // 2 participants that exclude each other = impossible
        $participants = [1, 2];
        $exclusions = [[1, 2]];

        $service->execute($participants, $exclusions, maxAttempts: 100);
    })->throws(RuntimeException::class);

    it('throws exception for fewer than 2 participants', function () {
        $service = new DrawAlgorithmService();

        $service->execute([1]);
    })->throws(RuntimeException::class, 'At least 2 participants are required');

    it('works with exactly 2 participants', function () {
        $service = new DrawAlgorithmService();
        $result = $service->execute([1, 2]);

        expect($result)->toBe([1 => 2, 2 => 1]);
    });
});
