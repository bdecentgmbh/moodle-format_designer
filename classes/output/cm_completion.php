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
 * Privacy Subsystem implementation for Designer course format.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output;

use cm_info;
use coding_exception;
use completion_info;
use core_availability\info;
use html_writer;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/course/format/designer/lib.php");


/**
 * Privacy Subsystem for Designer course format implementing null_provider.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_completion implements renderable, templatable {

    /**
     * @var cm_info
     */
    private $cm;

    private static $completioninfos = [];

    /**
     * Constructor.
     *
     * @param cm_info $cm
     */
    public function __construct(cm_info $cm) {
        $this->cm = $cm;
    }

    /**
     * Check if completion info should be displayed at all.
     *
     * @return bool
     * @throws coding_exception
     */
    public final function is_visible(): bool {
        if (!isloggedin() || isguestuser() || !$this->cm->uservisible ||
            $this->get_completion_mode() == COMPLETION_TRACKING_NONE) {
            return false;
        }

        if ($this->get_completion_state() == COMPLETION_INCOMPLETE && !$this->get_completion_expected()) {
            return false;
        }

        return true;
    }

    /**
     * Get completion info object for this cm's course.
     *
     * @return completion_info
     */
    public final function get_completion_info(): completion_info {
        if (!isset(self::$completioninfos[$this->cm->course])) {
            self::$completioninfos[$this->cm->course] = new completion_info($this->cm->get_course());
        }

        return self::$completioninfos[$this->cm->course];
    }

    /**
     * Get completion info for this cm.
     *
     * @return stdClass
     */
    public final function get_completion_data(): stdClass {
        return $this->get_completion_info()->get_data($this->cm, true);
    }

    /**
     * Get completion mode:
     *
     * - COMPLETION_TRACKING_NONE
     * - COMPLETION_TRACKING_MANUAL
     * - COMPLETION_TRACKING_AUTOMATIC
     *
     * @return int
     */
    public final function get_completion_mode(): int {
        $mode = $this->get_completion_info()->is_enabled($this->cm);
        return $mode;
    }

    /**
     * Get completion state:
     *
     * - COMPLETION_INCOMPLETE
     * - COMPLETION_COMPLETE
     * - COMPLETION_COMPLETE_PASS
     * - COMPLETION_COMPLETE_FAIL
     *
     * @return int
     */
    public final function get_completion_state(): int {
        return $this->get_completion_data()->completionstate;
    }

    /**
     * Check if user is tracked for this cm.
     *
     * @param int|null $userid
     * @return bool
     */
    public final function is_tracked_users(int $userid = null): bool {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->get_completion_info()->is_tracked_user($userid);
    }

    /**
     * Check if user is editing the course.
     *
     * @return bool
     */
    public final function is_editing(): bool {
        global $PAGE;
        return $PAGE->user_is_editing();
    }

    /**
     * Check if user completion state was overridden by another user (teacher, admin, etc).
     *
     * @return bool
     */
    public final function is_overridden(): bool {
        return isset($this->get_completion_data()->overrideby) && !empty($this->get_completion_data()->overrideby);
    }

    /**
     * Get date cm was completed. Basing off completion date. Also check completion state.
     *
     * @return int
     */
    public final function get_completion_date(): int {
        return $this->get_completion_data()->timemodified;
    }

    /**
     * Get when cm must be completed by.
     *
     * @return int
     */
    public final function get_completion_expected(): int {
        return $this->cm->completionexpected;
    }

    /**
     * Get user that overrode completion state of user.
     *
     * @see cm_completion::is_overridden()
     *
     * @return stdClass|null
     * @throws \dml_exception
     */
    public final function get_override_user(): ?stdClass {
        if ($user = \core_user::get_user($this->get_completion_data()->overrideby, '*', IGNORE_MISSING)) {
            $user->fullname = fullname($user);
            return $user;
        }
        return null;
    }

    /**
     * Check if cm is overdue for user.
     *
     * @return bool
     */
    public final function is_overdue(): bool {
        return $this->get_completion_expected() > 0 && $this->get_completion_expected() + 86400 < time();
    }

    /**
     * Get time ago since cm was overdue.
     *
     * @return string
     */
    public final function get_overdue_by(): string {
        return $this->get_time_ago($this->get_completion_expected());
    }

    /**
     * Check if cm is due within a day.
     *
     * @return bool
     */
    public final function is_due_today(): bool {
        return $this->get_completion_expected() > 0 && $this->get_completion_expected() > time() &&
             $this->get_completion_expected() - time() < 86400;
    }

    /**
     * Get manual completion checkbox. Note: Mostly legacy code from Moodle.
     *
     * @return string
     * @throws \dml_exception
     * @throws coding_exception
     */
    public final function get_completion_checkbox(): string {
        global $OUTPUT;

        if ($this->get_completion_state() == COMPLETION_INCOMPLETE) {
            $completionicon = 'manual-n' . ($this->get_completion_data()->overrideby ? '-override' : '');
        } elseif ($this->get_completion_state() == COMPLETION_COMPLETE) {
            $completionicon = 'manual-y' . ($this->get_completion_data()->overrideby ? '-override' : '');
        }

        if ($this->is_overridden()) {
            $args = new stdClass();
            $args->modname = $this->get_cm_formatted_name();
            if ($overridebyuser = $this->get_override_user()) {
                $args->overrideuser = fullname($overridebyuser);
            }
            $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $args);
        } else {
            $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $this->get_cm_formatted_name());
        }

        $output = '';
        $newstate =
            $this->get_completion_state() == COMPLETION_COMPLETE
                ? COMPLETION_INCOMPLETE
                : COMPLETION_COMPLETE;
        // In manual mode the icon is a toggle form...

        // If this completion state is used by the
        // conditional activities system, we need to turn
        // off the JS.
        $extraclass = '';
        if (!empty($CFG->enableavailability) &&
            info::completion_value_used($this->cm->get_course(), $this->cm->id)) {
            $extraclass = ' preventjs';
        }
        $output .= html_writer::start_tag('form', array('method' => 'post',
            'action' => new moodle_url('/course/togglecompletion.php'),
            'class' => 'togglecompletion'. $extraclass));
        $output .= html_writer::start_tag('div');
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden', 'name' => 'id', 'value' => $this->cm->id));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden', 'name' => 'modulename', 'value' => $this->get_cm_formatted_name()));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden', 'name' => 'completionstate', 'value' => $newstate));
        $output .= html_writer::tag('button',
            $OUTPUT->pix_icon('i/completion-' . $completionicon, $imgalt),
            array('class' => 'btn btn-link', 'aria-live' => 'assertive'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Get formatted name for cm to use within strings or tooltips.
     *
     * @return string
     */
    public final function get_cm_formatted_name(): string {
        return html_entity_decode($this->cm->get_formatted_name(), ENT_QUOTES, 'UTF-8');
    }

    public function export_for_template(renderer_base $output) {
        $data = [
            'istrackeduser' => $this->is_tracked_users(),
            'isediting' => $this->is_editing(),
            'ispreview' => $this->is_editing() || !$this->is_tracked_users(),
            'isoverridden' => $this->is_overridden(),
            'overrideuser' => $this->get_override_user(),
            'isoverdue' => $this->is_overdue(),
            'overdueby' => $this->get_overdue_by(),
            'duetoday' => $this->is_due_today(),
            'completioncheckbox' => $this->get_completion_checkbox(),
            'completiontrackingmanual' => $this->get_completion_mode() == COMPLETION_TRACKING_MANUAL,
            'completiontrackingautomatic' => $this->get_completion_mode() == COMPLETION_TRACKING_AUTOMATIC,
            'completionincomplete' => $this->get_completion_state() == COMPLETION_INCOMPLETE,
            'completioncomplete' => $this->get_completion_state() == COMPLETION_COMPLETE,
            'completionincompletepass' => $this->get_completion_state() == COMPLETION_COMPLETE_PASS,
            'completionincompletefail' => $this->get_completion_state() == COMPLETION_COMPLETE_FAIL
        ];

        if ($completiondate = $this->get_completion_date()) {
            $data['completiondate'] = format_designer_format_date($completiondate);
        }

        if ($completionexpected = $this->get_completion_expected()) {
            $data['completionexpected'] = format_designer_format_date($completionexpected);
        }

        return $data;
    }

    private function get_time_ago(int $timestamp): string {
        $now = new \DateTime();
        $ago = new \DateTime('@' . $timestamp);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => get_string('timeagoyear', 'format_designer'),
            'm' => get_string('timeagomonth', 'format_designer'),
            'w' => get_string('timeagoweek', 'format_designer'),
            'd' => get_string('timeagoday', 'format_designer'),
            'h' => get_string('timeagohour', 'format_designer'),
            'i' => get_string('timeagominute', 'format_designer'),
            's' => get_string('timeagosecond', 'format_designer'),
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ' . get_string('timeago', 'format_designer') : get_string('timeagojustnow', 'format_designer');
    }
}
