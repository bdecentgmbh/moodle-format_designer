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

namespace format_designer\output;

use core_courseformat\output\section_renderer;
use core_courseformat\base as course_format;
use cm_info;
use section_info;

/**
 * Basic renderer for designer format.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {

    /**
     * Renders the content widget.
     * @param renderable $widget instance with renderable interface
     * @return string the widget HTML
     */
    public function render_content($widget) {
        return $this->render_from_template('format_designer/courseformat/content/section',
        $widget->export_for_template($this));
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Get the updated rendered version of a cm list item.
     *
     * This method is used when an activity is duplicated or copied in on the client side without refreshing the page.
     * It replaces the course renderer course_section_cm_list_item method but it's scope is different.
     * Note that the previous method is used every time an activity is rendered, independent of it is the initial page
     * loading or an Ajax update. In this case, course_section_updated_cm_item will only be used when the course editor
     * requires to get an updated cm item HTML to perform partial page refresh. It will be used for suporting the course
     * editor webservices.
     *
     * By default, the template used for update a cm_item is the same as when it renders initially, but format plugins are
     * free to override this methos to provide extra affects or so.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @param cm_info $cm the course module ionfo
     * @param array $displayoptions optional extra display options
     * @return string the rendered element
     */
    public function course_section_updated_cm_item(
        course_format $format,
        section_info $section,
        cm_info $cm,
        array $displayoptions = []
    ) {
        $cmlistclass = $format->get_output_classname('content\\section\\cmlist');
        $cmlist = new $cmlistclass($format, $section, $displayoptions);
        $output = $this->page->get_renderer('format_designer');
        $cmlistdata = $cmlist->render_course_module($output, $cm);
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        $templatename = 'format_designer/layout/cm/module_layout_' . $sectiontype;
        return $this->render_from_template($templatename, $cmlistdata);
    }

    /**
     * Get the updated rendered version of a section.
     *
     * This method will only be used when the course editor requires to get an updated cm item HTML
     * to perform partial page refresh. It will be used for supporting the course editor webservices.
     *
     * By default, the template used for update a section is the same as when it renders initially,
     * but format plugins are free to override this method to provide extra effects or so.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @return string the rendered element
     */
    public function course_section_updated(
        course_format $format,
        section_info $section
    ): string {
        $output = $this->page->get_renderer('format_designer');
        $sectionclass = $format->get_output_classname('content\\section');
        $sectionobj = new $sectionclass($format, $section);
        return $this->render_from_template('format_designer/section_info',
            $sectionobj->export_for_template($output));
    }
}
