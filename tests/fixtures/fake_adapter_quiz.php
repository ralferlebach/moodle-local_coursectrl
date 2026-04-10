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

/*
 * Test fixture: available fake adapter targeting mod_quiz.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || exit;

require_once __DIR__.'/fake_adapter_base.php';

/**
 * Available fake adapter targeting mod_quiz.
 */
class local_coursectrl_fake_adapter_quiz extends local_coursectrl_fake_adapter_base
{
    /**
     * Returns the target component name.
     */
    public static function component(): string
    {
        return 'mod_quiz';
    }
}
