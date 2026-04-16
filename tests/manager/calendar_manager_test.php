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
 * Tests for calendar_manager.
 *
 * Uses stub providers injected via reflection so no live API calls are made.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\contract\calendar_provider;

/**
 * Tests for calendar_manager.
 *
 * @covers \local_coursectrl\manager\calendar_manager
 */
final class calendar_manager_test extends \advanced_testcase {
    /**
     * Build a stub calendar_provider that returns pre-defined day info.
     *
     * @param array $dayinfo Map of Y-m-d → event arrays to return.
     * @return calendar_provider
     */
    private function stub_provider(array $dayinfo): calendar_provider {
        $stub = $this->createMock(calendar_provider::class);
        $stub->method('is_enabled')->willReturn(true);
        $stub->method('is_available')->willReturn(true);
        $stub->method('get_supported_categories')->willReturn(['public_holiday']);
        $stub->method('get_day_info')->willReturnCallback(
            function(int $year, int $month) use ($dayinfo) {
                $result = [];
                foreach ($dayinfo as $datekey => $events) {
                    $m = (int) date('n', strtotime('!' . $datekey));
                    $y = (int) date('Y', strtotime('!' . $datekey));
                    if ($y === $year && $m === $month) {
                        $result[$datekey] = $events;
                    }
                }
                return $result;
            }
        );
        return $stub;
    }

    /**
     * Inject stub providers into a calendar_manager via reflection.
     *
     * @param calendar_manager $manager   Manager instance.
     * @param calendar_provider[] $providers Stub providers to inject.
     */
    private function inject_providers(calendar_manager $manager, array $providers): void {
        $ref = new \ReflectionClass($manager);
        $provprop = $ref->getProperty('providers');
        $provprop->setAccessible(true);
        $provprop->setValue($manager, $providers);
        $discprop = $ref->getProperty('discovered');
        $discprop->setAccessible(true);
        $discprop->setValue($manager, true);
    }

    /**
     * get_holidays_for_range returns empty array when no providers are registered.
     */
    public function test_no_providers_returns_empty(): void {
        $this->resetAfterTest();
        $manager = new calendar_manager();
        $this->inject_providers($manager, []);
        $result = $manager->get_holidays_for_range(
            strtotime('2026-06-01'),
            strtotime('2026-06-30')
        );
        $this->assertEmpty($result);
    }

    /**
     * is_holiday returns true when the date is in the provider data.
     */
    public function test_is_holiday_returns_true_for_known_date(): void {
        $this->resetAfterTest();
        $provider = $this->stub_provider([
            '2026-06-15' => [['name' => 'Test Holiday', 'category' => 'public_holiday', 'source' => 'test']],
        ]);
        $manager = new calendar_manager();
        $this->inject_providers($manager, [$provider]);
        $this->assertTrue($manager->is_holiday(strtotime('2026-06-15')));
        $this->assertFalse($manager->is_holiday(strtotime('2026-06-16')));
    }

    /**
     * get_events_for_day returns the correct event list.
     */
    public function test_get_events_for_day_returns_events(): void {
        $this->resetAfterTest();
        $events = [['name' => 'Feiertag', 'category' => 'public_holiday', 'source' => 'test']];
        $provider = $this->stub_provider(['2026-10-03' => $events]);
        $manager = new calendar_manager();
        $this->inject_providers($manager, [$provider]);
        $result = $manager->get_events_for_day(strtotime('2026-10-03'));
        $this->assertCount(1, $result);
        $this->assertSame('Feiertag', $result[0]['name']);
    }

    /**
     * Results from multiple providers are merged for the same day.
     */
    public function test_multiple_providers_merged(): void {
        $this->resetAfterTest();
        $event1 = [['name' => 'National Day', 'category' => 'public_holiday', 'source' => 'p1']];
        $event2 = [['name' => 'School Break', 'category' => 'school_holiday', 'source' => 'p2']];
        $manager = new calendar_manager();
        $this->inject_providers($manager, [
            $this->stub_provider(['2026-07-01' => $event1]),
            $this->stub_provider(['2026-07-01' => $event2]),
        ]);
        $events = $manager->get_events_for_day(strtotime('2026-07-01'));
        $this->assertCount(2, $events);
    }

    /**
     * get_holidays_for_range correctly spans multiple months.
     */
    public function test_range_spans_months(): void {
        $this->resetAfterTest();
        $data = [
            '2026-06-24' => [['name' => 'June Event', 'category' => 'custom', 'source' => 'test']],
            '2026-07-04' => [['name' => 'July Event', 'category' => 'custom', 'source' => 'test']],
        ];
        $provider = $this->stub_provider($data);
        $manager = new calendar_manager();
        $this->inject_providers($manager, [$provider]);
        $result = $manager->get_holidays_for_range(
            strtotime('2026-06-01'),
            strtotime('2026-07-31')
        );
        $this->assertArrayHasKey('2026-06-24', $result);
        $this->assertArrayHasKey('2026-07-04', $result);
    }
}
