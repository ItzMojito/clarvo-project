<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrerequisiteGraphService
{
    /**
     * Menelusuri graf DAG mundur untuk menemukan prasyarat terlemah di bawah ambang gate (0.85).
     */
    public function weakestPrerequisites(int $learnerId, int $targetConceptId, float $threshold = 0.85): Collection
    {
        $sql = "
            WITH RECURSIVE prereq_tree AS (
                SELECT prerequisite_id AS concept_id, 1 AS depth
                FROM concept_prerequisites
                WHERE concept_id = ?
                UNION ALL
                SELECT cp.prerequisite_id, pt.depth + 1
                FROM concept_prerequisites cp
                INNER JOIN prereq_tree pt ON cp.concept_id = pt.concept_id
                WHERE pt.depth < 6
            )
            SELECT c.id, c.code, c.name, MAX(pt.depth) AS depth,
                   COALESCE(sm.p_l, c.default_p_l0) AS current_p_l
            FROM prereq_tree pt
            JOIN concepts c ON c.id = pt.concept_id
            LEFT JOIN student_masteries sm
                ON sm.concept_id = c.id AND sm.learner_id = ?
            GROUP BY c.id, c.code, c.name, sm.p_l, c.default_p_l0
            HAVING current_p_l < ?
            ORDER BY depth DESC, current_p_l ASC
        ";

        return collect(DB::select($sql, [$targetConceptId, $learnerId, $threshold]));
    }
}
