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
 * Admin settings for local_coursectrl.
 *
 * Sections:
 *   1. Calendar providers — enable/disable and configure each coursectrlcal_* adapter.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_coursectrl',
        get_string('pluginname', 'local_coursectrl')
    );
    $ADMIN->add('localplugins', $settings);

    // Nager.Date settings.
    $settings->add(new admin_setting_heading(
        'local_coursectrl/calnager_heading',
        get_string('settings_calnager_heading', 'local_coursectrl'),
        get_string('settings_calnager_desc', 'local_coursectrl')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_coursectrl/calnager_enabled',
        get_string('settings_calnager_enabled', 'local_coursectrl'),
        '',
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calnager_countrycode',
        get_string('settings_calnager_countrycode', 'local_coursectrl'),
        get_string('settings_calnager_countrycode_desc', 'local_coursectrl'),
        'DE',
        PARAM_ALPHANUMEXT,
        5
    ));

    // OpenHolidays API settings.
    $settings->add(new admin_setting_heading(
        'local_coursectrl/calopenholidays_heading',
        get_string('settings_calopenholidays_heading', 'local_coursectrl'),
        get_string('settings_calopenholidays_desc', 'local_coursectrl')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_coursectrl/calopenholidays_enabled',
        get_string('settings_calopenholidays_enabled', 'local_coursectrl'),
        '',
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calopenholidays_countryisocode',
        get_string('settings_calopenholidays_countryisocode', 'local_coursectrl'),
        get_string('settings_calopenholidays_countryisocode_desc', 'local_coursectrl'),
        'DE',
        PARAM_ALPHANUMEXT,
        5
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calopenholidays_languageisocode',
        get_string('settings_calopenholidays_languageisocode', 'local_coursectrl'),
        get_string('settings_calopenholidays_languageisocode_desc', 'local_coursectrl'),
        'DE',
        PARAM_ALPHANUMEXT,
        5
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calopenholidays_regioncode',
        get_string('settings_calopenholidays_regioncode', 'local_coursectrl'),
        get_string('settings_calopenholidays_regioncode_desc', 'local_coursectrl'),
        '',
        PARAM_TEXT,
        10
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calopenholidays_categories',
        get_string('settings_calopenholidays_categories', 'local_coursectrl'),
        get_string('settings_calopenholidays_categories_desc', 'local_coursectrl'),
        'public_holiday,school_holiday',
        PARAM_TEXT
    ));

    // Manual free days settings.
    $settings->add(new admin_setting_heading(
        'local_coursectrl/calmanual_heading',
        get_string('settings_calmanual_heading', 'local_coursectrl'),
        get_string('settings_calmanual_desc', 'local_coursectrl')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_coursectrl/calmanual_enabled',
        get_string('settings_calmanual_enabled', 'local_coursectrl'),
        '',
        0
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_coursectrl/calmanual_entries',
        get_string('settings_calmanual_entries', 'local_coursectrl'),
        get_string('settings_calmanual_entries_desc', 'local_coursectrl'),
        ''
    ));

    // Moodle calendar reader settings.
    $settings->add(new admin_setting_heading(
        'local_coursectrl/calmoodlecal_heading',
        get_string('settings_calmoodlecal_heading', 'local_coursectrl'),
        get_string('settings_calmoodlecal_desc', 'local_coursectrl')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_coursectrl/calmoodlecal_enabled',
        get_string('settings_calmoodlecal_enabled', 'local_coursectrl'),
        '',
        0
    ));
    $settings->add(new admin_setting_configmulticheckbox(
        'local_coursectrl/calmoodlecal_eventtype',
        get_string('settings_calmoodlecal_eventtype', 'local_coursectrl'),
        get_string('settings_calmoodlecal_eventtype_desc', 'local_coursectrl'),
        ['site' => 1],
        [
            'site'     => get_string('settings_calmoodlecal_eventtype_site', 'local_coursectrl'),
            'category' => get_string('settings_calmoodlecal_eventtype_category', 'local_coursectrl'),
            'user'     => get_string('settings_calmoodlecal_eventtype_user', 'local_coursectrl'),
        ]
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calmoodlecal_namepattern',
        get_string('settings_calmoodlecal_namepattern', 'local_coursectrl'),
        get_string('settings_calmoodlecal_namepattern_desc', 'local_coursectrl'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursectrl/calmoodlecal_category',
        get_string('settings_calmoodlecal_category', 'local_coursectrl'),
        get_string('settings_calmoodlecal_category_desc', 'local_coursectrl'),
        'custom',
        PARAM_ALPHANUMEXT
    ));
}
