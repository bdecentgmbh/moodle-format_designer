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
 * Contains the designer cmlist course format output class.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output\courseformat\content\section;
use core_courseformat\output\local\content\section\cmlist as cmlist_base;
use format_designer\output\cm_completion as cm_completion;
use format_designer\output\call_to_action as call_to_action;
use stdClass;
use moodle_url;
use section_info;
use core_courseformat\base as course_format;
use html_writer;

/**
 * Base class to render a section activity list.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmlist extends cmlist_base {

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @param array $displayoptions optional extra display options
     */
    public function __construct(course_format $format, section_info $section, array $displayoptions = []) {
        $this->format = $format;
        $this->section = $section;
        $this->displayoptions = $displayoptions;
        // Get the necessary classes.
        $this->itemclass = $format->get_output_classname('content\\section\\cmitem');
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        return $this->render_section_content($output);
    }

    /**
     * Get the render section content.
     *
     * @param object $output typically, the renderer that's calling this function
     * @param bool $htmlparse return type cm innertemplate.
     * @return string|object
     */
    public function render_section_content($output, $htmlparse = false) {
        global $USER, $OUTPUT, $DB;

        $format = $this->format;
        $section = $this->section;
        $this->course = $format->get_course();
        $modinfo = $format->get_modinfo();
        $user = $USER;

        $data = new stdClass();
        $cms = [];

        // By default, non-ajax controls are disabled but in some places like the frontpage
        // it is necessary to display them. This is a temporal solution while JS is still
        // optional for course editing.
        $this->showmovehere = ismoving($this->course->id);

        if ($this->showmovehere) {
            $data->hascms = true;
            $data->showmovehere = true;
            $data->strmovefull = strip_tags(get_string("movefull", "", "'$user->activitycopyname'"));
            $data->movetosectionurl = new moodle_url('/course/mod.php', ['movetosection' => $section->id, 'sesskey' => sesskey()]);
            $data->movingstr = strip_tags(get_string('activityclipboard', '', $user->activitycopyname));
            $data->cancelcopyurl = new moodle_url('/course/mod.php', ['cancelcopy' => 'true', 'sesskey' => sesskey()]);
        }

        if (empty($modinfo->sections[$section->section])) {
            return $data;
        }

        if (!empty($modinfo->sections[$section->section]) && $section->uservisible) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                $cms[] = $this->render_course_module($output, $mod);
            }
        }
        if (!empty($cms)) {
            $data->hascms = true;
        }
        $sectionlayoutclass = 'link-layout';
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        if ($sectiontype == 'list') {
            $sectionlayoutclass = "list-layout";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = 'card-layout';
        }
        $data->sectionlayoutclass = $sectionlayoutclass;
        $data->cms = $cms;
        $templatename = 'format_designer/layout/' . 'section_layout_'. $sectiontype;
        $cmscontent = $OUTPUT->render_from_template($templatename, $data);
        $cmlistcontent = new stdClass;
        if ($htmlparse) {
            return $cmscontent;
        }
        $cmlistcontent->cmscontent = $cmscontent;
        return $cmlistcontent;
    }

    /**
     * Get the render course module content.
     *
     * @param object $output typically, the renderer that's calling this function
     * @param object $mod cm.
     * @return string|object
     */
    public function render_course_module($output, $mod) {
        global $DB, $USER, $OUTPUT;
        $format = $this->format;
        $section = $this->section;
        // If the old non-ajax move is necessary, we do not print the selected cm.
        if ($this->showmovehere && $USER->activitycopy == $mod->id) {
            return [];
        }

        if (!$mod->is_visible_on_course_page()) {
            return [];
        }

        // Load cm.
        $cmclass = $format->get_output_classname('content\\cm');
        $cmobj = new $cmclass($format, $section, $mod, $this->displayoptions);
        $this->displayoptions['textclasses'] = $cmobj->get_text_classes();
        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $movehtml = '';
        // Move and select options.
        if ($format->supports_components()) {
            $movehtml = $output->pix_icon('i/dragdrop', '', 'moodle', ['class' => 'editing_move dragicon']);
        } else {
            // Add the legacy YUI move link.
            $movehtml = course_get_cm_move($mod, $format->get_section_number());
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
            $beforecontent = $mod->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
        }
        $cmcompletion = new cm_completion($mod);
        $cmcompletionhtml = '';
        if (empty($this->displayoptions['hidecompletion'])) {
            if ($cmcompletion->is_visible()) {
                $cmcompletionhtml = $OUTPUT->render_from_template('format_designer/cm_completion',
                    $cmcompletion->export_for_template($output));
            }
        }

        $modicons = '';
        if ($format->show_editor()) {
            $controlmenuclass = $format->get_output_classname('content\\cm\\controlmenu');
            $editactions = new $controlmenuclass(
                $format,
                $this->section,
                $mod,
                $this->displayoptions
            );
            $modicons .= ' '. $OUTPUT->render_from_template('core_courseformat/local/content/cm/controlmenu',
                $editactions->export_for_template($output));
            $modicons .= $mod->afterediticons;
        }
        // Mod availability.
        $availabilityclass = $format->get_output_classname('content\\cm\\availability');
        $availabilitycontent = new $availabilityclass(
            $format,
            $this->section,
            $mod,
            $this->displayoptions
        );
        $availability = $OUTPUT->render_from_template('core_courseformat/local/content/cm/availability',
        $availabilitycontent->export_for_template($output));
        // If there is content AND a link, then display the content here.
        // (AFTER any icons). Otherwise it was displayed before.
        $cmtext = '';
        if (!empty($url)) {
            $cmtext = $mod->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
            $cmtextcontent = format_string($cmtext);
            $modcontent = '';
            if (!empty($cmtextcontent)) {
                if (strlen($cmtextcontent) >= 160) {
                    $modcontenthtml = '';
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'fullcontent-summary hide'));
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
        $callaction = new call_to_action($mod);
        $calltoactionhtml = $OUTPUT->render_from_template('format_designer/call_to_action',
                    $callaction->export_for_template($output));
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

        $cmnameclass = $format->get_output_classname('content\\cm\\cmname');
        $cmname = new $cmnameclass(
            $format,
            $this->section,
            $mod,
            $format->show_editor(),
            $this->displayoptions
        );
        $cmname = $cmname->export_for_template($output);
        $groupinglabel = $mod->get_grouping_label($this->displayoptions['textclasses']);
        $completionenabled = $this->course->enablecompletion == COMPLETION_ENABLED;
        $showactivityconditions = $completionenabled && $this->course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS;
        $showactivitydates = !empty($this->course->showactivitydates);
        // This will apply styles to the course homepage when the activity information output component is displayed.
        $hasinfo = $showactivityconditions || $showactivitydates;
        $cmlist = [];
        // Mod inplace name editable.
        $cmitem = [
            'id' => $mod->id,
            'module' => $mod->modname,
            'extraclasses' => $mod->extraclasses,
            'hasinfo' => $hasinfo,
            'modtype' => $mod->get_module_type_name(),
            'modclasses' => $modclasses,
            'indentclasses' => $indentclasses,
            'colorclass' => $cmcompletion->get_color_class(),
            'movehtml' => $movehtml,
            'cmname' => $cmname,
            'cmcompletionhtml' => $cmcompletionhtml,
            'calltoactionhtml' => $calltoactionhtml,
            'afterlink' => $mod->afterlink,
            'altcontent' => $mod->get_formatted_content(['overflowdiv' => true, 'noclean' => true]),
            'beforecontent' => $beforecontent,
            'cmtext' => $cmtext,
            'modicons' => $modicons,
            'availability' => $availability,
            'isrestricted' => !empty($mod->availableinfo),
            'modcontent' => isset($modcontent) ? $modcontent : '',
            'modvisits' => $modvisits,
            'url' => (isset($mod->url)) ? $mod->url->out(false) : '#',
            'groupinglabel' => $groupinglabel,
            'textclasses' => $this->displayoptions['textclasses'],
            'availabilityrestrict' => $availabilityrestrict
        ];

        if (!empty($mod->indent)) {
            $cmitem['indent'] = $mod->indent;
            if ($mod->indent > 15) {
                $cmitem['hugeindent'] = true;
            }
        }
        $returnsection = $format->get_section_number();
        if (!empty($cmitem['cmname'])) {
            $cmitem['hasname'] = true;
        }
        if (!empty($cmitem['url'])) {
            $cmitem['hasurl'] = true;
        }
        $cmlist['cmitem'] = $cmitem;
        $cmlist['moveurl'] = new moodle_url('/course/mod.php', array('moveto' => $mod->id, 'sesskey' => sesskey()));
        return $cmlist;
    }
}
