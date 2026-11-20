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

namespace format_designer;

/**
 * Hook callbacks for format_designer.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Tell site admins that Designer Pro needs to be updated, when it is too old for this format.
     *
     * Shown on admin pages and in Designer courses, in the page's notification area.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE;

        if (during_initial_install() || !empty($CFG->upgraderunning) || !is_siteadmin()) {
            return;
        }
        if ($PAGE->pagelayout !== 'admin' && ($PAGE->course->format ?? '') !== 'designer') {
            return;
        }
        if (!helper::pro_outdated()) {
            return;
        }
        \core\notification::warning(get_string('prooutdated', 'format_designer', helper::MIN_PRO_RELEASE));
    }
}
