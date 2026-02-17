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
 * Contains the default section navigation output class.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output\courseformat\content;

use renderer_base;
use stdClass;
use context_course;

/**
 * Base class to render a course section.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionnavigation extends \core_courseformat\output\local\content\sectionnavigation {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $USER;

        if ($this->data !== null) {
            return $this->data;
        }

        $format = $this->format;
        $course = $format->get_course();
        $context = context_course::instance($course->id);

        $modinfo = $this->format->get_modinfo();
        $sections = $modinfo->get_section_info_all();

        // FIXME: This is really evil and should by using the navigation API.
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context, $USER);

        $data = (object)[
            'previousurl' => '',
            'nexturl' => '',
            'larrow' => $output->larrow(),
            'rarrow' => $output->rarrow(),
            'currentsection' => $this->sectionno,
        ];

        $back = $this->sectionno - 1;
        while ($back >= 0 && empty($data->previousurl)) {
            if ($canviewhidden || $sections[$back]->uservisible) {
                if (!$sections[$back]->visible) {
                    $data->previoushidden = true;
                }
                $data->previousname = get_section_name($course, $sections[$back]);
                $data->previousurl = new \moodle_url("/course/section.php", ['id' => $sections[$back]->id]);
                $data->hasprevious = true;
            }
            $back--;
        }

        $forward = $this->sectionno + 1;
        $numsections = course_get_format($course)->get_last_section_number();
        while ($forward <= $numsections && empty($data->nexturl)) {
            if ($canviewhidden || $sections[$forward]->uservisible) {
                if (!$sections[$forward]->visible) {
                    $data->nexthidden = true;
                }
                $data->nextname = get_section_name($course, $sections[$forward]);
                $data->nexturl = new \moodle_url("/course/section.php", ['id' => $sections[$forward]->id]);
                $data->hasnext = true;
            }
            $forward++;
        }

        $this->data = $data;
        return $data;
    }
}
