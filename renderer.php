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
                echo $this->section_summary($thissection, $course, null);
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
        echo $this->start_section_list();

        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            $this->render_section($thissection, $course, false);
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
        echo $this->start_section_list();
        echo $this->render_section($thissection, $course, false);
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
     * @param int $sectionreturn
     * @param bool $sectioncontent
     * @return void|string
     */
    public function render_section(section_info $section, stdClass $course, $onsectionpage,
        $sectionreturn = 0, $sectioncontent = false) {
        global $DB, $USER, $CFG;
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

        $sectionsummary = '';
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $sectionsummary = $this->format_summary_text($section);
        }
        $sectionlayoutclass = 'link-layout';
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        if ($sectiontype == 'list') {
            $sectionlayoutclass = "list-layout";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = 'card-layout';
        }
        $templatename = 'format_designer/section_layout_' . $sectiontype;

        try {
            $this->get_mustache()->loadTemplate($templatename);
        } catch (Exception $exception) {
            debugging('Missing section mustache template: ' . $templatename);
            $templatename = 'format_designer/section_layout_default';
        }
        if ($sectioncontent) {
            $contenttemplatename = 'format_designer/section_content_' . $sectiontype;
            return $this->render_from_template($contenttemplatename, [
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
                'cmcontrol' => $cmcontrol
            ]);
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
            'sectionrestrict' => $sectionrestrict,
            'sectionsummary' => $sectionsummary,
            'cmlist' => $cmlist,
            'courseid' => $course->id,
            'cmcontrol' => $cmcontrol
        ]);
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
        global $DB, $USER;
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
                if (strlen($cmtextcontent) >= 160) {
                    $modcontenthtml = '';
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'fullcontent-summary summary-hide'));
                    $modcontenthtml .= $cmtextcontent;
                    $modurl = \html_writer::link('javascript:void(0)', get_string('more'),
                        array('class' => 'mod-description-action'));
                    $modcontenthtml .= $modurl;
                    $modcontenthtml .= html_writer::end_tag('div');
                    $modcontent = $modcontenthtml;
                } else {
                    $modcontent = html_writer::tag('p', $cmtextcontent);;
                }
            }
        }

        $modvisits = $DB->count_records('logstore_standard_log', array('contextinstanceid' => $mod->id,
            'userid' => $USER->id, 'action' => 'viewed', 'target' => 'course_module'));
        $modvisits = !empty($modvisits) ? get_string('modvisit', 'format_designer', $modvisits) : false;
        $calltoactionhtml = $this->render(new call_to_action($mod));
        $availabilityrestrict = '';
        if ($mod->availableinfo) {
            $availabilityhtml = $availability;
            $restricthtml = html_writer::start_tag('div', array('class' => 'restrict-block'));
            $restricthtml .= html_writer::start_tag('div', array('class' => 'info-content-block'));
            $restricthtml .= $availabilityhtml;
            $restricthtml .= html_writer::start_tag('div', array('class' => 'call-action-block'));
            $restricthtml .= $calltoactionhtml;
            $restricthtml .= html_writer::end_tag('div');
            $restricthtml .= html_writer::end_tag('div');
            $restricthtml .= html_writer::end_tag('div');
            $calltoactionhtml = $restricthtml;
            $availabilityrestrict = $availability;
            $availability = '';
        }

        $cmlist = [
            'id' => 'module-' . $mod->id,
            'cm' => $mod,
            'modtype' => $mod->get_module_type_name(),
            'modclasses' => $modclasses,
            'indentclasses' => $indentclasses,
            'colorclass' => $cmcompletion->get_color_class(),
            'movehtml' => $movehtml,
            'cmname' => $this->courserenderer->course_section_cm_name($mod, $displayoptions),
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
            'modvisits' => $modvisits,
            'availabilityrestrict' => $availabilityrestrict
        ];
        return $cmlist;
    }
}
