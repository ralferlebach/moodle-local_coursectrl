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
 * German strings for coursectrlcal_moodlecal.
 *
 * @package    coursectrlcal_moodlecal
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Kursablauf-Zentrale: Moodle-Kalender-Leser';
$string['privacy:metadata'] = 'Der Moodle-Kalender-Adapter liest vorhandene Moodle-Ereignisse und speichert keine zusätzlichen Daten.';
$string['setting_category']          = 'Kategorie zuweisen';
$string['setting_category_desc']     = 'Kategorie für gefundene Ereignisse: public_holiday, school_holiday oder custom.';
$string['setting_enabled']           = 'Moodle-Kalender-Leser aktivieren';
$string['setting_eventtype']         = 'Einzuschließende Ereignistypen';
$string['setting_eventtype_desc']    = 'Kommagetrennte Moodle-Ereignistypen: site, category, user. Standard: site. Typ course vermeiden (Zirkelbezüge durch Abgabetermine usw.).';
$string['setting_namepattern']       = 'Namensfilter (Regex)';
$string['setting_namepattern_desc']  = 'Optionaler PHP-Regex für den Ereignisnamen, z.B. /Ferien|Feiertag/i. Leer = alle Ereignisse des gewählten Typs.';
