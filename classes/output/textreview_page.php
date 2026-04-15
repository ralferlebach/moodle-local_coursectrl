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
 * Renderable for the text-datetime review page.
 *
 * Transforms text_hit persistent records into a template context
 * suitable for the textreview.mustache review table.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\persistent\text_hit;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the text-datetime review page.
 */
class textreview_page implements renderable, templatable {
    /** @var int Course id. */
    protected int $courseid;

    /** @var text_hit[] Hits to display. */
    protected array $hits;

    /** @var array Scan summary. */
    protected array $summary;

    /**
     * Constructor.
     *
     * @param int        $courseid Course id.
     * @param text_hit[] $hits     Hits to display.
     * @param array      $summary  Scan summary counts.
     */
    public function __construct(int $courseid, array $hits, array $summary) {
        $this->courseid = $courseid;
        $this->hits = $hits;
        $this->summary = $summary;
    }

    /**
     * Build template context for templates/textreview.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach ($this->hits as $hit) {
            $contextraw = $hit->get('contextjson');
            $context = $contextraw ? json_decode($contextraw, true) : [];
            $confidence = $hit->get('confidence');

            $rows[] = [
                'id' => (int) $hit->get('id'),
                'entitytype' => $hit->get('entitytype'),
                'entityid' => (int) $hit->get('entityid'),
                'fieldname' => $hit->get('fieldname'),
                'matchedtext' => $hit->get('matchedtext'),
                'normalizedvalue' => $hit->get('normalizedvalue') ?? '',
                'hasnormalized' => $hit->get('normalizedvalue') !== null
                    && $hit->get('normalizedvalue') !== '',
                'confidence' => $confidence,
                'issafe' => $confidence === text_hit::CONFIDENCE_SAFE,
                'isambiguous' => $confidence === text_hit::CONFIDENCE_AMBIGUOUS,
                'isinformational' => $confidence === text_hit::CONFIDENCE_INFORMATIONAL,
                'contextbefore' => $context['before'] ?? '',
                'contextafter' => $context['after'] ?? '',
                'pattern' => $context['pattern'] ?? '',
                'selectable' => $confidence !== text_hit::CONFIDENCE_INFORMATIONAL
                    && $hit->get('normalizedvalue') !== null
                    && $hit->get('normalizedvalue') !== '',
            ];
        }

        $safehits = array_filter($rows, fn($r) => $r['issafe']);
        $ambiguoushits = array_filter($rows, fn($r) => $r['isambiguous']);
        $informationalhits = array_filter($rows, fn($r) => $r['isinformational']);

        return [
            'courseid' => $this->courseid,
            'sesskey' => sesskey(),
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'textreviewurl' => (new \moodle_url(
                '/local/coursectrl/textreview.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'rows' => array_values($rows),
            'hasrows' => count($rows) > 0,
            'summary_total' => (int) ($this->summary['total'] ?? 0),
            'summary_safe' => (int) ($this->summary['safe'] ?? 0),
            'summary_ambiguous' => (int) ($this->summary['ambiguous'] ?? 0),
            'summary_informational' => (int) ($this->summary['informational'] ?? 0),
            'hassafe' => count($safehits) > 0,
            'hasambiguous' => count($ambiguoushits) > 0,
            'hasinformational' => count($informationalhits) > 0,
            'selectablecount' => count(array_filter($rows, fn($r) => $r['selectable'])),
        ];
    }
}
