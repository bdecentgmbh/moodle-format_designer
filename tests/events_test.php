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

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Unit tests for the Designer event observers.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\events
 */
final class events_test extends \advanced_testcase {
    /**
     * Updating a course in another format must not leave the old course record in the format cache.
     *
     * @covers ::course_updated
     */
    public function test_course_updated_other_format_keeps_updated_course(): void {
        $this->assert_course_updated_keeps_updated_course('topics');
    }

    /**
     * Updating a Designer course must not leave the old course record in the format cache.
     *
     * @covers ::course_updated
     */
    public function test_course_updated_designer_keeps_updated_course(): void {
        $this->assert_course_updated_keeps_updated_course('designer');
    }

    /**
     * Update a course while $COURSE holds the record from before the update, then check the format's course record.
     *
     * Like core_course_external::update_courses(), which calls require_login() for each course it updates.
     *
     * @param string $format Course format of the updated course.
     */
    protected function assert_course_updated_keeps_updated_course(string $format): void {
        global $COURSE, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => $format, 'fullname' => 'Old course name']);

        $origcourse = $COURSE;
        $COURSE = $DB->get_record('course', ['id' => $course->id]);
        try {
            update_course((object) ['id' => $course->id, 'fullname' => 'New course name']);
        } finally {
            $COURSE = $origcourse;
        }

        $this->assertEquals('New course name', course_get_format($course->id)->get_course()->fullname);
    }
}
