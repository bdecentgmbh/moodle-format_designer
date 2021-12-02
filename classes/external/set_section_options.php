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
 * Set section options web service function.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use external_function_parameters;
use external_single_structure;
use external_value;
use format_designer;

require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot.'/course/format/lib.php');

/**
 * Set section options web service function.
 */
trait set_section_options {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function set_section_options_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionid' => new external_value(PARAM_INT, 'Section Id'),
            'options' => new \external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Option name to set on section'),
                'value' => new external_value(PARAM_RAW, 'Value for option')
            ]))
        ]);
    }

    /**
     * Set section options web service function.
     *
     * @param int $courseid course id
     * @param int $sectionid section id
     * @param array $options
     */
    public static function set_section_options(int $courseid, int $sectionid, array $options) {
        global $DB, $PAGE;
        $context = context_course::instance($courseid);
        $PAGE->set_context($context);
        $params = self::validate_parameters(self::set_section_options_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'options' => $options
        ]);
        $course = $DB->get_record('course', ['id' => $params['courseid']]);
        /** @var format_designer $format */
        $format = course_get_format($course);
        require_capability('format/designer:changesectionoptions', $context);
        foreach ($params['options'] as $option) {
            $format->set_section_option($params['sectionid'], $option['name'], $option['value']);
        }
        return null;
    }

    /**
     * Describes the parameters for move activities return
     *
     * @return external_single_structure
     */
    public static function set_section_options_returns() {
        return new external_value(PARAM_RAW, 'Section html');
    }
}
