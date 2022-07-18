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
require_once($CFG->dirroot.'/course/format/designer/lib.php');
/**
 * Basic renderer for Designer format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_designer_renderer extends format_section_renderer_base {

    /**
     * Course modinfo instance.
     *
     * @var course_modinfo
     */
    public $modinfo;

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
        $this->page = $page;
    }

    /**
     * Generate the starting container html for a list of sections.
     *
     * @param string|bool $id
     * @param array $classes Additional class for section ul
     * @return string HTML to output.
     */
    protected function start_section_list($id=false, $classes=[]) {

        $classes[] = 'designer';
        $attrs = ['class' => implode(' ', $classes) ];

        if ($id) {
            $attrs['id'] = (is_bool($id) && $id) ? 'section-course-accordion' : $id;
        }
        return html_writer::start_tag('ul', $attrs);
    }

    /**
     * Find the course has collapasable accordion.
     *
     * @param stdclass $course
     * @return boolean
     */
    protected function is_courseaccordion($course) {
        // Now the list of sections.
        $sectioncollapse = isset($course->sectioncollapse) ?
            (($course->coursedisplay && !$this->page->user_is_editing()) ? false : $course->sectioncollapse) : false;
        // If kanban board enabled remove the row.
        if ($course->designercoursetype) {
            $sectioncollapse = false;
        }
        return $sectioncollapse;
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
                $prosectiontypes = \local_designer\info::get_layout_menu($format, $section, $course);

                $sectiontypes = array_merge($sectiontypes, $prosectiontypes);
            }

            $o = $this->render_from_template('format_designer/section_controls', [
                'seciontypes' => $sectiontypes,
                'hassectiontypes' => ($course->designercoursetype != DESIGNER_TYPE_FLOW),
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
     * Render the completion info icon to modify the content.
     *
     * @param \completion_info $completioninfo Completion info of the current course.
     * @return html $result Completion progress info icon.
     */
    public function completioninfo_icon(\completion_info $completioninfo) {
        global $USER;
        $result = '';
        if ($completioninfo->is_enabled() && !$this->page->user_is_editing()
            && $completioninfo->is_tracked_user($USER->id) && isloggedin() && !isguestuser()) {
            $result .= html_writer::tag('div', get_string('yourprogress', 'completion') .
                    $this->output->help_icon('completionicons', 'format_designer'), array(
                        'id' => 'completionprogressid', 'class' => 'completionprogress'
                    ));
        }
        return $result;
    }

    /**
     * Get course time mananagment details user current course progress and due modules course.
     *
     * @param stdclass $course
     * @return string
     */
    public function timemanagement_details(stdclass $course): string {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/enrol/locallib.php');
        $context = context_course::instance($course->id);
        if (is_enrolled($context, $USER->id)) {
            $enrolmanager = new course_enrolment_manager($this->page, $course);
            $enrolments = $enrolmanager->get_user_enrolments($USER->id);
            $enrolment = (!empty($enrolments)) ? current($enrolments) : [];
            $enrolstartdate = ($enrolment->timestart) ?? '';
            $enrolenddate = ($enrolment->timeend) ?? '';
        } else {
            $enrolstartdate = $course->startdate;
            $enrolenddate = $course->enddate;
        }
        $strftimedate = get_string('strftimedate');
        $data = [
            'course' => $course,
            'enrolmentstartdate' => ($course->enrolmentstartdate && $enrolstartdate)
                ? userdate($enrolstartdate, $strftimedate, '', false) : '',
            'enrolmentenddate' => ($course->enrolmentenddate && $enrolenddate)
                ? userdate($enrolenddate, $strftimedate, '', false) : '',
        ];
        $courseprogress = $this->activity_progress($course, $USER->id);
        if ($courseprogress != null) {
            $sql = "SELECT * FROM {course_completions}
                WHERE course = :course AND userid = :userid AND timecompleted IS NOT NULL";
            $completion = $DB->get_record_sql($sql, ['userid' => $USER->id, 'course' => $course->id]);
            $data += [
                'showcompletiondate' => ($course->coursecompletiondate) ?: '',
                'completiondate' => (!empty($completion) ? userdate($completion->timecompleted, $strftimedate, '', false) : ''),
                'courseprogress' => ($course->activityprogress) ? $courseprogress : '',
            ];
        }
        // Find the course due date. only if the timemanagement installed.
        if (format_designer_timemanagement_installed() && function_exists('ltool_timemanagement_cal_course_duedate')) {
            $coursedatesinfo = $DB->get_record('ltool_timemanagement_course', array('course' => $course->id));
            if ($course->courseduedate && $coursedatesinfo) {
                $courseduedate = ltool_timemanagement_cal_course_duedate($coursedatesinfo, $enrolstartdate);
                $data['courseduedate'] = userdate($courseduedate, $strftimedate, '', false);
            }
        }

        $data['due'] = $this->due_overdue_activities_count();
        if (isset($courseprogress['count']) &&  isset($courseprogress['completed'])) {
            $data['modcmpinfo'] = get_string("activitiescompleted", "format_designer",
                 ['count' => $courseprogress['count'], 'completed' => $courseprogress['completed']]);
        }
        $html = $this->output->render_from_template('format_designer/course_time_management', $data);
        return $html;
    }

    /**
     * Get the count of due and overdue activities.
     *
     * @return array
     */
    public function due_overdue_activities_count(): array {
        global $USER, $DB;
        $duecount = $overduecount = 0;
        $modinfo = $this->modinfo;
        $completion = new \completion_info($this->modinfo->get_course());

        foreach ($modinfo->sections as $modnumbers) {
            foreach ($modnumbers as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!empty($mod) && $DB->record_exists('course_modules', array('id' => $mod->id, 'deletioninprogress' => 0))
                         && $mod->uservisible) {
                    $data = $completion->get_data($mod, true, $USER->id);
                    if ($data->completionstate != COMPLETION_COMPLETE) {
                        $cmcompletion = new cm_completion($mod);
                        $overduecount = ($cmcompletion->is_overdue()) ? $overduecount + 1 : $overduecount;
                        $duecount = ($cmcompletion->is_due_today()) ? $duecount + 1 : $duecount;
                    }
                }
            }
        }
        return [
            'dues' => get_string('todaydue', 'format_designer', ["due" => $duecount]),
            'overdues' => get_string('overdues', 'format_designer', ["overdues" => $overduecount])
        ];
    }

    /**
     * Get current course module progress. count of completion enable modules and count of completed modules.
     *
     * @param stdclass $course
     * @param int $userid
     * @return array Modules progress
     */
    protected function activity_progress($course, $userid) {
        $completion = new \completion_info($course);
        // First, let's make sure completion is enabled.
        if (!$completion->is_enabled()) {
            return null;
        }
        $result = [];

        // Get the number of modules that support completion.
        $modules = $completion->get_activities();
        $completionactivities = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);

        $count = count($completionactivities);
        if (!$count) {
            return null;
        }
        // Get the number of modules that have been completed.
        $completed = 0;
        foreach ($completionactivities as $activity) {
            $cmid = $activity->moduleinstance;

            if (isset($modules[$cmid])) {
                $data = $completion->get_data($modules[$cmid], true, $userid);
                $completed += $data->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
            }
        }
        $percent = ($completed / $count) * 100;

        return ['count' => $count, 'completed' => $completed, 'percent' => $percent] + $result;
    }

    /**
     * Course type classes.
     *
     * @param stdclass $course
     * @return void
     */
    public function course_type_class($course) {
        if (!isset($course->designercoursetype)) {
            return '';
        }
        if ($course->designercoursetype == DESIGNER_TYPE_COLLAPSIBLE) {
            $class = 'course-type-collapsible';
        } else if ($course->designercoursetype == DESIGNER_TYPE_KANBAN) {
            $class = 'course-type-kanbanboard kanban-board';
        } else if ($course->designercoursetype == DESIGNER_TYPE_FLOW) {
            $class = (!$this->page->user_is_editing()) ? 'course-type-flow' : '';
        } else {
            $class = "course-type-default";
        }

        $id = isset($course->accordion) && $course->accordion && !$this->page->user_is_editing() ? 'section-course-accordion' : '';

        return [$id, [$class] ];
    }

    /**
     * Section classes based on different courses.
     *
     * @param stdclass $course
     * @param stdclass $section
     * @param cminfo $modinfo
     * @return array list of classes.
     */
    public function course_type_sectionclasses($course, $section, $modinfo) {
        $attrs = $contentattrs = [];
        $contentclass = $actvitiyclass = '';
        $class = "";
        if ($course->designercoursetype == DESIGNER_TYPE_COLLAPSIBLE) {
            $attrs[] = 'data-toggle="collapse"';
            $contentattrs[] = 'data-parent="#section-course-accordion"';
        } else if ($course->designercoursetype == DESIGNER_TYPE_KANBAN) {
            $class = "";
        } else if ($course->designercoursetype == DESIGNER_TYPE_FLOW) {
            $modnumber = isset($modinfo->sections[$section->section]) ? $modinfo->sections[$section->section] : 0;
            $class = ($modnumber > 0 ) ? 'has-modules flow-stack' : 'flow-none';
            $contentclass = 'flow-open';
            if (count($modinfo->sections) <= 1 && $section->section == '0') {
                $class .= ' flow-head-hide';
            }
        }
        return [
            'classes' => $class,
            'content' => [
                'classes' => $contentclass,
            ],
            'activity' => [
                'classes' => $actvitiyclass,
            ]
        ];
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
        $this->modinfo = $modinfo;
        $course = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);
        // Display the time management plugin widget.
        echo $this->timemanagement_details($course);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $this->completioninfo_icon($completioninfo);
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        list($startid, $startclass) = $this->course_type_class($course);
        $startclass[] = ($course->coursedisplay && !$this->page->user_is_editing()) ? 'row' : '';

        echo $this->start_section_list($startid, $startclass);
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
            if ($course->designercoursetype == DESIGNER_TYPE_KANBAN && $section == 0) {
                echo html_writer::start_div('kanban-board-activities');
                $kanbanactivities = true;
            }
        }
        // Close the kanban board items.
        if (isset($kanbanactivities)) {
            echo html_writer::end_div();
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
        if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                echo html_writer::end_tag('div');
        }
        format_designer_editsetting_style($this->page);
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
        $this->modinfo = $modinfo;
        $course = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);
        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection)) || !$sectioninfo->uservisible) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            throw new moodle_exception('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }
        // Display the time management plugin widget.
        echo $this->timemanagement_details($course);
        // Copy activity clipboard..
        list($startid, $startclass) = $this->course_type_class($course);
        $sectioncollapse = isset($course->designercoursetype)
            && $course->designercoursetype == DESIGNER_TYPE_COLLAPSIBLE ? true : false;
        echo $this->start_section_list($startid, $startclass);

        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            $this->render_section($thissection, $course, true);
        }
        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            echo $this->change_number_sections($course, 0);
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

        echo $this->render_section($thissection, $course, true);

        // Close single-section div.
        echo html_writer::end_tag('div');

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
        format_designer_editsetting_style($this->page);
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
                $o .= $this->availability_info(get_string('hiddenfromstudents'), 'ishidden');
            } else {
                // We are here because of the setting "Hidden sections are shown in collapsed form".
                // Student can not see the section contents but can see its name.
                $o .= $this->availability_info(get_string('notavailable'), 'ishidden');
            }
        } else if (!$section->uservisible || $canviewhidden && !empty($CFG->enableavailability)) {
            if (!$section->uservisible && $section->availableinfo) {
                // Note: We only get to this function if availableinfo is non-empty,
                // so there is definitely something to print.
                $formattedinfo = \core_availability\info::format_info(
                        $section->availableinfo, $section->course);
                $o .= $this->availability_info($formattedinfo, 'isrestricted');
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
                    $o .= $this->availability_info($formattedinfo, 'isrestricted isfullinfo');
                    $o .= html_writer::start_tag('div', array('class' => 'section-restricted-action'));
                    $o .= html_writer::tag('i', '', array('class' => 'fa fa-lock'));
                    $o .= html_writer::end_tag('div');
                }
            }
        }
        return $o;
    }

    /**
     * Genreate the class for the sections to deifine the width of sections.
     * It contains bootstrap grid classes, for desktop, laptop and mobile.
     *
     * @param stdclass $section Record object.
     * @return string classes list.
     */
    protected function generate_section_widthclass($section) {
        if (!isset($section->tabletwidth)) {
            return '';
        }
        $tablet = isset($section->tabletwidth) ? $section->tabletwidth : '';
        $mobile = isset($section->mobilewidth) ? $section->mobilewidth : '';
        $desktop = isset($section->desktopwidth) ? $section->desktopwidth : '';

        $widthclasses = [0 => 12, 1 => 6, 2 => 4, 3 => 3, 4 => 2 ];
        $classes = [];
        foreach (['desktop' => 'md', 'tablet' => 'sm', 'mobile' => 'xs'] as $device => $size) {
            $class = 'col-';
            $class .= ($size) ? $size.'-' : '';
            $class .= (isset($widthclasses[$$device])) ? $widthclasses[$$device] : 12;
            $classes[] = $class;
        }
        return ' '.implode(' ', $classes);
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
        // Add accordion panel class to bootstrap 3 accordion if course has accordion method.
        $sectionstyle .= isset($course->accordion) && !$this->page->user_is_editing() ? ' panel ' : '';

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $sectionavailability = $this->section_availability($section);
        $sectionrestrict = (!$section->uservisible && $section->availableinfo) ? true : false;

        // CM LIST.
        $cmlist = [];
        $modinfo = get_fast_modinfo($course);
        $displayoptions = [];

        // Get the list of modules visible to user.
        $this->flowdelay = null;
        $section->sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        if (!empty($modinfo->sections[$section->section]) && $section->uservisible) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                $cminfo = $this->render_course_module($mod, $sectionreturn, $displayoptions, $section);
                $cmlist[$modnumber] = $cminfo;
                $cmlist[$modnumber]['modstatus'] = !empty($cminfo) ? true : false;
            }
        }
        $cmlist = array_values($cmlist);
        // END CM LIST.
        $cmcontrol = $this->courserenderer->course_section_add_cm_control($course, 0, 0);
        if ($course->coursedisplay == 1 && !$onsectionpage && $section->section > 0) {
            $gotosection = true;
        }

        // Calculate to the section progress.
        $sectiondata = \format_designer\options::is_section_completed($section, $course, $modinfo);
        list($issectioncompletion, $sectionprogress, $sectionprogresscomp) = $sectiondata;

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
        $prodata = [];

        $sectionlayoutclass = 'link-layout';
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        if ($sectiontype == 'list') {
            $sectionlayoutclass = "list-layout";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = 'card-layout';
        }
        if ($course->designercoursetype == DESIGNER_TYPE_FLOW) {
            $sectionlayoutclass = 'card-layout';
            $sectiontype = 'cards';
        }
        $templatename = 'format_designer/section_layout_' . $sectiontype;
        $prolayouts = format_designer_get_pro_layouts();
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

        $sectioncollapsestatus = '';
        if (isset($course->initialstate) && $course->initialstate == SECTION_COLLAPSE) {
            $sectioncollapsestatus = '';
        } else {
            $sectioncollapsestatus = (isset($course->initialstate) && $course->initialstate == FIRST_EXPAND)
            ? (($section->section == 0) ? 'show' : '') : 'show';
        }
        // Disable the collapsible for kanban board.
        if ( !($course->coursedisplay == 1 && !$onsectionpage)
            && ($course->designercoursetype == DESIGNER_TYPE_COLLAPSIBLE || $course->designercoursetype == DESIGNER_TYPE_FLOW) ) {
            $sectioncollapse = true;
        }

        // IN flow if general section only available then the general heading hidden. So we need to disable the collapsible.
        if ($course->designercoursetype == DESIGNER_TYPE_FLOW && count($modinfo->sections) <= 1) {
            $sectioncollapsestatus = 'show';
        }

        // Calculate section width for single section format.
        $section->widthclass = ($course->coursedisplay && !$this->page->user_is_editing() && !$onsectionpage)
            ? $this->generate_section_widthclass($section) : '';

        // Set list width for kanban board sections.
        $sectionstylerules = ($course->designercoursetype == DESIGNER_TYPE_KANBAN)
            ? (isset($course->listwidth) && $section->section != 0 ? sprintf('width: %s;', $course->listwidth) : '') : '';

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
            'sectioncollapse' => isset($sectioncollapse) && $sectioncollapse ? true : false,
            'sectionshow' => $sectioncollapsestatus,
            'sectionaccordion' => isset($course->accordion) && !$this->page->user_is_editing() ? $course->accordion : false,
            'designercoursetype' => $this->course_type_sectionclasses($course, $section, $modinfo),
            'stylerules' => $sectionstylerules,
            'flowcourse' => isset($course->designercoursetype) && $course->designercoursetype == DESIGNER_TYPE_FLOW ? true : false,
            'maskimage' => (isset($section->sectiondesignermaskimage) && $section->sectiondesignermaskimage) ? true : false,
        ];

        if (format_designer_has_pro()) {
            $prodata = \local_designer\options::render_section($section, $course, $modinfo, $templatecontext);
        }
        if ($sectioncontent) {
            $contenttemplatename = 'format_designer/section_content_' . $sectiontype;
            return $this->render_from_template($contenttemplatename, $templatecontext);
        }
        $sectionclass = 'section-type-'.$sectiontype;
        $sectionclass .= ($sectionrestrict) ? 'restricted' : '';
        $sectionclass .= $section->widthclass;
        $sectionclass .= ($templatecontext['sectionstyle']) ?? ' '.$templatecontext['sectionstyle'];
        $modnumber = isset($modinfo->sections[$section->section]) ? $modinfo->sections[$section->section] : 0;
        if ($templatecontext['flowcourse']) {
            $sectionclass .= ($modnumber > 0 ) ? '' : ' section-flow-none';
        }
        $style = isset($templatecontext['stylerules']) ? ' '.$templatecontext['stylerules'] : '';
        $sectionhead = html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => 'section main clearfix '.$sectionclass,
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
     * Displays availability info for a course section or course module
     *
     * @param string $text
     * @param string $additionalclasses
     * @return string
     */
    public function availability_info($text, $additionalclasses = '') {

        $data = ['text' => $text, 'classes' => $additionalclasses];
        $additionalclasses = array_filter(explode(' ', $additionalclasses));

        if (in_array('ishidden', $additionalclasses)) {
            $data['ishidden'] = 1;

        } else if (in_array('isstealth', $additionalclasses)) {
            $data['isstealth'] = 1;

        } else if (in_array('isrestricted', $additionalclasses)) {
            $data['isrestricted'] = 1;

            if (in_array('isfullinfo', $additionalclasses)) {
                $data['isfullinfo'] = 1;
            }
        }

        return $this->render_from_template('format_designer/availability_info', $data);
    }

    /**
     * Render the mod info.
     *
     * @param object $mod
     * @param int $sectionreturn
     * @param array $displayoptions
     * @param stdclass $section section record data.
     * @return void|string
     */
    public function render_course_module($mod, $sectionreturn, $displayoptions = [], $section=null) {
        global $DB, $USER, $CFG;

        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $movehtml = '';
        $style = '';
        if ($this->page->user_is_editing()) {
            $movehtml = course_get_cm_move($mod, $sectionreturn);
        }

        $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;

        // Add course type flow animation class.
        // TODO: check the animation settings.
        $course = course_get_format($mod->get_course())->get_course();
        if ($course->designercoursetype == DESIGNER_TYPE_FLOW && !$this->page->user_is_editing()) {
            if ((isset($course->showanimation) && $course->showanimation)) {
                $modclasses .= ' flow-animation ';
                $modstyle = sprintf('animation-delay: %ss;', $this->flowdelay);
                $duration = get_config('format_designer', 'flowanimationduration');
                $modstyle .= sprintf('animation-duration: %ss;', ($duration) ? $duration : '1');
                $this->flowdelay = $this->flowdelay + 0.5;
            }
        }
        $ispopupactivities = isset($course->popupactivities) && $course->popupactivities;
        if ($ispopupactivities) {
            $class = '\\format_popups\\local\\mod_' . $mod->modname;
            if (
                class_exists($class) &&
                has_capability('format/popups:view', context_module::instance($mod->id))
            ) {
                $modclasses .= ' popmodule ';
            }
        }
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
        if (!empty($url) || $mod->modname == 'videotime') {
            $cmtext = $this->course_section_cm_text($mod, $displayoptions);
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

        $activitylink = $mod->render_icon($this->output, 'activityicon');
        if ($mod->uservisible) {
            $modiconurl = html_writer::link($url, $activitylink, array('class' => 'mod-icon-url'));
        } else {
            $modiconurl = html_writer::start_div('mod-icon-url');
            $modiconurl .= $activitylink;
            $modiconurl .= html_writer::end_div();
        }
        $useactivityimage = false;
        $options = (format_designer_has_pro()) ? \local_designer\options::get_options($mod->id) : [];
        if (isset($options->useactivityimage)) {
             if ($mod->modname == 'videotime') {
                if ($videorecord = $DB->get_record('videotime', array('id' => $mod->instance))) {
                    if ($videorecord->label_mode == 2) {
                        $useactivityimage = ($options->useactivityimage) ? $options->useactivityimage : false;
                    }
                }
            }
            $enableactivityimage = ($options->useactivityimage) ? $options->useactivityimage : false;
        }
        $videotimeduration = '';
        $durationformatted = '';
        if ($mod->modname == 'videotime') {
            $videoinstance = $DB->get_record('videotime', array('id' => $mod->instance));
            if ($videoinstance) {
                if ($video = $DB->get_record('videotime_vimeo_video', ['link' => $videoinstance->vimeo_url])) {
                    $videotimeduration = $video->duration;
                }
            }
        }
        if ($videotimeduration) {
            if ($videotimeduration >= 3600) {
                $durationformatted = gmdate('H:i:s', $videotimeduration);
            } else {
                $durationformatted = gmdate('i:s', $videotimeduration);
            }
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
            'cmurl' => $this->get_cmurl($mod),
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
            'modvisits' => ($this->get_cmurl($mod)) ? $modvisits : false,
            'availabilityrestrict' => $availabilityrestrict,
            'modiconurl' => $modiconurl,
            'modrestricted' => $modrestricted,
            'elementstate' => $this->get_activity_elementclasses($mod),
            'modstyle' => isset($modstyle) ? $modstyle : '',
            'useactivityimage' => $useactivityimage,
            'duration_formatted' => $durationformatted,
            'enableactivityimage' => $enableactivityimage,
        ];

        if (format_designer_has_pro()) {
            require_once($CFG->dirroot. "/local/designer/lib.php");
            $prodata = \local_designer\options::render_course_module($mod, $cmlist, $section);
            $cmlist = array_merge($cmlist, $prodata);
        }
        return $cmlist;
    }

    /**
     * Renders html to display the module content on the course page (i.e. text of the labels)
     *
     * @param cm_info $mod
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm_text(cm_info &$mod, $displayoptions = array()) {
        global $DB;
        $output = '';
        $options = (format_designer_has_pro()) ? \local_designer\options::get_options($mod->id) : [];
        if ($mod->modname != 'videotime') {
            return $this->courserenderer->course_section_cm_text($mod, $displayoptions);
        }
        $content = $mod->get_formatted_content(array('overflowdiv' => true, 'noclean' => true));
        $videotime = $DB->get_record('videotime', ['id' => $mod->instance]);
        $content = $videotime->intro;

        list($linkclasses, $textclasses) = $this->course_section_cm_classes($mod);
        if ($mod->url && $mod->uservisible) {
            if ($content) {
                // If specified, display extra content after link.
                $output = html_writer::tag('div', $content, array('class' =>
                        trim('contentafterlink ' . $textclasses)));
            }
        } else {
            $groupinglabel = $mod->get_grouping_label($textclasses);
            // No link, so display only content.
            $output = html_writer::tag('div', $content . $groupinglabel,
                    array('class' => 'contentwithoutlink ' . $textclasses));
        }
        return $output;
    }

    /**
     * Generate the classes for the activity elements visibility classes.
     * It used to show or hide, or show, hide during the activity hover.
     * @param \modinfo $mod
     * @return void
     */
    public function get_activity_elementclasses($mod) {

        $option  = \format_designer\options::get_option($mod->id, 'activityelements');
        if (!empty($option)) {
            $element = json_decode($option, true);
            $classes = [
                0 => 'content-hide', 1 => 'content-show',
                2 => 'content-show-hover', 3 => 'content-hide-hover',
                4 => 'content-remove'
            ];

            $elementclasses = array_map(function($v) use ($classes) {
                return (isset($classes[$v])) ? $classes[$v] : $v;
            }, $element);
            return $elementclasses;
        }
        return [];
    }

    /**
     * Get course modulename.
     * @param object $mod
     * @param array $displayoptions
     * @return string module name.
     */
    public function get_cmname($mod, $displayoptions = []) {

        $options = (format_designer_has_pro()) ? \local_designer\options::get_options($mod->id) : [];
        if ($mod->url || ($mod->modname == 'videotime')) {
            list($linkclasses, $textclasses) = $this->course_section_cm_classes($mod);
            $groupinglabel = $mod->get_grouping_label($textclasses);
            $temp1 = new \core_course\output\course_module_name($mod, $this->page->user_is_editing(), $displayoptions);
            $modulenametemplate = $temp1->export_for_template($this->output);
            $output = '';
            $style = '';
            if (format_designer_has_pro()) {
                $textcolor = \format_designer\options::get_option($mod->id, 'textcolor');
                if ($textcolor) {
                    $style = "color: $textcolor" . ";";
                }
            }
            $onclick = htmlspecialchars_decode($mod->onclick, ENT_QUOTES);
            $url = $mod->url ?: new moodle_url('/mod/videotime/view.php', ['id' => $mod->id]);
            $instancename = $mod->get_formatted_name() ?: $mod->name;
            $activitylink = html_writer::tag('span', $instancename, array('class' => 'instancename', 'style' => $style));
            if ($mod->uservisible) {
                $output .= html_writer::link($url, $activitylink, array('class' => 'aalink' . $linkclasses, 'onclick' => $onclick));
            } else {
                // We may be displaying this just in order to show information
                // about visibility, without the actual link ($mod->availableinfo).
                $output .= html_writer::tag('div', $activitylink, array('class' => $textclasses));
            }
            $modulenametemplate['displayvalue'] = $output;
            $cmname = $this->output->render_from_template('core/inplace_editable', $modulenametemplate) . $groupinglabel;
            return $cmname;
        }
        return '';
    }

    /**
     * Get course module URL.
     *
     * @param cminfo $mod Course Module Info.
     * @return string
     */
    public function get_cmurl($mod) {
        $options = (format_designer_has_pro()) ? \local_designer\options::get_options($mod->id) : [];
        if ($mod->url) {
            return $mod->url;
        } else if ($mod->modname == 'videotime' && $options && $options->useactivityimage) {
            return new moodle_url('/mod/videotime/view.php', ['id' => $mod->id]);
        }
        return '';
    }

    /**
     * Returns the CSS classes for the activity name/content
     *
     * For items which are hidden, unavailable or stealth but should be displayed
     * to current user ($mod->availableinfo), we show those as dimmed.
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
            if ($this->is_stealth($mod)) {
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
     * Whether this module is available but hidden from course page
     *
     * "Stealth" modules are the ones that are not shown on course page but available by following url.
     * They are normally also displayed in grade reports and other reports.
     * Module will be stealth either if visibleoncoursepage=0 or it is a visible module inside the hidden
     * section.
     * @param modinfo $mod
     * @return bool
     */
    public function is_stealth($mod) {
        $modinfo = get_fast_modinfo($mod->get_course());
        $sectioninfo = $modinfo->get_section_info($mod->sectionnum);
        return !$mod->visibleoncoursepage ||
            ($mod->visible && ($section = $sectioninfo) && !$section->visible);
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

    /**
     * Render the modules call to action link html.
     *
     * @param moodle_page $page
     * @return void
     */
    public function render_call_to_action($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('format_designer/call_to_action', $data);
    }

    /**
     * Render the modules completion status html.
     *
     * @param moodle_page $page
     * @return void
     */
    public function render_cm_completion($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('format_designer/cm_completion', $data);
    }
}
