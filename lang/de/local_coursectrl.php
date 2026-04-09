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
 * Deutsche Sprachstrings für local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Kern.
$string['pluginname']      = 'Kursablauf-Zentrale';
$string['pluginname_desc'] = 'Kursweite Analyse, Massenbearbeitung, Zeitachsen-Darstellung, Lernenden-Simulation und Risikoerkennung für Moodle-Kurse.';

// Rechte.
$string['coursectrl:view']          = 'Kursablauf-Zentrale anzeigen';
$string['coursectrl:bulkaction']    = 'Massenaktionen ausführen';
$string['coursectrl:viewreports']   = 'Berichte und Risikoanalysen anzeigen';
$string['coursectrl:rollback']      = 'Ausgeführte Batch-Aktionen rückgängig machen';
$string['coursectrl:managepresets'] = 'Aktions-Presets erstellen und verwalten';
$string['coursectrl:simulate']      = 'Lernenden-Simulationen ausführen';

// Navigation.
$string['nav_dashboard']  = 'Dashboard';
$string['nav_bulk']       = 'Massenaktionen';
$string['nav_timeline']   = 'Zeitachse';
$string['nav_graph']      = 'Abhängigkeitsgraph';
$string['nav_simulation'] = 'Simulation';
$string['nav_risks']      = 'Risiken';
$string['nav_history']    = 'Verlauf';

// Dashboard.
$string['dashboard_startdate']        = 'Startdatum';
$string['dashboard_enddate']          = 'Enddatum';
$string['dashboard_visibility']       = 'Sichtbarkeit';
$string['dashboard_visible']          = 'Sichtbar';
$string['dashboard_hidden']           = 'Verborgen';
$string['dashboard_sections']         = 'Abschnitte';
$string['dashboard_activities']       = 'Aktivitäten';
$string['dashboard_activities_short'] = 'Aktivitäten';
$string['dashboard_texts']            = 'Editierbare Texte';
$string['dashboard_inventory']        = 'Kursinventar';
$string['dashboard_section']          = 'Abschnitt';
$string['dashboard_completion']       = 'Abschluss';
$string['dashboard_availability']     = 'Eingeschränkt';
$string['dashboard_empty']            = 'Dieser Kurs hat noch keine inventarisierten Abschnitte.';

// Stub / Platzhalter.
$string['stub_placeholder'] = 'Kursablauf-Zentrale – Phase-1-Stub. Die vollständige Oberfläche folgt in Phase 2.';

// Fehler.
$string['error_no_course']     = 'Kein gültiger Kurskontext gefunden.';
$string['error_no_capability'] = 'Sie haben keine Berechtigung, die Kursablauf-Zentrale in diesem Kurs zu nutzen.';
