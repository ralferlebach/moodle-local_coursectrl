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
 * English strings for coursectrlcal_moodlecal.
 *
 * @package    coursectrlcal_moodlecal
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Course Control Hub: Moodle Calendar Reader';
$string['privacy:metadata'] = 'The Moodle calendar adapter reads existing Moodle events and stores no additional personal data.';
$string['setting_category']          = 'Assign category';
$string['setting_category_desc']     = 'Category to assign to matched events: public_holiday, school_holiday, or custom.';
$string['setting_enabled']           = 'Enable Moodle calendar reader';
$string['setting_eventtype']         = 'Event types to include';
$string['setting_eventtype_desc']    = 'Comma-separated Moodle event types: site, category, user. Default: site. Avoid course to prevent circular references with activity deadlines.';
$string['setting_namepattern']       = 'Name filter (regex)';
$string['setting_namepattern_desc']  = 'Optional PHP regex applied to event name, e.g. /Ferien|Holiday/i. Leave empty to include all events of the selected type.';
