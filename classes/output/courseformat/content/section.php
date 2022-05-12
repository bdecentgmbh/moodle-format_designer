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
 * Contains the default section course format output class.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output\courseformat\content;

use renderer_base;
use stdClass;

/**
 * Base class to render a course section.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends \core_courseformat\output\local\content\section {

    /**
     * Add the section format attributes to the data structure.
     *
     * @param stdClass $data the current cm data reference
     * @param bool[] $haspartials the result of loading partial data elements
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return bool if the cm has name data
     */
    protected function add_format_data(stdClass &$data, array $haspartials, renderer_base $output): bool {
        global $PAGE;

        $section = $this->section;
        $format = $this->format;

        $data->iscoursedisplaymultipage = ($format->get_course_display() == COURSE_DISPLAY_MULTIPAGE);

        if ($data->num === 0 && !$data->iscoursedisplaymultipage) {
            $data->collapsemenu = true;
        }

        $data->contentcollapsed = false;
        $preferences = $format->get_sections_preferences();
        if (isset($preferences[$section->id])) {
            $sectionpreferences = $preferences[$section->id];
            if (!empty($sectionpreferences->contentcollapsed)) {
                $data->contentcollapsed = true;
            }
        }

        if ($format->is_section_current($section)) {
            $data->iscurrent = true;
            $data->currentlink = get_accesshide(
                get_string('currentsection', 'format_' . $format->get_format())
            );
        }

        $renderer = $this->format->get_renderer($PAGE);
        if ($data->iscoursedisplaymultipage && !$format->get_section_number()) {
            $formatdata = (array) $renderer->render_section_data($this->section, $this->format->get_course(), false, true);
        } else {
            $formatdata = (array) $renderer->render_section_data($this->section, $this->format->get_course(), false);
        }
        $data = (object) array_merge((array) $data, $formatdata);

        return true;
    }
}
