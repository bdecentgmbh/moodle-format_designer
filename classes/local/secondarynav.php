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

namespace format_designer\local;

use core\navigation\views\secondary;
use moodle_page;
use moodle_url;
use navigation_node;

/**
 * Designer's items in the secondary navigation of a course and its activities.
 *
 * The hero activities ("Show as tab"), the course link on activity pages ("Secondary menu to course") and the tabs of
 * Designer Pro are added to the secondary navigation as navigation nodes, in the order Designer sets. Moodle renders
 * them in the menu of its version: as server-side markup up to 5.2, through the React menu from 5.3 (MDL-87830).
 * Before, a script moved them in the server-side markup, which 5.3 replaces.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secondarynav {
    /** @var string Key prefix of the hero activity nodes, followed by the course module id. */
    const HERO_PREFIX = 'designerhero';

    /**
     * Add Designer's items to the secondary navigation of the page, if it is a page of a Designer course or activity.
     *
     * @param moodle_page $page
     * @return void
     */
    public static function extend(moodle_page $page): void {
        $course = $page->course;
        if (empty($course->id) || $course->id == SITEID || ($course->format ?? '') !== 'designer') {
            return;
        }
        $contextlevel = $page->context->contextlevel;
        if (!in_array($contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE]) || !$page->has_secondary_navigation()) {
            return;
        }
        $nav = $page->secondarynav;
        if (!$nav instanceof secondary) {
            return;
        }
        $format = course_get_format($course);
        $course = $format->get_course();
        $cm = ($contextlevel == CONTEXT_MODULE) ? $page->cm : null;

        $heroes = self::hero_activities($course, $cm);
        $coursehome = $cm && !empty($course->secondarymenutocourse);

        // An activity without a secondary navigation of its own gets one, with the activity first, as in core.
        if ($cm && !$nav->has_children() && ($heroes || $coursehome)) {
            $node = navigation_node::create(
                get_string('modulename', $cm->modname),
                new moodle_url("/mod/{$cm->modname}/view.php", ['id' => $cm->id]),
                navigation_node::TYPE_SETTING,
                null,
                'modulepage'
            );
            $nav->add_node($node);
            $node->make_active();
        }
        if (!$nav->has_children()) {
            return;
        }

        self::add_hero_activities($nav, $format, $heroes, $cm);
        if (\format_designer\helper::has_pro()) {
            self::move_prerequisites($nav, $course);
        }
        if ($coursehome && !$nav->get('coursehome')) {
            $node = navigation_node::create(
                get_string('strsecondarymenucourse', 'format_designer'),
                new moodle_url('/course/view.php', ['id' => $course->id]),
                navigation_node::TYPE_SETTING,
                null,
                'designercoursehome'
            );
            self::insert($nav, $node, 0);
            if (
                \format_designer\helper::has_pro() && !empty($course->prerequisitesbackmain) &&
                ($maincourse = \local_designer\helper::is_prerequisites_maincourse($course))
            ) {
                $node = navigation_node::create(
                    get_string('backtomaincourse', 'format_designer'),
                    new moodle_url('/course/view.php', ['id' => $maincourse->id]),
                    navigation_node::TYPE_SETTING,
                    null,
                    'backtomaincourse'
                );
                self::insert($nav, $node, 0);
            }
        }
        // Designer Pro's link back to the main course goes first.
        if ($node = $nav->get('backtomaincourse')) {
            $node->remove();
            self::insert($nav, $node, 0);
        }
    }

    /**
     * The hero activities of the course that the page shows, in their order.
     *
     * @param \stdClass $course Course with the format options.
     * @param \cm_info|null $cm Activity of an activity page, null on course pages.
     * @return array Objects with the course module (cm) and its position (pos), in the order they are added.
     */
    public static function hero_activities(\stdClass $course, ?\cm_info $cm = null): array {
        global $DB;

        $sql = "SELECT fd.*
                  FROM {format_designer_options} fd
                  JOIN {course_modules} cm ON fd.cmid = cm.id
                 WHERE fd.courseid = :courseid AND cm.deletioninprogress = 0
                       AND (fd.name = 'heroactivity' OR fd.name = 'heroactivitypos')
              ORDER BY fd.cmid DESC";
        $options = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $course->id]) as $record) {
            $options[$record->cmid][$record->name] = $record->value;
            $options[$record->cmid]['cmid'] = $record->cmid;
        }
        // The activities of section 0, when the course makes them hero activities.
        $options = \format_designer\helper::section_zero_tomake_hero($options, $course);

        $modinfo = get_fast_modinfo($course);
        $negative = [];
        $positive = [];
        foreach ($options as $option) {
            $hero = $option['heroactivity'] ?? DESIGNER_HERO_ACTIVITY_DISABLED;
            // Activity pages show the activities shown everywhere, course pages all hero activities.
            if (!$hero || ($cm && $hero != DESIGNER_HERO_ACTIVITY_EVERYWHERE)) {
                continue;
            }
            try {
                $herocm = $modinfo->get_cm($option['cmid']);
            } catch (\moodle_exception $e) {
                continue;
            }
            $pos = (int) ($option['heroactivitypos'] ?? 0);
            $item = (object) ['cm' => $herocm, 'pos' => $pos];
            if ($pos < 0) {
                $negative[] = $item;
            } else {
                $positive[] = $item;
            }
        }
        usort($negative, fn($a, $b) => $a->pos <=> $b->pos);
        usort($positive, fn($a, $b) => $a->pos <=> $b->pos);
        return array_merge($negative, $positive);
    }

    /**
     * Add the hero activities at their positions.
     *
     * A negative position puts the activity before the first tab, position n before the n-th tab after the first one
     * (0: before the first tab), or at the end. Hero activities at the same position keep their order.
     *
     * @param secondary $nav Secondary navigation.
     * @param \format_designer $format Course format.
     * @param array $heroes Hero activities, from hero_activities().
     * @param \cm_info|null $cm Activity of an activity page, null on course pages.
     * @return void
     */
    protected static function add_hero_activities(
        secondary $nav,
        \format_designer $format,
        array $heroes,
        ?\cm_info $cm
    ): void {
        $tabs = $nav->children->get_key_list();
        $avoidduplicate = get_config('format_designer', 'avoidduplicate_heromodentry') == 1;
        foreach ($heroes as $hero) {
            $herocm = $hero->cm;
            $node = navigation_node::create(
                $format->get_cm_secondary_title($herocm),
                new moodle_url("/mod/{$herocm->modname}/view.php", ['id' => $herocm->id]),
                navigation_node::TYPE_SETTING,
                null,
                self::HERO_PREFIX . $herocm->id
            );
            $node->add_class('designer-hero-activity');
            $node->set_force_into_more_menu(false);
            $slot = max(0, $hero->pos);
            $nav->add_node($node, $tabs[$slot] ?? null);
            // On the page of the hero activity itself, its tab replaces the activity tab of core.
            if ($cm && $herocm->id == $cm->id && $avoidduplicate && ($modulepage = $nav->get('modulepage'))) {
                $modulepage->remove();
                $node->make_active();
            }
        }
    }

    /**
     * Put Designer Pro's prerequisites tab at its position, and open it in a new tab if the course says so.
     *
     * @param secondary $nav Secondary navigation.
     * @param \stdClass $course Course with the format options.
     * @return void
     */
    protected static function move_prerequisites(secondary $nav, \stdClass $course): void {
        $node = $nav->get('prerequisites');
        if (!$node) {
            return;
        }
        $node->remove();
        if (!empty($course->prerequisitesnewtab) && $node->action instanceof moodle_url) {
            $node->action = new \action_link($node->action, $node->text, null, ['target' => '_blank']);
        }
        $node->set_force_into_more_menu(false);
        self::insert($nav, $node, max(0, (int) ($course->courseprerequisitepos ?? 0)));
    }

    /**
     * Insert a node before the tab at a position, or at the end.
     *
     * @param secondary $nav Secondary navigation.
     * @param navigation_node $node Node to insert.
     * @param int $position Position of the tab to insert it before.
     * @return void
     */
    protected static function insert(secondary $nav, navigation_node $node, int $position): void {
        $node->set_force_into_more_menu(false);
        $tabs = $nav->children->get_key_list();
        $nav->add_node($node, $tabs[$position] ?? null);
    }
}
