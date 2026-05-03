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
 * Tests for the availability_parser.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\availability_parser::class)]
/**
 * Unit tests for availability_parser.
 *
 * @covers \local_coursectrl\local\analysis\availability_parser
 */
final class availability_parser_test extends \basic_testcase {
    /** @var availability_parser */
    private availability_parser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->parser = new availability_parser();
    }

    /**
     * Null input must return empty result with no restrictions.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_null(): void {
        $result = $this->parser->parse(null);
        $this->assertFalse($result['hasrestrictions']);
        $this->assertEmpty($result['completiondeps']);
    }

    /**
     * Empty string must return empty result.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_empty_string(): void {
        $result = $this->parser->parse('');
        $this->assertFalse($result['hasrestrictions']);
    }

    /**
     * Completion condition must be extracted.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_completion(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 5, 'e' => 1],
            ],
            'showc' => [true],
        ]);
        $result = $this->parser->parse($json);

        $this->assertTrue($result['hasrestrictions']);
        $this->assertArrayHasKey(5, $result['completiondeps']);
        $this->assertSame(1, $result['completiondeps'][5]);
    }

    /**
     * Date conditions must be extracted with direction and timestamp.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_date(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'date', 'd' => '>=', 't' => 1700000000],
                ['type' => 'date', 'd' => '<', 't' => 1700100000],
            ],
        ]);
        $result = $this->parser->parse($json);

        $this->assertCount(2, $result['dateconditions']);
        $this->assertSame('>=', $result['dateconditions'][0]['direction']);
        $this->assertSame(1700000000, $result['dateconditions'][0]['timestamp']);
        $this->assertSame('<', $result['dateconditions'][1]['direction']);
    }

    /**
     * Grade conditions must be extracted.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_grade(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'grade', 'id' => 10, 'min' => 50.0],
            ],
        ]);
        $result = $this->parser->parse($json);

        $this->assertCount(1, $result['gradeconditions']);
        $this->assertSame(10, $result['gradeconditions'][0]['itemid']);
        $this->assertSame(50.0, $result['gradeconditions'][0]['min']);
    }

    /**
     * Group and grouping conditions must be extracted.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_group(): void {
        $json = json_encode([
            'op' => '|',
            'c' => [
                ['type' => 'group', 'id' => 3],
                ['type' => 'grouping', 'id' => 7],
            ],
        ]);
        $result = $this->parser->parse($json);

        $this->assertSame('|', $result['operator']);
        $this->assertCount(2, $result['groupconditions']);
        $this->assertSame('group', $result['groupconditions'][0]['type']);
        $this->assertSame('grouping', $result['groupconditions'][1]['type']);
    }

    /**
     * Multiple completion deps must all be extracted.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_multiple_completions(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 5, 'e' => 1],
                ['type' => 'completion', 'cm' => 8, 'e' => 2],
            ],
        ]);
        $result = $this->parser->parse($json);

        $this->assertCount(2, $result['completiondeps']);
        $this->assertArrayHasKey(5, $result['completiondeps']);
        $this->assertArrayHasKey(8, $result['completiondeps']);
    }

    /**
     * Convenience method must return only cmids.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_get_completion_deps(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 5, 'e' => 1],
                ['type' => 'date', 'd' => '>=', 't' => 1700000000],
            ],
        ]);
        $deps = $this->parser->get_completion_deps($json);

        $this->assertSame([5], $deps);
    }

    /**
     * Convenience method must return date conditions.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_get_date_conditions(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'date', 'd' => '>=', 't' => 1700000000],
            ],
        ]);
        $dates = $this->parser->get_date_conditions($json);

        $this->assertCount(1, $dates);
        $this->assertSame(1700000000, $dates[0]['timestamp']);
    }

    /**
     * Unknown condition types must go into otherconditions.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_unknown_type(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'profile', 'sf' => 'email', 'op' => 'contains', 'v' => '@'],
            ],
        ]);
        $result = $this->parser->parse($json);

        $this->assertCount(1, $result['otherconditions']);
        $this->assertTrue($result['hasrestrictions']);
    }

    /**
     * Invalid JSON must return empty result gracefully.
     * @covers \local_coursectrl\local\analysis\availability_parser
     */
    public function test_parse_invalid_json(): void {
        $result = $this->parser->parse('{broken');
        $this->assertFalse($result['hasrestrictions']);
    }
}
