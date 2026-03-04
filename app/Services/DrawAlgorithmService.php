<?php

namespace App\Services;

use RuntimeException;

class DrawAlgorithmService {
    /**
     * Execute a Secret Santa draw using Fisher-Yates shuffle with exclusion validation.
     *
     * @param array<int> $participantIds List of user IDs participating in the draw
     * @param array<array{0: int, 1: int}> $exclusions Pairs of user IDs that cannot be matched
     * @param int $maxAttempts Maximum number of retry attempts
     * @return array<int, int> Map of drawer_user_id => target_user_id
     * @throws RuntimeException When a valid draw is impossible within max attempts
     */
    public function execute(array $participantIds, array $exclusions = [], int $maxAttempts = 1000): array {
        if (count($participantIds) < 2) {
            throw new RuntimeException('At least 2 participants are required for a draw.');
        }

        $exclusionMap = $this->buildExclusionMap($exclusions);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $result = $this->attemptDraw($participantIds, $exclusionMap);
            if ($result !== null) {
                return $result;
            }
        }

        throw new RuntimeException('Unable to generate a valid draw after ' . $maxAttempts . ' attempts. The exclusion rules may make a valid draw impossible.');
    }

    /**
     * @param array<array{0: int, 1: int}> $exclusions
     * @return array<int, array<int, bool>>
     */
    private function buildExclusionMap(array $exclusions): array {
        $map = [];
        foreach ($exclusions as [$userA, $userB]) {
            $map[$userA][$userB] = true;
            $map[$userB][$userA] = true;
        }
        return $map;
    }

    /**
     * @param array<int> $participantIds
     * @param array<int, array<int, bool>> $exclusionMap
     * @return array<int, int>|null
     */
    private function attemptDraw(array $participantIds, array $exclusionMap): ?array {
        $targets = $participantIds;

        // Fisher-Yates shuffle
        for ($i = count($targets) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$targets[$i], $targets[$j]] = [$targets[$j], $targets[$i]];
        }

        $result = [];
        foreach ($participantIds as $index => $drawerId) {
            $targetId = $targets[$index];

            // Cannot draw yourself
            if ($drawerId === $targetId) {
                return null;
            }

            // Cannot draw an excluded person
            if (isset($exclusionMap[$drawerId][$targetId])) {
                return null;
            }

            $result[$drawerId] = $targetId;
        }

        return $result;
    }
}
