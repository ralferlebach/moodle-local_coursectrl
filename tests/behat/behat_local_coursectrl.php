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
    // Navigation steps.

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

    /**
     * Navigate to the Course Control Hub dashboard for a course.
     *
     * @Given I am on the coursectrl dashboard for course :shortname
     * @param string $shortname Course shortname.
     */
    public function i_am_on_the_coursectrl_dashboard_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/local/coursectrl/index.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Navigate to the timeline page for a course.
     *
     * @Given I am on the timeline page for course :shortname
     * @param string $shortname Course shortname.
     */
    public function i_am_on_the_timeline_page_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/local/coursectrl/timeline.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Navigate to the timeline textreview tab for a course.
     *
     * @Given I am on the textreview tab for course :shortname
     * @param string $shortname Course shortname.
     */
    public function i_am_on_the_textreview_tab_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url(
            '/local/coursectrl/timeline.php',
            ['courseid' => $course->id, 'tab' => 'textreview']
        );
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Navigate to the manage page for the given course.
     *
     * @Given /^I am on the manage page for course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course short name.
     */
    public function i_am_on_the_manage_page_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/local/coursectrl/manage.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Navigate to the history page for the given course.
     *
     * @Given /^I am on the history page for course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course short name.
     */
    public function i_am_on_the_history_page_for_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/local/coursectrl/history.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->wait_for_pending_js();
    }

    // Shift-modal interaction steps.

    /**
     * Click the first shift-slot button visible on the timeline.
     *
     * @When I click the first slot shift button on the timeline
     */
    public function i_click_first_slot_shift_button(): void {
        $btn = $this->find('css', '[data-action="shift-slot"]');
        if ($btn === null) {
            throw new ExpectationException('No shift-slot button found on the timeline', $this->getSession());
        }
        $btn->click();
        $this->wait_for_pending_js();
    }

    /**
     * Click the first shift-following button visible on the timeline.
     *
     * @When I click the first following shift button on the timeline
     */
    public function i_click_first_following_shift_button(): void {
        $btn = $this->find('css', '[data-action="shift-following"]');
        if ($btn === null) {
            throw new ExpectationException('No shift-following button found on the timeline', $this->getSession());
        }
        $btn->click();
        $this->wait_for_pending_js();
    }

    /**
     * Click the first shift-entry button for a specific module type on the timeline.
     *
     * @When I click the entry shift button for :modtype on the timeline
     * @param string $modtype Module type, e.g. "assign", "forum".
     */
    public function i_click_entry_shift_button_for_modtype(string $modtype): void {
        // Find a shift-entry button whose parent li contains a pix element for this module.
        $selector = 'li:has(img[src*="mod_' . $modtype . '"]) [data-action="shift-entry"]';
        $btn = $this->find('css', $selector);
        if ($btn === null) {
            // Fallback: first shift-entry button on page.
            $btn = $this->find('css', '[data-action="shift-entry"]');
        }
        if ($btn === null) {
            throw new ExpectationException(
                "No shift-entry button found for module type '{$modtype}'",
                $this->getSession()
            );
        }
        $btn->click();
        $this->wait_for_pending_js();
    }

    /**
     * Set the days delta input in the open shift modal.
     *
     * @When I set the shift days to :days
     * @param int $days Number of days.
     */
    public function i_set_shift_days(int $days): void {
        $this->getSession()->executeScript(
            "document.getElementById('coursectrl-shift-delta-days').value = '" . (int) $days . "';"
        );
    }

    /**
     * Set the hours delta input in the open shift modal.
     *
     * @When I set the shift hours to :hours
     * @param int $hours Number of hours.
     */
    public function i_set_shift_hours(int $hours): void {
        $this->getSession()->executeScript(
            "document.getElementById('coursectrl-shift-delta-hours').value = '" . (int) $hours . "';"
        );
    }

    /**
     * Set the minutes delta input in the open shift modal.
     *
     * @When I set the shift minutes to :minutes
     * @param int $minutes Number of minutes.
     */
    public function i_set_shift_minutes(int $minutes): void {
        $this->getSession()->executeScript(
            "document.getElementById('coursectrl-shift-delta-minutes').value = '" . (int) $minutes . "';"
        );
    }

    /**
     * Enable the followdeps checkbox in the shift modal.
     *
     * @When I enable the followdeps checkbox in the shift modal
     */
    public function i_enable_followdeps_checkbox(): void {
        $cb = $this->find('css', '#coursectrl-shift-followdeps-cb');
        if ($cb === null) {
            throw new ExpectationException('Followdeps checkbox not found in shift modal', $this->getSession());
        }
        if (!$cb->isChecked()) {
            $cb->click();
        }
    }

    /**
     * Assert the followdeps checkbox is visible and unchecked by default.
     *
     * @Then the followdeps checkbox should be present and unchecked
     */
    public function followdeps_checkbox_should_be_unchecked(): void {
        $cb = $this->find('css', '#coursectrl-shift-followdeps-cb');
        if ($cb === null) {
            throw new ExpectationException('Followdeps checkbox not found', $this->getSession());
        }
        if ($cb->isChecked()) {
            throw new ExpectationException('Followdeps checkbox should be unchecked', $this->getSession());
        }
    }

    /**
     * Click the Preview button in the shift modal and wait for the AJAX response.
     *
     * @When I click the shift preview button and wait
     */
    public function i_click_shift_preview_and_wait(): void {
        $btn = $this->find('css', '[data-ccwf-action="preview"]');
        if ($btn === null) {
            throw new ExpectationException('Shift preview button not found', $this->getSession());
        }
        $btn->click();
        $this->getSession()->wait(4000);
        $this->wait_for_pending_js();
    }

    /**
     * Assert that the shift preview summary matches the given text fragment.
     *
     * @Then the shift preview summary should contain :text
     * @param string $text Expected text fragment (e.g. "1 Felder in 1 Aktivität").
     */
    public function shift_preview_summary_should_contain(string $text): void {
        $body = $this->find('css', '[data-ccwf-preview-body]');
        if ($body === null) {
            throw new ExpectationException('Shift preview body not found', $this->getSession());
        }
        if (strpos($body->getText(), $text) === false) {
            throw new ExpectationException(
                "Shift preview body does not contain '{$text}'. Got: " . substr($body->getText(), 0, 200),
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the preview contains an activity icon for a specific module type.
     *
     * @Then the shift preview should contain a :modtype activity icon
     * @param string $modtype Module type, e.g. "assign".
     */
    public function preview_should_contain_activity_icon(string $modtype): void {
        $img = $this->find('css', '[data-ccwf-preview-body] img[src*="mod_' . $modtype . '"]');
        if ($img === null) {
            throw new ExpectationException(
                "No '{$modtype}' activity icon found in shift preview",
                $this->getSession()
            );
        }
    }

    /**
     * Click the (i) info-toggle button in the preview for a given index (1-based).
     *
     * @When I click the preview info button :index
     * @param int $index 1-based index of the info button.
     */
    public function i_click_preview_info_button(int $index): void {
        $buttons = $this->find_all('css', '[data-ccwf-preview-body] .ccwf-preview-toggle');
        $idx = (int) $index - 1;
        if (!isset($buttons[$idx])) {
            throw new ExpectationException(
                "Preview info button #{$index} not found (found " . count($buttons) . ')',
                $this->getSession()
            );
        }
        $buttons[$idx]->click();
        $this->wait_for_pending_js();
    }

    /**
     * Enable the "text review after shift" checkbox in the preview step.
     *
     * @When I enable the text review checkbox in the shift preview
     */
    public function i_enable_text_review_checkbox(): void {
        $cb = $this->find('css', '#ccwf-scantext-cb');
        if ($cb === null) {
            throw new ExpectationException(
                'Text-review checkbox (#ccwf-scantext-cb) not found in preview step',
                $this->getSession()
            );
        }
        if (!$cb->isChecked()) {
            $cb->click();
        }
    }

    /**
     * Click "Verschiebung anwenden" and wait for the AJAX result.
     *
     * @When I apply the shift and wait
     */
    public function i_apply_shift_and_wait(): void {
        $btn = $this->find('css', '[data-ccwf-action="execute"]');
        if ($btn === null) {
            throw new ExpectationException('Execute shift button not found', $this->getSession());
        }
        $btn->click();
        $this->getSession()->wait(5000);
        $this->wait_for_pending_js();
    }

    /**
     * Assert the shift success message.
     *
     * @Then the shift modal should show :count shifted entry message
     * @param int $count Expected number of shifted entries.
     */
    public function shift_modal_should_show_success(int $count): void {
        $body = $this->find('css', '[data-ccwf-preview-body]');
        if ($body === null) {
            throw new ExpectationException('Shift preview body not found after apply', $this->getSession());
        }
        $text = $body->getText();
        if (strpos($text, (string) $count) === false) {
            throw new ExpectationException(
                "Expected count '{$count}' not found in success message. Got: " . substr($text, 0, 200),
                $this->getSession()
            );
        }
    }

    /**
     * Assert the shift modal is currently visible (display:block or visible class).
     *
     * @Then the shift modal should be visible
     */
    public function shift_modal_should_be_visible(): void {
        $dialog = $this->find('css', '#coursectrl-shift-dialog');
        if ($dialog === null) {
            throw new ExpectationException('Shift dialog element not found', $this->getSession());
        }
        $style = $dialog->getAttribute('style') ?? '';
        $classes = $dialog->getAttribute('class') ?? '';
        if (strpos($style, 'display: block') === false && strpos($classes, 'show') === false) {
            throw new ExpectationException('Shift dialog is not visible', $this->getSession());
        }
    }

    /**
     * Assert the shift modal is closed (hidden).
     *
     * @Then the shift modal should be closed
     */
    public function shift_modal_should_be_closed(): void {
        $dialog = $this->find('css', '#coursectrl-shift-dialog');
        if ($dialog === null) {
            return; // Already gone from DOM = closed.
        }
        $style = $dialog->getAttribute('style') ?? '';
        if (strpos($style, 'display: none') !== false || strpos($style, 'display:none') !== false) {
            return;
        }
        $classes = $dialog->getAttribute('class') ?? '';
        if (strpos($classes, 'show') !== false) {
            throw new ExpectationException('Shift dialog is still visible (has class "show")', $this->getSession());
        }
    }

    /**
     * Assert that the text review modal (step 3) is visible.
     *
     * @Then the text review step should be visible in the shift modal
     */
    public function text_review_step_should_be_visible(): void {
        $step3 = $this->find('css', '[data-ccwf-step="3"]:not(.d-none)');
        if ($step3 === null) {
            throw new ExpectationException(
                'Text review step 3 is not visible in the shift modal',
                $this->getSession()
            );
        }
    }

    /**
     * Click the first (i) context button in the text review step and wait.
     *
     * @When I click the first context button in the text review step
     */
    public function i_click_first_context_button_in_text_review(): void {
        $btn = $this->find('css', '[data-ccwf-step="3"] .ccwf-ctx-btn');
        if ($btn === null) {
            throw new ExpectationException('No context button found in text review step', $this->getSession());
        }
        $btn->click();
        $this->wait_for_pending_js();
    }

    /**
     * Select the first N text hit checkboxes in the text review step.
     *
     * @When I select the first :count text hit checkboxes
     * @param int $count Number of checkboxes to check.
     */
    public function i_select_first_text_hit_checkboxes(int $count): void {
        $checkboxes = $this->find_all('css', '[data-ccwf-step="3"] .ccwf-hit-cb');
        $n = min((int) $count, count($checkboxes));
        for ($i = 0; $i < $n; $i++) {
            if (!$checkboxes[$i]->isChecked()) {
                $checkboxes[$i]->click();
            }
        }
    }

    /**
     * Click "Ausgewählte Textänderungen anwenden" and wait.
     *
     * @When I apply the selected text changes and wait
     */
    public function i_apply_selected_text_changes(): void {
        $btn = $this->find('css', '[data-ccwf-action="apply-text"]');
        if ($btn === null) {
            throw new ExpectationException('Apply-text button not found', $this->getSession());
        }
        $btn->click();
        $this->getSession()->wait(5000);
        $this->wait_for_pending_js();
    }

    // Text-review tab interaction steps.

    /**
     * Assert that an activity icon for a given module type is present in
     * the text review table on the timeline Textprüfung tab.
     *
     * @Then the textreview table should contain a :modtype icon
     * @param string $modtype Module type, e.g. "assign".
     */
    public function textreview_table_should_contain_icon(string $modtype): void {
        $img = $this->find('css', '#coursectrl-textreview-table img[src*="mod_' . $modtype . '"]');
        if ($img === null) {
            throw new ExpectationException(
                "No '{$modtype}' icon found in text review table",
                $this->getSession()
            );
        }
    }

    /**
     * Set the text review delta days input (spinner) by clicking the up/down arrow
     * or by directly setting the value via JS.
     *
     * @When I set the textreview delta to :days days
     * @param int $days Delta in days (positive or negative).
     */
    public function i_set_textreview_delta_days(int $days): void {
        $this->getSession()->executeScript(
            "var el = document.getElementById('coursectrl-textreview-delta-days');"
            . "if (el) { el.value = '" . (int) $days . "'; el.dispatchEvent(new Event('change')); }"
        );
    }

    /**
     * Apply text changes from the Textprüfung tab (not from the shift modal).
     *
     * @When I apply text changes from the textreview tab and wait
     */
    public function i_apply_text_changes_from_tab(): void {
        $btn = $this->find('css', '#coursectrl-textreview-apply-btn');
        if ($btn === null) {
            throw new ExpectationException('Text review apply button not found on tab', $this->getSession());
        }
        $btn->click();
        $this->getSession()->wait(1000); // Confirmation modal opens.
        $this->wait_for_pending_js();
        // Confirm in the modal if it appears.
        $confirmbtn = $this->find('css', '[data-action="confirm-apply-text"]');
        if ($confirmbtn !== null) {
            $confirmbtn->click();
            $this->getSession()->wait(5000);
            $this->wait_for_pending_js();
        }
    }

    // Database assertion steps.

    /**
     * Assert that the duedate of an assignment was shifted by a given number of days.
     * The original duedate is read from the DB and compared to the shifted expectation.
     *
     * @Then the duedate of assign :name in course :shortname should be shifted by :days day(s) from :original_ts
     * @param string $name        Assignment name.
     * @param string $shortname   Course shortname.
     * @param int    $days        Number of days to shift.
     * @param int    $originalts  Original Unix timestamp to compute expected value from.
     */
    public function duedate_should_be_shifted(
        string $name,
        string $shortname,
        int $days,
        int $originalts
    ): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $assign = $DB->get_record('assign', ['course' => $course->id, 'name' => $name], 'id, duedate', MUST_EXIST);
        $expected = $originalts + ($days * DAYSECS);
        $actual   = (int) $assign->duedate;
        if (abs($actual - $expected) > 60) { // 60 s tolerance for DST edge cases.
            throw new ExpectationException(
                "Expected duedate ~{$expected}, got {$actual} (diff " . ($actual - $expected) . ' s)',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the completionexpected of a course module was shifted by a given
     * number of days from a known original timestamp.
     *
     * @Then the completionexpected of :modtype :name in course :shortname should be shifted by :days day(s) from :original_ts
     * @param string $modtype     Module type (e.g. "forum").
     * @param string $name        Module instance name.
     * @param string $shortname   Course shortname.
     * @param int    $days        Number of days to shift.
     * @param int    $originalts  Original Unix timestamp.
     */
    public function completionexpected_should_be_shifted(
        string $modtype,
        string $name,
        string $shortname,
        int $days,
        int $originalts
    ): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $instance = $DB->get_record($modtype, ['course' => $course->id, 'name' => $name], 'id', MUST_EXIST);
        $module   = $DB->get_record('modules', ['name' => $modtype], 'id', MUST_EXIST);
        $cm = $DB->get_record(
            'course_modules',
            ['course' => $course->id, 'module' => $module->id, 'instance' => $instance->id],
            'id, completionexpected',
            MUST_EXIST
        );
        $expected = $originalts + ($days * DAYSECS);
        $actual   = (int) $cm->completionexpected;
        if (abs($actual - $expected) > 60) {
            throw new ExpectationException(
                "Expected completionexpected ~{$expected}, got {$actual}",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the duedate of an assignment is still the original value (not shifted).
     *
     * @Then the duedate of assign :name in course :shortname should still be :original_ts
     * @param string $name       Assignment name.
     * @param string $shortname  Course shortname.
     * @param int    $originalts Expected original Unix timestamp.
     */
    public function duedate_should_still_be(string $name, string $shortname, int $originalts): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $assign = $DB->get_record('assign', ['course' => $course->id, 'name' => $name], 'id, duedate', MUST_EXIST);
        $actual = (int) $assign->duedate;
        if (abs($actual - $originalts) > 60) {
            throw new ExpectationException(
                "Expected duedate to remain {$originalts}, but got {$actual}",
                $this->getSession()
            );
        }
    }

    /**
     * Set completionexpected for a course module directly in the database.
     * Needed because Behat generators do not expose this CM-level field.
     *
     * @Given the completionexpected of :modtype :name in course :shortname is :timestamp
     * @param string $modtype   Module type.
     * @param string $name      Module instance name.
     * @param string $shortname Course shortname.
     * @param int    $timestamp Unix timestamp to set.
     */
    public function set_completionexpected(
        string $modtype,
        string $name,
        string $shortname,
        int $timestamp
    ): void {
        global $DB;
        $course   = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $instance = $DB->get_record($modtype, ['course' => $course->id, 'name' => $name], 'id', MUST_EXIST);
        $module   = $DB->get_record('modules', ['name' => $modtype], 'id', MUST_EXIST);
        $cm = $DB->get_record(
            'course_modules',
            ['course' => $course->id, 'module' => $module->id, 'instance' => $instance->id],
            'id',
            MUST_EXIST
        );
        $DB->set_field('course_modules', 'completionexpected', $timestamp, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);
    }
}
