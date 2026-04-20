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
 * English strings for coursectrlcal_openholidays.
 *
 * @package    coursectrlcal_openholidays
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Course Control Hub: OpenHolidays API (Public + School Holidays)';
$string['privacy:metadata'] = 'The OpenHolidays adapter fetches public data and stores no personal data.';
$string['setting_categories']           = 'Categories to load';
$string['setting_categories_desc']      = 'Comma-separated: public_holiday, school_holiday';
$string['setting_countryisocode']       = 'Country ISO code';
$string['setting_countryisocode_desc']  = 'E.g. DE, AT, CH';
$string['setting_enabled']              = 'Enable OpenHolidays API provider';
$string['setting_languageisocode']      = 'Language ISO code';
$string['setting_languageisocode_desc'] = 'Preferred language for holiday names, e.g. DE, EN, FR';
$string['setting_regioncode']           = 'Region code';
$string['setting_regioncode_desc']      = 'Subdivision code for school holidays, e.g. DE-BY (Bavaria), AT-7 (Tyrol), CH-ZH (Zurich).';
