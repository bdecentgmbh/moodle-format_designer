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

/**
 * Designer format event observer.
 */
class events {

    /**
     * Observer section created event and insert section format options into DB;
     *
     * @param object $event
     * @return void
     */
    public static function course_section_created($event) {
        $data = $event->get_data();
        $sectionid = $data['objectid'];
        $sectionnum = $data['other']['sectionnum'];
        $contextid = $data['contextid'];
        $courseid = $data['courseid'];;
        $filearea = 'sectiondesignbackground';
        $option = get_config('format_designer', 'sectiondesignerbackgroundimage');
        $coursecontext = \context_course::instance($courseid);
        if (course_get_format($courseid)->get_course()->format !== 'designer') {
            return true;
        }

        $format = course_get_format($courseid);
        $options = $format->section_format_options();
        $sectiondata = ['id' => $sectionid];
        foreach ($options as $name => $option) {
            $sectiondata[$name] = get_config('format_designer', $name);
        }

        if (!defined('NO_OUTPUT_BUFFERING') || (defined('NO_OUTPUT_BUFFERING') && !NO_OUTPUT_BUFFERING)) {
            $format->update_section_format_options($sectiondata);
        }
    }
}
