<?php

namespace Tests\Unit;

use App\Services\Tuntas\BktEngineService;
use PHPUnit\Framework\TestCase;

class BktEngineServiceTest extends TestCase
{
    public function test_table2_wrong_right_right_sequence_opens_gate(): void
    {
        $bkt = new BktEngineService();

        $p1 = $bkt->updateMastery(0.30, false); // salah -> ~0.19
        $this->assertEquals(0.19, round($p1, 2));
        $this->assertFalse($bkt->isGateOpen($p1));

        $p2 = $bkt->updateMastery($p1, true);   // benar -> ~0.59
        $this->assertEquals(0.59, round($p2, 2));
        $this->assertFalse($bkt->isGateOpen($p2));

        $p3 = $bkt->updateMastery($p2, true);   // benar -> ~0.89
        $this->assertEquals(0.89, round($p3, 2));
        $this->assertTrue($bkt->isGateOpen($p3));
    }

    public function test_gate2_factoring_sequence(): void
    {
        $bkt = new BktEngineService();
        $pAfter = $bkt->updateMastery(0.72, true);
        $this->assertEquals(0.93, round($pAfter, 2));
        $this->assertTrue($bkt->isGateOpen($pAfter));
    }
}
