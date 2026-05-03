<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for gantt_dataset_builder.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\visualization;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\visualization\gantt_dataset_builder::class)]
/**
 * Unit tests for gantt_dataset_builder.
 *
 * Uses a stub date_collector to avoid adapter/DB calls in unit tests.
 *
 * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
 */
final class gantt_dataset_builder_test extends \advanced_testcase {
    /** @var int Arbitrary base timestamp (2026-06-01 UTC). */
    private const T1 = 1748736000;

    /** @var int T1 + 7 days. */
    private const T2 = 1749340800;

    /** @var int T1 + 14 days. */
    private const T3 = 1749945600;

    /**
     * Build a stub date_collector that returns pre-determined grouped data.
     *
     * @param array $groupedbycm Data to return from collect_grouped_by_cm().
     * @return date_collector PHPUnit stub.
     */
    private function stub_collector(array $groupedbycm): date_collector {
        $stub = $this->createMock(date_collector::class);
        $stub->method('collect_grouped_by_cm')->willReturn($groupedbycm);
        return $stub;
    }

    /**
     * Build a minimal cm_item.
     *
     * @param int $cmid CM id.
     * @return cm_item
     */
    private function make_cm(int $cmid): cm_item {
        return new cm_item($cmid, 1, 10, 'assign', $cmid, 'Activity ' . $cmid, true, null, 2);
    }

    /**
     * Build a date entry array.
     *
     * @param int    $cmid      CM id.
     * @param string $field     Field name.
     * @param int    $ts        Timestamp.
     * @param string $source    Source ('adapter'|'cm'|'availability').
     * @return array
     */
    private function entry(int $cmid, string $field, int $ts, string $source = 'adapter'): array {
        return [
            'cmid' => $cmid,
            'name' => 'Activity ' . $cmid,
            'modname' => 'assign',
            'component' => 'mod_assign',
            'field' => $field,
            'fieldlabel' => $field,
            'timestamp' => $ts,
            'source' => $source,
        ];
    }

    /**
     * Empty CMs produce hasdata=false.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_empty_cms_returns_no_data(): void {
        $this->resetAfterTest();
        $builder = new gantt_dataset_builder($this->stub_collector([]));
        $result = $builder->build([]);
        $this->assertFalse($result['hasdata']);
        $this->assertSame(0, $result['rowcount']);
    }

    /**
     * CMs with no date entries are excluded from rows.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_cms_without_dates_excluded(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2)];
        // Only CM 2 has entries.
        $grouped = [2 => [$this->entry(2, 'duedate', self::T1)]];
        $builder = new gantt_dataset_builder($this->stub_collector($grouped));
        $result = $builder->build($cms);
        $this->assertTrue($result['hasdata']);
        $this->assertSame(1, $result['rowcount']);
        $this->assertSame(2, $result['rows'][0]['cmid']);
    }

    /**
     * mints and maxts reflect the global date range across all rows.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_mints_maxts_span_all_dates(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2)];
        $grouped = [
            1 => [$this->entry(1, 'duedate', self::T2)],
            2 => [$this->entry(2, 'timeopen', self::T1), $this->entry(2, 'timeclose', self::T3)],
        ];
        $builder = new gantt_dataset_builder($this->stub_collector($grouped));
        $result = $builder->build($cms);
        $this->assertSame(self::T1, $result['mints']);
        $this->assertSame(self::T3, $result['maxts']);
    }

    /**
     * Bars within a row are sorted chronologically.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_bars_sorted_chronologically(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1)];
        $grouped = [
            1 => [
                $this->entry(1, 'duedate', self::T3),
                $this->entry(1, 'allowsubmissionsfromdate', self::T1),
            ],
        ];
        $builder = new gantt_dataset_builder($this->stub_collector($grouped));
        $result = $builder->build($cms);
        $bars = $result['rows'][0]['bars'];
        $this->assertSame(self::T1, $bars[0]['timestamp']);
        $this->assertSame(self::T3, $bars[1]['timestamp']);
    }

    /**
     * Rows are sorted by earliest bar timestamp.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_rows_sorted_by_earliest_date(): void {
        $this->resetAfterTest();
        // CM 2 has an earlier date than CM 1.
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2)];
        $grouped = [
            1 => [$this->entry(1, 'duedate', self::T3)],
            2 => [$this->entry(2, 'duedate', self::T1)],
        ];
        $builder = new gantt_dataset_builder($this->stub_collector($grouped));
        $result = $builder->build($cms);
        $this->assertSame(2, $result['rows'][0]['cmid']);
        $this->assertSame(1, $result['rows'][1]['cmid']);
    }

    /**
     * Each bar carries source and fieldlabel keys for the renderer.
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_bar_contains_required_keys(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1)];
        $grouped = [1 => [$this->entry(1, 'duedate', self::T1, 'adapter')]];
        $builder = new gantt_dataset_builder($this->stub_collector($grouped));
        $result = $builder->build($cms);
        $bar = $result['rows'][0]['bars'][0];
        $this->assertArrayHasKey('field', $bar);
        $this->assertArrayHasKey('fieldlabel', $bar);
        $this->assertArrayHasKey('timestamp', $bar);
        $this->assertArrayHasKey('source', $bar);
        $this->assertSame('adapter', $bar['source']);
    }

    /**
     * All-dates-absent course returns empty result (not an error).
     * @covers \local_coursectrl\local\visualization\gantt_dataset_builder
     */
    public function test_course_with_cms_but_no_dates_returns_empty(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2)];
        $builder = new gantt_dataset_builder($this->stub_collector([]));
        $result = $builder->build($cms);
        $this->assertFalse($result['hasdata']);
        $this->assertSame(0, $result['rowcount']);
    }
}
