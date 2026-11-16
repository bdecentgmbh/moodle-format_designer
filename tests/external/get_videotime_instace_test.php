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

namespace format_designer\external;

/**
 * Tests for the format_designer_get_videotime_instace web service.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\external\external::get_videotime_instace
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class get_videotime_instace_test extends \advanced_testcase {
    /** @var \stdClass Designer course with the Video Time activity. */
    private $course;

    /** @var \stdClass Video Time activity. */
    private $videotime;

    /**
     * Create a Designer course with a Video Time activity.
     */
    public function setUp(): void {
        parent::setUp();
        if (!\core_component::get_plugin_directory('mod', 'videotime')) {
            $this->markTestSkipped('mod_videotime is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $this->videotime = $this->getDataGenerator()->create_module('videotime', ['course' => $this->course->id]);
    }

    /**
     * An enrolled student gets the settings of the activity.
     */
    public function test_enrolled_student_gets_instance(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $data = json_decode(external::get_videotime_instace($this->videotime->cmid));

        $this->assertEquals($this->videotime->cmid, $data->cmid);
        $this->assertEquals($this->videotime->id, json_decode($data->instance)->id);
    }

    /**
     * A user who is not enrolled in the course gets nothing.
     */
    public function test_user_not_enrolled_is_refused(): void {
        // Enrolled in another course only.
        $othercourse = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $user = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');
        $this->setUser($user);

        $this->expectException(\require_login_exception::class);
        external::get_videotime_instace($this->videotime->cmid);
    }

    /**
     * An enrolled student gets nothing for a hidden activity.
     */
    public function test_hidden_activity_is_refused(): void {
        set_coursemodule_visible($this->videotime->cmid, 0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $this->expectException(\require_login_exception::class);
        external::get_videotime_instace($this->videotime->cmid);
    }

    /**
     * A student without the view capability on the activity gets nothing.
     */
    public function test_no_view_capability_is_refused(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $modcontext = \context_module::instance($this->videotime->cmid);
        assign_capability('mod/videotime:view', CAP_PROHIBIT, $studentrole, $modcontext->id);
        $this->setUser($student);

        // Without the view capability, core already treats the activity as not available to the user.
        $this->expectException(\require_login_exception::class);
        external::get_videotime_instace($this->videotime->cmid);
    }
}
