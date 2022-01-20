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

use format_designer\output\call_to_action;
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
    protected function start_section_list($sectioncollapse=false) {
        $attrs = ['class' => 'designer'];
        if ($sectioncollapse) {
            $attrs['id'] = 'section-course-accordion';
        }
        return html_writer::start_tag('ul', $attrs);
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

            $sectiontypes = [
                [
                    'type' => 'default',
                    'name' => get_string('link', 'format_designer'),
                    'active' => empty($format->get_section_option($section->id, 'sectiontype'))
                        || $format->get_section_option($section->id, 'sectiontype') == 'default',
                    'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                ],
                [
                    'type' => 'list',
                    'name' => get_string('list', 'format_designer'),
                    'active' => $format->get_section_option($section->id, 'sectiontype') == 'list',
                    'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                ],
                [
                    'type' => 'cards',
                    'name' => get_string('cards', 'format_designer'),
                    'active' => $format->get_section_option($section->id, 'sectiontype') == 'cards',
                    'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                ],
            ];

            if (format_designer_has_pro()) {
                $prosectiontypes = [
                    [
                        'type' => 'circles',
                        'name' => get_string('circles', 'format_designer'),
                        'active' => $format->get_section_option($section->id, 'sectiontype') == 'circles',
                        'url' => new moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $section->section)
                    ],
                ];
                $sectiontypes = array_merge($sectiontypes, $prosectiontypes);
            }

            $o = $this->render_from_template('format_designer/section_controls', [
                'seciontypes' => $sectiontypes,
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
                        'class' => 'dropdown-item editing_highlight menu-action',
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
                        'class' => 'dropdown-item editing_highlight menu-action',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        $parentcontrols = $this->parentsection_edit_control_items($course, $section, $onsectionpage);

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
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    public function parentsection_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return array();
        }

        $sectionreturn = $onsectionpage ? $section->section : null;

        $coursecontext = context_course::instance($course->id);
        $numsections = course_get_format($course)->get_last_section_number();
        $isstealth = $section->section > $numsections;

        $baseurl = course_get_url($course, $sectionreturn);
        $baseurl->param('sesskey', sesskey());

        $controls = array();

        if (!$isstealth && has_capability('moodle/course:update', $coursecontext)) {
            if ($section->section > 0
                && get_string_manager()->string_exists('editsection', 'format_'.$course->format)) {
                $streditsection = get_string('editsection', 'format_'.$course->format);
            } else {
                $streditsection = get_string('editsection');
            }

            $controls['edit'] = array(
                'url'   => new moodle_url('/course/editsection.php', array('id' => $section->id, 'sr' => $sectionreturn)),
                'icon' => 'i/settings',
                'name' => $streditsection,
                'pixattr' => array('class' => ''),
                'attr' => array('class' => 'dropdown-item edit menu-action'));
        }

        if ($section->section) {
            $url = clone($baseurl);
            if (!$isstealth) {
                if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                    if ($section->visible) { // Show the hide/show eye.
                        $strhidefromothers = get_string('hidefromothers', 'format_'.$course->format);
                        $url->param('hide', $section->section);
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/hide',
                            'name' => $strhidefromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'hide'));
                    } else {
                        $strshowfromothers = get_string('showfromothers', 'format_'.$course->format);
                        $url->param('show',  $section->section);
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/show',
                            'name' => $strshowfromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'show'));
                    }
                }

                if (!$onsectionpage) {
                    if (has_capability('moodle/course:movesections', $coursecontext)) {
                        $url = clone($baseurl);
                        if ($section->section > 1) { // Add a arrow to move section up.
                            $url->param('section', $section->section);
                            $url->param('move', -1);
                            $strmoveup = get_string('moveup');
                            $controls['moveup'] = array(
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmoveup,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'dropdown-item moveup menu-action'));
                        }

                        $url = clone($baseurl);
                        if ($section->section < $numsections) { // Add a arrow to move section down.
                            $url->param('section', $section->section);
                            $url->param('move', 1);
                            $strmovedown = get_string('movedown');
                            $controls['movedown'] = array(
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmovedown,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'dropdown-item movedown menu-action'));
                        }
                    }
                }
            }

            if (course_can_delete_section($course, $section)) {
                if (get_string_manager()->string_exists('deletesection', 'format_'.$course->format)) {
                    $strdelete = get_string('deletesection', 'format_'.$course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new moodle_url('/course/editsection.php', array(
                    'id' => $section->id,
                    'sr' => $sectionreturn,
                    'delete' => 1,
                    'sesskey' => sesskey()));
                $controls['delete'] = array(
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => array('class' => ''),
                    'attr' => array('class' => 'dropdown-item editing_delete menu-action'));
            }
        }

        return $controls;
    }

    /**
     * Generate a summary of a section for display on the 'course index page'
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    public function section_summary($section, $course, $mods) {
        $classattr = 'section main section-summary clearfix';
        $linkclasses = '';

        // If section is hidden then display grey section link.
        if (!$section->visible) {
            $classattr .= ' hidden';
            $linkclasses .= ' dimmed_text';
        } else if (course_get_format($course)->is_section_current($section)) {
            $classattr .= ' current';
        }

        $title = get_section_name($course, $section);
        $o = '';
        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => $classattr,
            'role' => 'region',
            'aria-label' => $title,
            'data-sectionid' => $section->section
        ]);

        $o .= html_writer::tag('div', '', array('class' => 'left side'));
        $o .= html_writer::tag('div', '', array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        if ($section->uservisible) {
            $title = html_writer::tag('a', $title,
                    array('href' => course_get_url($course, $section->section), 'class' => $linkclasses));
        }
        $o .= $this->output->heading($title, 3, 'section-title');
        $o .= html_writer::start_tag('div', array('class' => 'availability-section-block'));
        $o .= $this->section_availability($section);
        $o .= html_writer::end_tag('div');
        $o .= html_writer::start_tag('div', array('class' => 'summarytext'));

        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');
        $o .= $this->section_activity_summary($section, $course, null);
        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('li');

        return $o;
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
        $sectioncollapse = isset($course->sectioncollapse) ? $course->sectioncollapse : false;
        echo $this->start_section_list($sectioncollapse);
        $numsections = course_get_format($course)->get_last_section_number();
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
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
                echo $this->render_section($thissection, $course, false, true);
            } else {
                echo $this->render_section($thissection, $course, false);
            }

        }
        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {

            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // This is not stealth section or it is empty.
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->render_section($thissection, $course, false);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {

            echo $this->end_section_list();
        }

    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);
        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection)) || !$sectioninfo->uservisible) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            throw new moodle_exception('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }
        // Copy activity clipboard..
        $sectioncollapse = isset($course->sectioncollapse) ? $course->sectioncollapse : false;
        echo $this->start_section_list($sectioncollapse);

        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            $this->render_section($thissection, $course, true);
        }
        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            echo $this->end_section_list();
            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

        // Start single-section div.
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        // Title attributes.
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }

        echo $sectiontitle;

        // Now the list of sections..
        $sectioncollapse = isset($course->sectioncollapse) ? $course->sectioncollapse : false;
        echo $this->start_section_list($sectioncollapse);
        echo $this->render_section($thissection, $course, true);
        echo $this->end_section_list();

        // Display section bottom navigation.
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        $sectionbottomnav .= html_writer::tag('div', $this->section_nav_selection($course, $sections, $displaysection),
            array('class' => 'mdl-align'));
        $sectionbottomnav .= html_writer::end_tag('div');
        echo $sectionbottomnav;

        // Close single-section div.
        echo html_writer::end_tag('div');
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param section_info $section The course_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    public function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->visible) {
            if ($canviewhidden) {
                $o .= $this->courserenderer->availability_info(get_string('hiddenfromstudents'), 'ishidden');
            } else {
                // We are here because of the setting "Hidden sections are shown in collapsed form".
                // Student can not see the section contents but can see its name.
                $o .= $this->courserenderer->availability_info(get_string('notavailable'), 'ishidden');
            }
        } else if (!$section->uservisible || $canviewhidden && !empty($CFG->enableavailability)) {
            if (!$section->uservisible && $section->availableinfo) {
                // Note: We only get to this function if availableinfo is non-empty,
                // so there is definitely something to print.
                $formattedinfo = \core_availability\info::format_info(
                        $section->availableinfo, $section->course);
                $o .= $this->courserenderer->availability_info($formattedinfo, 'isrestricted');
                $o .= html_writer::start_tag('div', array('class' => 'section-restricted-action'));
                $o .= html_writer::tag('i', '', array('class' => 'fa fa-lock'));
                $o .= html_writer::end_tag('div');
            } else {
                 // Check if there is an availability restriction.
                $ci = new \core_availability\info_section($section);
                $fullinfo = $ci->get_full_information();
                if ($fullinfo) {
                    $formattedinfo = \core_availability\info::format_info(
                            $fullinfo, $section->course);
                    $o .= $this->courserenderer->availability_info($formattedinfo, 'isrestricted isfullinfo');
                    $o .= html_writer::start_tag('div', array('class' => 'section-restricted-action'));
                    $o .= html_writer::tag('i', '', array('class' => 'fa fa-lock'));
                    $o .= html_writer::end_tag('div');
                }
            }
        }
        return $o;
    }

    /**
     * Render the section info.
     *
     * @param \section_info $section
     * @param stdClass $course
     * @param bool $onsectionpage
     * @param bool $sectionheader
     * @param int $sectionreturn
     * @param bool $sectioncontent
     * @return void|string
     */
    public function render_section(section_info $section, stdClass $course, $onsectionpage,
        $sectionheader = false, $sectionreturn = 0, $sectioncontent = false) {
        global $DB, $USER, $CFG;

        $sectionurl = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
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
        $sectionrestrict = (!$section->uservisible && $section->availableinfo) ? true : false;

        // CM LIST.
        $cmlist = [];
        $modinfo = get_fast_modinfo($course);
        $displayoptions = [];
        $completioninfo = new completion_info($course);

        // Get the list of modules visible to user.
        if (!empty($modinfo->sections[$section->section]) && $section->uservisible) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                $cminfo = $this->render_course_module($mod, $sectionreturn, $displayoptions);
                $cmlist[$modnumber] = $cminfo;
                $cmlist[$modnumber]['modstatus'] = !empty($cminfo) ? true : false;
            }
        }
        $cmlist = array_values($cmlist);
        // END CM LIST.
        $cmcontrol = $this->courserenderer->course_section_add_cm_control($course, 0, 0);
        if ($course->coursedisplay == 1 && !$onsectionpage) {
            $gotosection = true;
        }

        // Calculate to the section progress.
        $cmcompleted = 0;
        $totalmods = 0;
        $issectioncompletion = 0;
        if (!empty($modinfo->sections[$section->section]) && $section->uservisible) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!empty($mod)) {
                    $cmcompletion = new cm_completion($mod);
                    if ($mod->uservisible && $cmcompletion->get_completion_mode() != COMPLETION_TRACKING_NONE) {
                        $totalmods++;
                        $cmcompletionstate = $cmcompletion->get_completion_state();
                        if ($cmcompletionstate == COMPLETION_COMPLETE || $cmcompletionstate == COMPLETION_COMPLETE_PASS ) {
                            $cmcompleted++;
                        }
                    }
                }
            }
        }
        if ($totalmods) {
            $sectionprogress = $cmcompleted / $totalmods * 100;
            $issectioncompletion = 1;
        } else {
            $sectionprogress = 0;
        }
        $sectionprogresscomp = ($sectionprogress == 100) ? true : false;
        $sectionsummary = '';
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $sectionsummary = $this->format_summary_text($section);
        }
        $sectionbackgroundstyle = '';
        $sectioncontainerwidth = '';
        $sectioncontentwidth = '';
        $sectiondesigntextcolor = '';
        $sectiondesignheader = false;
        $sectiondesignwhole = false;
        $sectioncontainerlayout = '';
        $sectioncontentlayout = '';
        $bgoverlay = false;
        if (format_designer_has_pro()) {

            $sectionstyle .= local_designer_layout_columnclasses($section);
            // Get section designer background image.
            $sectiondesignerbackimageurl = get_section_designer_background_image($section, $course->id);
            if ($section->sectionbackgroundtype == 'header') {
                $sectiondesignheader = true;
                $sectionstyle .= ' section-design-header ';
            } else if ($section->sectionbackgroundtype == 'whole') {
                $sectiondesignwhole = true;
                $sectionstyle .= ' section-design-whole ';
            }
            // Section designer background styles.
            $backgradient = (isset($section->sectiondesignerbackgradient) && ($section->sectiondesignerbackgradient))
                            ? str_replace(';', '', $section->sectiondesignerbackgradient) : null;

            // Background color.
            if ($section->sectiondesignerbackgroundcolor) {
                $overlaycolor = "background-color: $section->sectiondesignerbackgroundcolor" . ";";
                $sectionbackgroundstyle .= $overlaycolor;
            }
            if ($sectiondesignerbackimageurl) {
                if ($backgradient) {
                    $sectionbackgroundstyle .= sprintf('background-image: url(%s);', $sectiondesignerbackimageurl);
                    $overlaycolor = sprintf('background: %s;', $backgradient );
                } else {
                    $sectionbackgroundstyle .= "background-image: url('" . $sectiondesignerbackimageurl . "');";
                }
                $bgoverlay = (isset($overlaycolor)) ? $overlaycolor : false;
                $sectionstyle .= (isset($overlaycolor)) ? ' bg-color-overlay' : '';
            } else if ($section->sectiondesignerbackgradient) {
                $gradient = $section->sectiondesignerbackgradient;
                $sectionbackgroundstyle .= sprintf('background: %s;', $gradient);
            }

            if ($section->sectiondesignertextcolor) {
                $sectiondesigntextcolor = "color: $section->sectiondesignertextcolor"
                    . ";--sectioncolor:  $section->sectiondesignertextcolor;";
            }

            // Section container & content layout.
            $containerlayout = $section->layoutcontainer;
            if ($containerlayout == 'full') {
                $sectioncontainerlayout = 'container-full';
            } else if ($containerlayout == 'boxed') {
                $sectioncontainerlayout = 'container-boxed';
                $sectioncontainerboxwidth = ($section->layoutcontainerwidth) ? $section->layoutcontainerwidth : '1200';
                $sectioncontainerwidth .= 'max-width:'. $sectioncontainerboxwidth. "px;";
            } else {
                $sectioncontainerlayout = "container";
            }

            $contentlayout = $section->layoutcontent;
            if ($contentlayout == 'boxed') {
                $sectioncontentlayout = 'content-boxed';
                $sectioncontentboxwidth = ($section->layoutcontentwidth) ? $section->layoutcontentwidth : '1200';
                $sectioncontentwidth .= 'max-width: '. $sectioncontentboxwidth . "px;";
            } else {
                $sectioncontentlayout = 'content-normal';
            }
        }

        $sectionlayoutclass = 'link-layout';
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        if ($sectiontype == 'list') {
            $sectionlayoutclass = "list-layout";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = 'card-layout';
        }
        $templatename = 'format_designer/section_layout_' . $sectiontype;
        $prolayouts = get_pro_layouts();
        if (in_array($sectiontype, $prolayouts)) {
            if (format_designer_has_pro()) {
                $templatename = 'layouts_' . $sectiontype . '/section_layout_' . $sectiontype;
            }
        }
        // Initailze section header titles.
        try {
            $this->get_mustache()->loadTemplate($templatename);
        } catch (Exception $exception) {
            debugging('Missing section mustache template: ' . $templatename);
            $templatename = 'format_designer/section_layout_default';
        }

        if (isset($course->initialstate) && $course->initialstate == SECTION_COLLAPSE) {
            $sectioncollapsestatus = '';
        } else {
            $sectioncollapsestatus = (isset($course->initialstate) && $course->initialstate == FIRST_EXPAND)
                ? (($section->section == 0) ? 'show' : '') : 'show';
        }

        $templatecontext = [
            'section' => $section,
            'sectiontype' => $sectiontype,
            'sectionlayoutclass' => $sectionlayoutclass,
            'sectionstyle' => $sectionstyle,
            'sectionreturn' => $sectionreturn,
            'leftcontent' => $leftcontent,
            'rightcontent' => $rightcontent,
            'sectionname' => $sectionname,
            'sectionavailability' => $sectionavailability,
            'sectionrestrict' => $sectionrestrict,
            'sectionsummary' => $sectionsummary,
            'cmlist' => $cmlist,
            'courseid' => $course->id,
            'cmcontrol' => $cmcontrol,
            'sectionprogress' => isset($sectionprogress) ? round($sectionprogress) : '',
            'sectionprogresscomp' => isset($sectionprogresscomp) ? round($sectionprogresscomp) : '',
            'sectioncategorisetitle' => isset($section->categorisetitle) ? $section->categorisetitle : '',
            'sectionbackgroundstyle' => $sectionbackgroundstyle,
            'sectioncontainerwidth' => $sectioncontainerwidth,
            'sectioncontentwidth' => $sectioncontentwidth,
            'sectiondesignwhole' => $sectiondesignwhole,
            'sectiondesignheader' => $sectiondesignheader,
            'sectiondesigntextcolor' => $sectiondesigntextcolor,
            'sectioncontainerlayout' => $sectioncontainerlayout,
            'sectioncontentlayout' => $sectioncontentlayout,
            'sectionheader' => $sectionheader,
            'bgoverlay' => $bgoverlay,
            'issectioncompletion' => $issectioncompletion,
            'gotosection' => (isset($gotosection) ? $gotosection : false),
            'sectionurl' => $sectionurl,
            'sectioncollapse' => isset($course->sectioncollapse) ? $course->sectioncollapse : false,
            'sectionshow' => $sectioncollapsestatus,
            'sectionaccordion' => isset($course->accordion) ? $course->accordion : false
        ];
        if ($sectioncontent) {
            $contenttemplatename = 'format_designer/section_content_' . $sectiontype;
            return $this->render_from_template($contenttemplatename, $templatecontext);
        }
        $sectionclass = 'section-type-'.$sectiontype.' '.$sectionstyle.' '.$sectioncontainerlayout;
        $sectionclass .= ($sectionrestrict) ? 'restricted' : '';
        $style = ($sectiondesignwhole) ? $sectionbackgroundstyle : '';
        $style .= ($sectioncontainerwidth) ? ' '.$sectioncontainerwidth : '';
        $sectionhead = html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => 'section main clearfix'.$sectionclass,
            'role' => 'region',
            'aria-labelledby' => "sectionid-{$section->id}-title",
            'data-sectionid' => $section->section,
            'data-sectionreturnid' => $sectionreturn,
            'data-id' => $section->id,
            'style' => $style
        ]);

        echo $sectionhead;
        echo $this->render_from_template($templatename, $templatecontext);
        echo html_writer::end_tag('li');
    }


    /**
     * Render the mod info.
     *
     * @param object $mod
     * @param int $sectionreturn
     * @param array $displayoptions
     * @return void|string
     */
    public function render_course_module($mod, $sectionreturn, $displayoptions = []) {
        global $DB, $USER, $CFG;
        if (!$mod->is_visible_on_course_page()) {
            return [];
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
        // If there is content AND a link, then display the content here.
        // (AFTER any icons). Otherwise it was displayed before.
        $cmtext = '';
        if (!empty($url)) {
            $cmtext = $this->courserenderer->course_section_cm_text($mod, $displayoptions);
            $cmtextcontent = format_string($cmtext);
            $modcontent = '';
            if (!empty($cmtextcontent)) {
                if (str_word_count($cmtextcontent) >= 23) {
                    $modcontenthtml = '';
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'trim-summary'));
                    $modcontenthtml .= format_designer_modcontent_trim_char($cmtextcontent, 24);
                    $modcontenthtml .= \html_writer::link('javascript:void(0)', get_string('more'),
                    array('class' => 'mod-description-action'));
                    $modcontenthtml .= html_writer::end_tag('div');
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'fullcontent-summary summary-hide'));
                    $modcontenthtml .= $cmtextcontent;
                    $modcontenthtml .= " " .\html_writer::link('javascript:void(0)', get_string('less', 'format_designer'),
                    array('class' => 'mod-description-action'));
                    $modcontenthtml .= html_writer::end_tag('div');
                    $modcontent = $modcontenthtml;
                } else {
                    $modcontent = html_writer::tag('p', $cmtextcontent);
                }
            }
        }

        $modvisits = $DB->count_records('logstore_standard_log', array('contextinstanceid' => $mod->id,
            'userid' => $USER->id, 'action' => 'viewed', 'target' => 'course_module'));
        $modvisits = !empty($modvisits) ? get_string('modvisit', 'format_designer', $modvisits) :
            get_string('notvisit', 'format_designer');
        $calltoactionhtml = $this->render(new call_to_action($mod));
        $availabilityrestrict = '';
        $modrestricted = false;
        if ($mod->availableinfo) {
            $availabilityhtml = $availability;
            $restricthtml = html_writer::start_tag('div', array('class' => 'restrict-block'));
            $restricthtml .= html_writer::start_tag('div', array('class' => 'info-content-block'));
            $restricthtml .= html_writer::start_tag('div', array('class' => 'call-action-block'));
            $restricthtml .= $calltoactionhtml;
            $restricthtml .= html_writer::end_tag('div');
            $restricthtml .= $availabilityhtml;
            $restricthtml .= html_writer::end_tag('div');
            $restricthtml .= html_writer::end_tag('div');
            $calltoactionhtml = $restricthtml;
            $availabilityrestrict = $availability;
            $availability = '';
            $modrestricted = true;
        }

        $activitylink = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                'class' => 'iconlarge activityicon', 'alt' => '', 'role' => 'presentation', 'aria-hidden' => 'true'));
        if ($mod->uservisible) {
            $modiconurl = html_writer::link($url, $activitylink, array('class' => 'mod-icon-url'));
        } else {
            $modiconurl = html_writer::start_div('mod-icon-url');
            $modiconurl .= $activitylink;
            $modiconurl .= html_writer::end_div();
        }

        $cmname = $this->get_cmname($mod, $displayoptions);
        $cmlist = [
            'id' => 'module-' . $mod->id,
            'cm' => $mod,
            'modtype' => $mod->get_module_type_name(),
            'modclasses' => $modclasses,
            'indentclasses' => $indentclasses,
            'colorclass' => $cmcompletion->get_color_class(),
            'movehtml' => $movehtml,
            'cmname' => $cmname,
            'cmcompletion' => $cmcompletion,
            'cmcompletionhtml' => $cmcompletionhtml,
            'calltoactionhtml' => $calltoactionhtml,
            'afterlink' => $mod->afterlink,
            'beforecontent' => $beforecontent,
            'cmtext' => $cmtext,
            'modicons' => $modicons,
            'availability' => $availability,
            'isrestricted' => !empty($mod->availableinfo),
            'modcontent' => isset($modcontent) ? $modcontent : '',
            'modcontentclass' => !empty($modcontent) ? 'ismodcontent' : '',
            'modvisits' => ($mod->url) ? $modvisits : false,
            'availabilityrestrict' => $availabilityrestrict,
            'modiconurl' => $modiconurl,
            'modrestricted' => $modrestricted,
        ];

        if (format_designer_has_pro()) {
            require_once($CFG->dirroot. "/local/designer/lib.php");
            $promodcontent = [];
            $modulebackdesign = $DB->get_record('local_designer_fields', array('cmid' => $mod->id));
            if ($modulebackdesign) {
                $modulebackimageurl = get_module_designer_background_image($mod, $modulebackdesign->backimage);
                $backgradient = (isset($modulebackdesign->backgradient) && ($modulebackdesign->backgradient))
                            ? str_replace(';', '', $modulebackdesign->backgradient) : null;

                if ($modulebackimageurl) {
                    $modulebackgroundstyle = "background-image: url('" . $modulebackimageurl . "');";
                    if ($backgradient) {
                        $promodcontent['modbackoverlaycolor'] = sprintf('background: %s;', $backgradient );
                        $cmlist['modclasses'] .= ' bg-color-overlay ';
                    }
                } else if ($modulebackdesign->backgradient) {
                    $modulebackgroundstyle = sprintf('background: %s;', $backgradient );
                } else {
                    $modulebackgroundstyle = '';
                }

                $promodcontent['modulebackgroundstyle'] = $modulebackgroundstyle;
                $moduletextcolor = '';
                // Text Color.
                if ($modulebackdesign->textcolor) {
                    $moduletextcolor = "color: $modulebackdesign->textcolor" . ";";
                }
                $promodcontent['moduletextcolor'] = $moduletextcolor;
                $cmlist = array_merge($cmlist, $promodcontent);
            }
        }
        return $cmlist;
    }
    /**
     * Get course modulename.
     * @param object $mod
     * @param array $displayoptions
     * @return string module name.
     */
    public function get_cmname($mod, $displayoptions = []) {
        global $DB;
        if ($mod->url) {
            list($linkclasses, $textclasses) = $this->course_section_cm_classes($mod);
            $groupinglabel = $mod->get_grouping_label($textclasses);
            $temp1 = new \core_course\output\course_module_name($mod, $this->page->user_is_editing(), $displayoptions);
            $modulenametemplate = $temp1->export_for_template($this->output);
            $output = '';
            $style = '';
            if (format_designer_has_pro()) {
                $modulebackdesign = $DB->get_record('local_designer_fields', array('cmid' => $mod->id));
                if ($modulebackdesign) {
                    if ($modulebackdesign->textcolor) {
                        $style = "color: $modulebackdesign->textcolor" . ";";
                    }
                }
            }
            $onclick = htmlspecialchars_decode($mod->onclick, ENT_QUOTES);
            $url = $mod->url;
            $instancename = $mod->get_formatted_name();
            $activitylink = html_writer::tag('span', $instancename, array('class' => 'instancename', 'style' => $style));
            if ($mod->uservisible) {
                $output .= html_writer::link($url, $activitylink, array('class' => 'aalink' . $linkclasses, 'onclick' => $onclick));
            } else {
                // We may be displaying this just in order to show information
                // about visibility, without the actual link ($mod->is_visible_on_course_page()).
                $output .= html_writer::tag('div', $activitylink, array('class' => $textclasses));
            }
            $modulenametemplate['displayvalue'] = $output;
            $cmname = $this->output->render_from_template('core/inplace_editable', $modulenametemplate) . $groupinglabel;
            return $cmname;
        }
        return '';
    }

    /**
     * Returns the CSS classes for the activity name/content
     *
     * For items which are hidden, unavailable or stealth but should be displayed
     * to current user ($mod->is_visible_on_course_page()), we show those as dimmed.
     * Students will also see as dimmed activities names that are not yet available
     * but should still be displayed (without link) with availability info.
     *
     * @param cm_info $mod
     * @return array array of two elements ($linkclasses, $textclasses)
     */
    public function course_section_cm_classes(cm_info $mod) {
        $linkclasses = '';
        $textclasses = '';
        if ($mod->uservisible) {
            $conditionalhidden = $this->is_cm_conditionally_hidden($mod);
            $accessiblebutdim = (!$mod->visible || $conditionalhidden) &&
                has_capability('moodle/course:viewhiddenactivities', $mod->context);
            if ($accessiblebutdim) {
                $linkclasses .= ' dimmed';
                $textclasses .= ' dimmed_text';
                if ($conditionalhidden) {
                    $linkclasses .= ' conditionalhidden';
                    $textclasses .= ' conditionalhidden';
                }
            }
            if ($mod->is_stealth()) {
                // Stealth activity is the one that is not visible on course page.
                // It still may be displayed to the users who can manage it.
                $linkclasses .= ' stealth';
                $textclasses .= ' stealth';
            }
        } else {
            $linkclasses .= ' dimmed';
            $textclasses .= ' dimmed dimmed_text';
        }
        return array($linkclasses, $textclasses);
    }

    /**
     * Checks if course module has any conditions that may make it unavailable for
     * all or some of the students
     *
     * This function is internal and is only used to create CSS classes for the module name/text
     *
     * @param cm_info $mod
     * @return bool
     */
    public function is_cm_conditionally_hidden(cm_info $mod) {
        global $CFG;
        $conditionalhidden = false;
        if (!empty($CFG->enableavailability)) {
            $info = new \core_availability\info_module($mod);
            $conditionalhidden = !$info->is_available_for_all();
        }
        return $conditionalhidden;
    }
}
