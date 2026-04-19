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
 * Behat step definitions for local_coursectrl.
 *
 * @package    local_coursectrl
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL check here as this file is used by Behat.

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for local_coursectrl.
 */
class behat_local_coursectrl extends behat_base {
    /**
     * Navigate to the checks page for the given course (consistency tab).
     *
     * @Given /^I am on the checks page for course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course short name.
     */
    public function i_am_on_the_checks_page_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/local/coursectrl/checks.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Navigate to a specific tab on the checks page.
     *
     * @Given /^I am on the checks page for course "(?P<shortname_string>(?:[^"]|\\")*)" tab "(?P<tab_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course short name.
     * @param string $tab       Tab name: consistency | risks | simulation.
     */
    public function i_am_on_the_checks_page_tab(string $shortname, string $tab): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $course->id, 'tab' => $tab]
        );
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Trigger a fresh risk assessment run for the given course.
     *
     * @Given /^I run the risk assessment for course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course short name.
     */
    public function i_run_the_risk_assessment(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $course->id, 'tab' => 'risks', 'run' => 1]
        );
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }
}
