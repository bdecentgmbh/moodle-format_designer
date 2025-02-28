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
 * Designer events handle for section created. Add default values to the sections.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/course/format/designer/lib.php");


/**
 * Designer format event observer.
 */
class events {

    /**
     * After new section created, section format options are not added to the DB.
     * Observe the section creation and add global format options to section in dB.
     *
     * @param object $event
     * @return void
     */
    public static function course_section_created($event) {

        $data = $event->get_data();
        $sectionid = $data['objectid'];
        $sectionnum = $data['other']['sectionnum'];
        $contextid = $data['contextid'];
        $courseid = $data['courseid'];
        $filearea = 'sectiondesignbackground';
        $option = get_config('format_designer', 'sectiondesignerbackgroundimage');
        $coursecontext = \context_course::instance($courseid);

        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }

        // Course_section_cache_updated.
        self::course_section_cache_updated($courseid, $sectionid);

        $format = course_get_format($courseid);
        $options = $format->section_format_options();
        $sectiondata = ['id' => $sectionid];
        foreach ($options as $name => $option) {
            if (get_config('format_designer', $name)) {
                $sectiondata[$name] = get_config('format_designer', $name);
            }
        }
        if (!defined('NO_OUTPUT_BUFFERING') || (defined('NO_OUTPUT_BUFFERING') && !NO_OUTPUT_BUFFERING)
            && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0')) {
            $format->update_section_format_options($sectiondata);
        }
    }

    /**
     * After course deleted, deleted the format_designer_options data related to the format_designer options.
     *
     * @param object $event
     * @return void
     */
    public static function course_deleted($event) {
        global $DB;
        $courseid = $event->courseid;
        $DB->delete_records('format_designer_options', ['courseid' => $courseid]);
        $cache = format_designer_get_cache_object();
        $cache->delete_prerequisites_courses();
        self::course_cache_updated($courseid);
    }

    /**
     * Update the cache for the course.
     * @param mixed $event
     * @return void
     */
    public static function course_completion_updated($event) {
        $data = $event->get_data();
        $courseid = $data['courseid'];
        self::course_cache_updated($courseid);
    }

    /**
     * Update the cache for the course.
     * @param mixed $event
     * @return bool
     */
    public static function course_updated($event) {
        $courseid = $event->courseid;
        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }
        self::course_cache_updated($courseid);
    }

    /**
     * Update the cache for the course completed.
     * @param mixed $event
     * @return void
     */
    public static function course_completed($event) {
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        self::course_user_cache_updated($courseid, $userid);
    }
    /**
     * Updated the cache for the course.
     * @param mixed $event
     * @return void
     */
    public static function course_module_completion_updated($event) {
        global $DB;
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        self::course_user_cache_updated($courseid, $userid);
        $records = $DB->get_records('course_completion_criteria', ['courseinstance' => $courseid]);
        if ($records) {
            foreach ($records as $record) {
                if ($record) {
                    self::course_user_cache_updated($record->course, $userid);
                }
            }
        }
    }

    /**
     * Updated the cache for the course.
     * @param mixed $event
     * @return void
     */
    public static function course_module_created($event) {
        self::course_section_module_cache_updated($event->courseid, $event->objectid);
    }

    /**
     * After course module deleted, deleted the format_designer_options data related to the format_designer options.
     *
     * @param object $event
     * @return void
     */
    public static function course_module_deleted($event) {
        global $DB;
        $courseid = $event->courseid;
        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }
        $cmid = $event->objectid;
        $DB->delete_records('format_designer_options', ['courseid' => $courseid, 'cmid' => $cmid]);

        // Clear cache.
        self::course_section_module_cache_updated($event->courseid, $event->objectid);
    }

    /**
     * Update the cache when course module updated.
     * @param mixed $event
     * @return void
     */
    public static function course_module_updated($event) {
        self::course_section_module_cache_updated($event->courseid, $event->objectid);
    }

    /**
     * Update the cache when course section deleted.
     * @param mixed $event
     * @return void
     */
    public static function course_section_deleted($event) {
        $data = $event->get_data();
        $sectionid = $data['objectid'];
        $courseid = $data['courseid'];
        self::course_section_cache_updated($courseid, $sectionid);
    }

    /**
     * Update the cache when course section updated.
     * @param mixed $event
     * @return void
     */
    public static function course_section_updated($event) {
        $data = $event->get_data();
        $sectionid = $data['objectid'];
        $courseid = $data['courseid'];
        self::course_section_cache_updated($courseid, $sectionid);
    }

    /**
     * Update the cache when course updated.
     * @param mixed $courseid
     * @return void
     */
    public static function course_cache_updated($courseid) {
        $cache = format_designer_get_cache_object();
        $cache->delete_vaild_section_completed_cache($courseid);
        $cache->delete_user_section_completed_cache($courseid);
        $cache->delete_course_progress_uncompletion_criteria($courseid);
        $cache->delete_due_overdue_activities_count($courseid);
        $cache->delete_criteria_progress($courseid);
        $cache->delete("g_c_a{$courseid}");
        $cache->delete("g_c_s_ic{$courseid}");
    }

    /**
     * Update the cache when section module updated.
     * @param mixed $courseid
     * @param mixed $userid
     * @return void
     */
    public static function course_user_cache_updated($courseid , $userid) {
        $cache = format_designer_get_cache_object();
        $cache->delete_vaild_section_completed_cache($courseid);
        $cache->delete_user_section_completed_cache($courseid);
        $cache->delete_course_progress_uncompletion_criteria($courseid, $userid);
        $cache->delete_due_overdue_activities_count($courseid, $userid);
        $cache->delete_criteria_progress($courseid, $userid);
        $cache->delete("g_c_a{$courseid}");
        $cache->delete("g_c_s_ic{$courseid}");
    }

    /**
     * Update the cache when section module updated.
     * @param mixed $courseid
     * @param mixed $cmid
     * @param mixed $sectionid
     * @return bool
     */
    public static function course_section_module_cache_updated($courseid, $cmid, $sectionid = 0) {
        global $DB;

        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }

        $cm = $DB->get_record("course_modules", ['id' => $cmid]);
        // Clear cache.
        $cache = format_designer_get_cache_object();
        $cache->delete_vaild_section_completed_cache($courseid);
        $cache->delete_user_section_completed_cache($courseid);
        $cache->delete_course_progress_uncompletion_criteria($courseid);
        $cache->delete_due_overdue_activities_count($courseid);
        $cache->delete_criteria_progress($courseid);
        $cache->delete("fdo_cm_j_{$courseid}");
        $cache->delete("g_c_a{$courseid}");
        $cache->delete("g_c_s_ic{$courseid}");
    }

    /**
     * Update the cache when section updated.
     * @param mixed $courseid
     * @param mixed $sectionid
     * @return bool
     */
    public static function course_section_cache_updated($courseid, $sectionid) {
        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }
        // Clear cache.
        $cache = format_designer_get_cache_object();
        $cache->delete_vaild_section_completed_cache($courseid, $sectionid);
        $cache->delete_user_section_completed_cache($courseid, $sectionid);
        $cache->delete_due_overdue_activities_count($courseid);
        $cache->delete_course_progress_uncompletion_criteria($courseid);
        $cache->delete_criteria_progress($courseid);
        $cache->delete("g_c_a{$courseid}");
        $cache->delete("g_c_s_ic{$courseid}");
    }
}
