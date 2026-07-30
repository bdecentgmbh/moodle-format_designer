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
 * Tests that section format options survive a course duplication / restore.
 *
 * @package   format_designer
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/format/designer/lib.php');

/**
 * Duplication / restore of designer section format options.
 *
 * @covers \format_designer\events::course_section_created
 */
final class duplicate_course_test extends \advanced_testcase {

    /**
     * Backup a course and restore it into a brand new course, the same way
     * core_course_duplicate_course does.
     *
     * @param int $courseid Source course id.
     * @return int New course id.
     */
    protected function backup_and_restore(int $courseid): int {
        global $CFG;
        require_once($CFG->dirroot . '/course/externallib.php');

        $course = get_course($courseid);
        $result = \core_course_external::duplicate_course(
            $courseid,
            $course->fullname . ' copy',
            $course->shortname . '_copy',
            $course->category
        );
        $result = \core_external\external_api::clean_returnvalue(
            \core_course_external::duplicate_course_returns(),
            $result
        );

        return (int) $result['id'];
    }

    /**
     * Section level designer options must be identical in the duplicated course.
     */
    public function test_section_format_options_are_duplicated(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Site wide default differs from what we configure on the section.
        set_config('sectiontype', 'default', 'format_designer');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'designer', 'numsections' => 3], ['createsections' => true]);
        $format = course_get_format($course);

        $modinfo = get_fast_modinfo($course);
        $sectiontypes = [];
        foreach ([1, 2, 3] as $num) {
            $sectioninfo = $modinfo->get_section_info($num);
            $type = ($num == 1) ? 'cards' : (($num == 2) ? 'list' : 'link');
            $format->update_section_format_options(['id' => $sectioninfo->id, 'sectiontype' => $type]);
            $sectiontypes[$num] = $type;
        }

        // Sanity check the source course really holds the values.
        foreach ($sectiontypes as $num => $expected) {
            $sectioninfo = get_fast_modinfo($course->id)->get_section_info($num);
            $this->assertEquals(
                $expected,
                $DB->get_field('course_format_options', 'value', [
                    'courseid' => $course->id,
                    'format' => 'designer',
                    'sectionid' => $sectioninfo->id,
                    'name' => 'sectiontype',
                ]),
                "Source course section $num should have sectiontype $expected"
            );
        }

        $newcourseid = $this->backup_and_restore($course->id);
        $newcourse = get_course($newcourseid);
        $this->assertEquals('designer', $newcourse->format);

        $newmodinfo = get_fast_modinfo($newcourseid);
        foreach ($sectiontypes as $num => $expected) {
            $newsection = $newmodinfo->get_section_info($num);
            $actual = $DB->get_field('course_format_options', 'value', [
                'courseid' => $newcourseid,
                'format' => 'designer',
                'sectionid' => $newsection->id,
                'name' => 'sectiontype',
            ]);
            $this->assertEquals($expected, $actual, "Duplicated course section $num lost its sectiontype");
        }
    }

    /**
     * Course level designer options must be identical in the duplicated course.
     */
    public function test_course_format_options_are_duplicated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'designer', 'numsections' => 2], ['createsections' => true]);

        $expected = [
            'accordion' => 1,
            'initialstate' => 1,
            'listwidth' => '600px',
            'courseindex' => 1,
        ];
        course_get_format($course)->update_course_format_options(['id' => $course->id] + $expected);

        $newcourseid = $this->backup_and_restore($course->id);

        $newoptions = course_get_format($newcourseid)->get_format_options();
        foreach ($expected as $name => $value) {
            $this->assertEquals($value, $newoptions[$name], "Duplicated course lost the course option $name");
        }
    }

    /**
     * Activity level designer options must keep pointing at the new course.
     */
    public function test_module_options_keep_the_courseid(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'designer', 'numsections' => 2], ['createsections' => true]);
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        \format_designer\options::insert_option($page->cmid, $course->id, 'heroactivity', 1);

        $newcourseid = $this->backup_and_restore($course->id);

        $newcms = get_fast_modinfo($newcourseid)->get_instances_of('page');
        $this->assertCount(1, $newcms);
        $newcm = reset($newcms);

        $record = $DB->get_record('format_designer_options', [
            'cmid' => $newcm->id,
            'name' => 'heroactivity',
        ]);
        $this->assertNotEmpty($record, 'Activity designer option was not restored at all');
        $this->assertEquals(1, $record->value);
        $this->assertEquals($newcourseid, $record->courseid, 'Restored activity option lost its courseid');
    }
}
