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
 * Contains the default section controls output class.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output\courseformat\content\section;

use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;
use section_info;
use core_courseformat\base as course_format;
use stdClass;
use action_menu_link_secondary;
use action_menu;
use moodle_url;
use pix_icon;

/**
 * Base class to render section controls.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /** @var course_format the course format class */
    protected $format;

    /** @var section_info the course section class */
    protected $section;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     */
    public function __construct(course_format $format, section_info $section) {
        $this->format = $format;
        $this->course = $format->get_course();
        $this->section = $section;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {

        $section = $this->section;

        $controls = $this->section_control_items();

        if (empty($controls)) {
            return new stdClass();
        }

        // Convert control array into an action_menu.
        $menu = new action_menu();
        $menu->set_menu_trigger(get_string('edit'));
        $menu->attributes['class'] .= ' section-actions';
        foreach ($controls as $value) {
            $url = empty($value['url']) ? '' : $value['url'];
            $icon = empty($value['icon']) ? '' : $value['icon'];
            $name = empty($value['name']) ? '' : $value['name'];
            $attr = empty($value['attr']) ? array() : $value['attr'];
            $class = empty($value['pixattr']['class']) ? '' : $value['pixattr']['class'];
            $al = new action_menu_link_secondary(
                new moodle_url($url),
                new pix_icon($icon, '', null, array('class' => "smallicon " . $class)),
                $name,
                $attr
            );
            $menu->add($al);
        }

        $sectiontypes = [
            [
                'type' => 'default',
                'name' => get_string('link', 'format_designer'),
                'active' => empty($this->format->get_section_option($section->id, 'sectiontype'))
                    || $this->format->get_section_option($section->id, 'sectiontype') == 'default',
                'url' => new moodle_url('/course/view.php', ['id' => $this->course->id], 'section-' . $section->section)
            ],
            [
                'type' => 'list',
                'name' => get_string('list', 'format_designer'),
                'active' => $this->format->get_section_option($section->id, 'sectiontype') == 'list',
                'url' => new moodle_url('/course/view.php', ['id' => $this->course->id], 'section-' . $section->section)
            ],
            [
                'type' => 'cards',
                'name' => get_string('cards', 'format_designer'),
                'active' => $this->format->get_section_option($section->id, 'sectiontype') == 'cards',
                'url' => new moodle_url('/course/view.php', ['id' => $this->course->id], 'section-' . $section->section)
            ],
        ];

        if (format_designer_has_pro()) {
            $prosectiontypes = \local_designer\info::get_layout_menu($this->format, $section, $this->course);
            $sectiontypes = array_merge($sectiontypes, $prosectiontypes);
        }

        $data = (object)[
            'menu' => $output->render($menu),
            'hasmenu' => true,
            'id' => $section->id,
            'seciontypes' => $sectiontypes,
            'hassectiontypes' => ($this->course->coursetype != DESIGNER_TYPE_FLOW),
        ];

        return $data;
    }

}
