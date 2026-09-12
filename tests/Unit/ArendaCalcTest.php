<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/arenda2_totals.php';

class ArendaCalcTest extends TestCase
{
    // ── arenda2_period_months_days ──────────────────────────────

    public function testPeriodSameDate(): void
    {
        $r = arenda2_period_months_days('2026-01-15', '2026-01-15');
        $this->assertSame(0, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    public function testPeriodExactMonth(): void
    {
        $r = arenda2_period_months_days('2026-01-10', '2026-02-10');
        $this->assertSame(1, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    public function testPeriodMonthPlusDays(): void
    {
        $r = arenda2_period_months_days('2026-01-10', '2026-02-15');
        $this->assertSame(1, $r['months']);
        $this->assertSame(5, $r['days']);
    }

    public function testPeriodTwoMonths(): void
    {
        $r = arenda2_period_months_days('2026-01-01', '2026-03-01');
        $this->assertSame(2, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    public function testPeriodDaysOnly(): void
    {
        $r = arenda2_period_months_days('2026-09-01', '2026-09-05');
        $this->assertSame(0, $r['months']);
        $this->assertSame(4, $r['days']);
    }

    public function testPeriodEndBeforeBeg(): void
    {
        $r = arenda2_period_months_days('2026-03-01', '2026-01-01');
        $this->assertSame(0, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    public function testPeriodInvalidFormats(): void
    {
        $r = arenda2_period_months_days('', '2026-01-01');
        $this->assertSame(0, $r['months']);
        $this->assertSame(0, $r['days']);

        $r = arenda2_period_months_days('bad', 'bad');
        $this->assertSame(0, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    public function testPeriodLeapYear(): void
    {
        $r = arenda2_period_months_days('2024-02-01', '2024-03-01');
        $this->assertSame(1, $r['months']);
        $this->assertSame(0, $r['days']);
    }

    // ── arenda2_hours_diff ──────────────────────────────────────

    public function testHoursDiffSame(): void
    {
        $this->assertSame(0.0, arenda2_hours_diff('12:00', '12:00'));
    }

    public function testHoursDiffForward(): void
    {
        $this->assertSame(2.0, arenda2_hours_diff('12:00', '14:00'));
    }

    public function testHoursDiffWrap(): void
    {
        // 23:00 → 01:00 = 2 hours (wraps)
        $this->assertSame(2.0, arenda2_hours_diff('23:00', '01:00'));
    }

    public function testHoursDiffHalfHour(): void
    {
        $this->assertSame(1.5, arenda2_hours_diff('10:00', '11:30'));
    }

    public function testHoursDiffFullDay(): void
    {
        // Same time → 0 hours (not 24; only negative wraps +24)
        $this->assertSame(0.0, arenda2_hours_diff('14:00', '14:00'));
    }

    // ── arenda2_count_weekends_end ──────────────────────────────

    public function testWeekendsEndOneWeekNoWE(): void
    {
        // 2026-09-10 is Thursday; 3 days back = Thu,Wed,Tue = no weekends
        $r = arenda2_count_weekends_end('2026-09-10', 3);
        $this->assertSame(0, $r);
    }

    public function testWeekendsEndOneWeekAllWE(): void
    {
        // 2026-09-06 is Sunday; 7 days = Sun, Sat, Fri, Thu, Wed, Tue, Mon = 2 WE
        $r = arenda2_count_weekends_end('2026-09-06', 7);
        $this->assertSame(2, $r);
    }

    public function testWeekendsEndTwoWeeks(): void
    {
        // 2026-09-14 Mon → 14 days back to Mon. Sun=13, Sat=12, Sun=6, Sat=5 → 4 weekends
        $r = arenda2_count_weekends_end('2026-09-14', 14);
        $this->assertSame(4, $r);
    }

    public function testWeekendsEndZeroDays(): void
    {
        $this->assertSame(0, arenda2_count_weekends_end('2026-09-01', 0));
    }

    // ── arenda2_count_weekends_range ────────────────────────────

    public function testWeekendsRangeMonToFri(): void
    {
        // Mon Sep 7 to Fri Sep 11, 2026 = 0 weekends
        $this->assertSame(0, arenda2_count_weekends_range('2026-09-07', '2026-09-11'));
    }

    public function testWeekendsRangeFullWeek(): void
    {
        // Mon Sep 7 to Sun Sep 13 = 2 weekends (Sat 12, Sun 13)
        $this->assertSame(2, arenda2_count_weekends_range('2026-09-07', '2026-09-13'));
    }

    public function testWeekendsRangeEndBeforeBeg(): void
    {
        $this->assertSame(0, arenda2_count_weekends_range('2026-09-10', '2026-09-05'));
    }

    public function testWeekendsRangeSingleDaySaturday(): void
    {
        // 2026-09-05 is Saturday
        $this->assertSame(1, arenda2_count_weekends_range('2026-09-05', '2026-09-05'));
    }

    public function testWeekendsRangeSingleDaySunday(): void
    {
        // 2026-09-06 is Sunday
        $this->assertSame(1, arenda2_count_weekends_range('2026-09-06', '2026-09-06'));
    }

    public function testWeekendsRangeTwoWeekends(): void
    {
        // Sat Sep 5 to Sun Sep 13: Sat5 + Sun6 + Sat12 + Sun13 = 4
        $this->assertSame(4, arenda2_count_weekends_range('2026-09-05', '2026-09-13'));
    }

    // ── arenda2_calc_sum_for_item ───────────────────────────────

    public function testCalcSumSimpleDays(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12', // Mon-Fri, no weekends
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        $this->assertSame(500.0, $r['sum']);
        $this->assertSame(0.0, $r['sum_discount']);
    }

    public function testCalcSumDaysWithWeekends(): void
    {
        // Mon Sep 7 to Sun Sep 13 = 6 days, 2 weekends (Sat+Sun)
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 6, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 150, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-13',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 4 weekdays * 100 + 2 weekends * 150 = 400 + 300 = 700
        $this->assertSame(700.0, $r['sum']);
    }

    public function testCalcSumFixedTariff(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 10, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 500, 'fixed_flag' => 1, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-17',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        $this->assertSame(500.0, $r['sum']);
    }

    public function testCalcSumWithDiscount(): void
    {
        $item = [
            'discount' => 10, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 500 * 0.9 = 450
        $this->assertSame(450.0, $r['sum']);
        $this->assertSame(50.0, $r['sum_discount']);
    }

    public function testCalcSumWithQuant(): void
    {
        $item = [
            'discount' => 0, 'quant' => 3, 'days' => 2, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-09',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 200 * 3 = 600
        $this->assertSame(600.0, $r['sum']);
    }

    public function testCalcSumNoquantIgnoresQuant(): void
    {
        $item = [
            'discount' => 0, 'quant' => 5, 'days' => 2, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 1,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-09',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // noquant: quant ignored → 200 * 1 = 200
        $this->assertSame(200.0, $r['sum']);
    }

    public function testCalcSumNds(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 20];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 500 * 1.20 = 600
        $this->assertSame(600.0, $r['sum']);
    }

    public function testCalcSumNoNdsFlag(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '1', 'nds_rate' => 20];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // no_nds=1: NDS skipped → 500
        $this->assertSame(500.0, $r['sum']);
    }

    public function testCalcSumDaysPlusHours(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 2, 'hours' => '3:00', 'months' => 0,
            'price_day' => 100, 'price_hour' => 20, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-09',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 2 weekdays * 100 + 3h * 20 = 260
        $this->assertSame(260.0, $r['sum']);
    }

    public function testCalcSumMonths(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 0, 'hours' => '', 'months' => 2,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 500,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-11-07',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // months active: 2 * 500 = 1000
        $this->assertSame(1000.0, $r['sum']);
    }

    public function testCalcSumFixedWithGlobalFixedFlag(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 10, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 300, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-17',
        ];
        // FixedFlag=1 globally makes price_fix used even if item.fixed_flag=0
        $cfg = ['FixedFlag' => 1, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        $this->assertSame(300.0, $r['sum']);
    }

    public function testCalcSumZeroDaysZeroHours(): void
    {
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 0, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 50, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-07',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        $this->assertSame(0.0, $r['sum']);
    }

    public function testCalcSumWeekendTariffFallbackToDay(): void
    {
        // price_we=0 → falls back to price_day
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 2, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-05', 'date_voz' => '2026-09-07', // Sat-Sun, 2 weekends
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 0];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // price_we=0 → $pWe = $pDay = 100; 2 weekend days * 100 = 200
        $this->assertSame(200.0, $r['sum']);
    }

    public function testCalcSumDiscountAndNds(): void
    {
        $item = [
            'discount' => 10, 'quant' => 2, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $cfg = ['FixedFlag' => 0, 'no_nds' => '0', 'nds_rate' => 20];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // gross = 500; after disc: 450; after quant: 900; after NDS: 1080
        $this->assertSame(1080.0, $r['sum']);
        $this->assertSame(100.0, $r['sum_discount']); // 500 * 0.10 * 2 = 100
    }
}
