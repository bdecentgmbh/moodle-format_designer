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
 * Renderer for outputting the Designer course format.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_designer\output\cm_completion;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for Designer format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_designer_renderer extends format_section_renderer_base {

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_designer_renderer::section_edit_control_items() only displays the 'Highlight' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', ['class' => 'designer']);
    }

    /**
     * Generate the closing container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
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
     * Generate the edit control action menu
     *
     * @param array $controls The edit control items from section_edit_control_items
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function section_edit_control_menu($controls, $course, $section) {

        /** @var format_designer $format */
        $format = course_get_format($course);

        $o = "";
        if (!empty($controls)) {
            $controllinks = [];
            foreach ($controls as $value) {
                $url = empty($value['url']) ? '' : $value['url'];
                $icon = empty($value['icon']) ? '' : $value['icon'];
                $name = empty($value['name']) ? '' : $value['name'];
                $attr = empty($value['attr']) ? array() : $value['attr'];
                $class = empty($value['pixattr']['class']) ? '' : $value['pixattr']['class'];
                $class .= ' dropdown-item';

                $attr = array_map(function($key, $value) {
                    return [
                        'name' => $key,
                        'value' => $value
                    ];
                }, array_keys($attr), $attr);
                $controllinks[] = [
                    'url' => $url,
                    'name' => $name,
                    'icon' => $this->render(new pix_icon($icon, '', null, array('class' => "smallicon " . $class))),
                    'attributes' => $attr
                ];
            }

            $o = $this->render_from_template('format_designer/section_controls', [
                'seciontypes' => [
                    [
                        'type' => 'default',
                        'name' => 'Text links (Default)',
                        'active' => empty($format->get_section_option($section->section, 'sectiontype')) || $format->get_section_option($section->section, 'sectiontype') == 'default',
                        'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                    ],
                    [
                        'type' => 'list',
                        'name' => 'List',
                        'active' => $format->get_section_option($section->section, 'sectiontype') == 'list',
                        'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                    ],
                    [
                        'type' => 'cards',
                        'name' => 'Cards',
                        'active' => $format->get_section_option($section->section, 'sectiontype') == 'cards',
                        'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                    ],
                ],
                'sectionid' => $section->id,
                'sectionnumber' => $section->section,
                'courseid' => $course->id,
                'sectionactionmenu' => $controllinks,
                'hassectionactionmenu' => !empty($controllinks)
            ]);
        }

        return $o;
    }

    /**
     * Generate the edit control items of a section.
     *
     * @param int|stdClass $course The course entry from DB
     * @param section_info|stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return [];
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'removemarker'
                    ],
                ];
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->render_section($thissection, $course, false);
            }
        }

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

    }

    public function render_section(section_info $section, stdClass $course, $onsectionpage, $sectionreturn = 0) {

        /** @var format_designer $format */
        $format = course_get_format($course);

        $sectionstyle = '';
        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $sectionavailability = $this->section_availability($section);

        // CM LIST

        $cmlist = [];

        $modinfo = get_fast_modinfo($course);
        $displayoptions = [];
        $completioninfo = new completion_info($course);

        // Get the list of modules visible to user
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];

                if (!$mod->is_visible_on_course_page()) {
                    continue;
                }

                $indentclasses = 'mod-indent';
                if (!empty($mod->indent)) {
                    $indentclasses .= ' mod-indent-'.$mod->indent;
                    if ($mod->indent > 15) {
                        $indentclasses .= ' mod-indent-huge';
                    }
                }

                $movehtml = '';
                if ($this->page->user_is_editing()) {
                    $movehtml = course_get_cm_move($mod, $sectionreturn);
                }

                $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;

                // If there is content but NO link (eg label), then display the
                // content here (BEFORE any icons). In this case cons must be
                // displayed after the content so that it makes more sense visually
                // and for accessibility reasons, e.g. if you have a one-line label
                // it should work similarly (at least in terms of ordering) to an
                // activity.
                $beforecontent = '';
                $url = $mod->url;
                if (empty($url)) {
                    $beforecontent = $this->courserenderer->course_section_cm_text($mod, $displayoptions);
                }

                $cmcompletion = new cm_completion($mod);
                $cmcompletionhtml = '';
                if (empty($displayoptions['hidecompletion'])) {
                    if ($cmcompletion->is_visible()) {
                        $cmcompletionhtml = $this->render($cmcompletion);
                    }
                }

                $modicons = '';
                if ($this->page->user_is_editing()) {
                    $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
                    $modicons .= ' '. $this->courserenderer->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
                    $modicons .= $mod->afterediticons;
                }

                $availability = $this->courserenderer->course_section_cm_availability($mod, $displayoptions);

                // If there is content AND a link, then display the content here
                // (AFTER any icons). Otherwise it was displayed before
                $aftercontent = '';
                if (!empty($url)) {
                    $aftercontent = $this->courserenderer->course_section_cm_text($mod, $displayoptions);
                }

                $cmlist[$modnumber] = [
                    'id' => 'module-' . $mod->id,
                    'modclasses' => $modclasses,
                    'indentclasses' => $indentclasses,
                    'colorclass' => $cmcompletion->get_color_class(),
                    'movehtml' => $movehtml,
                    'cmname' => $this->courserenderer->course_section_cm_name($mod, $displayoptions),
                    'cmcompletion' => $cmcompletion,
                    'cmcompletionhtml' => $cmcompletionhtml,
                    'afterlink' => $mod->afterlink,
                    'beforecontent' => $beforecontent,
                    'aftercontent' => $aftercontent,
                    'modicons' => $modicons,
                    'availability' => $availability
                ];
            }
        }
        $cmlist = array_values($cmlist);

        // END CM LIST
        $cmcontrol = $this->courserenderer->course_section_add_cm_control($course, 0, 0);

        $sectionsummary = '';
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $sectionsummary = $this->format_summary_text($section);
        }

        $sectiontype = $format->get_section_option($section->section, 'sectiontype') ?: 'default';

        $templatename = 'format_designer/section_layout_' . $sectiontype;
        try {
            $this->get_mustache()->loadTemplate($templatename);
        } catch (Exception $exception) {
            debugging('Missing section mustache template: ' . $templatename);
            $templatename = 'format_designer/section_layout_default';
        }

        echo $this->render_from_template($templatename, [
            'section' => $section,
            'sectiontype' => $sectiontype,
            'sectionstyle' => $sectionstyle,
            'sectionreturn' => $sectionreturn,
            'leftcontent' => $leftcontent,
            'rightcontent' => $rightcontent,
            'sectionname' => $sectionname,
            'sectionavailability' => $sectionavailability,
            'sectionsummary' => $sectionsummary,
            'cmlist' => $cmlist,
            'cmcontrol' => $cmcontrol
        ]);

        return;


        echo $this->section_header($section, $course, false, 0);
        if ($section->uservisible) {
            echo $this->courserenderer->course_section_cm_list($course, $section, 0);
            echo $this->courserenderer->course_section_add_cm_control($course, $section->section, 0);
        }
        echo $this->section_footer();
    }
}
