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
 * Tests for the Manage features registry and its toggles.
 *
 * @package   format_designer
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/designer/lib.php');

/**
 * Tests for \format_designer\features and \format_designer\helper::feature_enabled().
 *
 * @covers \format_designer\features
 * @covers \format_designer\helper::feature_enabled
 */
final class features_test extends \advanced_testcase {
    /**
     * Every registry entry must be complete, so the admin page and the option
     * stripping never hit an undefined index.
     */
    public function test_registry_entries_are_well_formed(): void {
        $this->resetAfterTest();

        $groups = [features::GROUP_COURSE, features::GROUP_SECTION, features::GROUP_ACTIVITY, features::GROUP_HEADER];
        $features = features::get_features();

        $this->assertNotEmpty($features);
        foreach ($features as $key => $def) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key, "Feature key '$key' is not a valid key");
            foreach (['name', 'description', 'group', 'pro', 'courseoptions', 'activityoptions', 'configkeys'] as $field) {
                $this->assertArrayHasKey($field, $def, "Feature '$key' is missing '$field'");
            }
            $this->assertContains($def['group'], $groups, "Feature '$key' has an unknown group");
            $this->assertIsBool($def['pro'], "Feature '$key' has a non-boolean 'pro'");
            $this->assertIsArray($def['courseoptions']);
            $this->assertIsArray($def['activityoptions']);
            $this->assertIsArray($def['configkeys']);
            $this->assertTrue(
                get_string_manager()->string_exists($def['name'], 'format_designer'),
                "Feature '$key' has no lang string '{$def['name']}'"
            );
            $this->assertTrue(
                get_string_manager()->string_exists($def['description'], 'format_designer'),
                "Feature '$key' has no lang string '{$def['description']}'"
            );
        }
    }

    /**
     * An unset toggle must mean "enabled", so upgrading sites keep every feature
     * they had before the Manage features page existed.
     */
    public function test_features_are_enabled_by_default(): void {
        $this->resetAfterTest();

        foreach (features::get_features() as $key => $def) {
            if (!empty($def['pro']) || !empty($def['depends'])) {
                // Pro and dependency gated features are covered separately.
                continue;
            }
            unset_config('feature_' . $key, 'format_designer');
        }

        // A fresh request would see no toggles at all: everything must still be on.
        $this->assertTrue(helper::feature_enabled('accordion'));
        $this->assertTrue(helper::feature_enabled('courseindex'));
    }

    /**
     * The toggle must take effect immediately, including within the request that
     * changed it - the settings page reads it back straight after saving.
     */
    public function test_toggle_takes_effect_immediately(): void {
        $this->resetAfterTest();

        unset_config('feature_accordion', 'format_designer');
        $this->assertTrue(helper::feature_enabled('accordion'));

        set_config('feature_accordion', 0, 'format_designer');
        $this->assertFalse(helper::feature_enabled('accordion'), 'Toggling off must be visible in the same request');

        set_config('feature_accordion', 1, 'format_designer');
        $this->assertTrue(helper::feature_enabled('accordion'), 'Toggling back on must be visible in the same request');
    }

    /**
     * A disabled feature must drop exactly its own option keys.
     */
    public function test_disabled_option_keys_lists_only_disabled_features(): void {
        $this->resetAfterTest();

        set_config('feature_courseindex', 0, 'format_designer');
        $removed = features::disabled_option_keys('courseoptions');
        $this->assertContains('courseindex', $removed);

        // The accordion feature is still on, so its keys stay.
        $this->assertNotContains('accordion', $removed);

        // The initialstate key belongs to both coursetype and accordion, both still enabled.
        $this->assertNotContains('initialstate', $removed);
    }

    /**
     * A key claimed by both a disabled and an enabled feature must be kept, otherwise
     * turning off one feature would silently strip a field another feature still needs.
     */
    public function test_shared_option_key_survives_partial_disable(): void {
        $this->resetAfterTest();

        set_config('feature_coursetype', 0, 'format_designer');
        set_config('feature_accordion', 1, 'format_designer');

        $removed = features::disabled_option_keys('courseoptions');
        $this->assertContains('coursetype', $removed, 'A key owned only by the disabled feature must go');
        $this->assertNotContains('initialstate', $removed, 'A key still claimed by an enabled feature must stay');
    }

    /**
     * An unknown key is treated as enabled rather than fatal, so a pro plugin that
     * is older than the free one cannot break the course settings form.
     */
    public function test_unknown_feature_key_is_enabled(): void {
        $this->resetAfterTest();
        $this->assertTrue(helper::feature_enabled('this_feature_does_not_exist'));
    }

    /**
     * With nothing disabled, no option key may be stripped.
     */
    public function test_nothing_is_stripped_when_all_features_are_on(): void {
        $this->resetAfterTest();

        // The initialstate key is claimed by both coursetype and accordion.
        $features = features::get_features();
        $this->assertContains('initialstate', $features['coursetype']['courseoptions']);
        $this->assertContains('initialstate', $features['accordion']['courseoptions']);

        foreach (array_keys($features) as $key) {
            set_config('feature_' . $key, 1, 'format_designer');
        }
        $this->assertSame([], features::disabled_option_keys('courseoptions'));
        $this->assertSame([], features::disabled_option_keys('activityoptions'));
        $this->assertSame([], features::disabled_option_keys('configkeys'));
    }

    /**
     * The plain layout must be registered and must be the shipped global default,
     * because a Designer course with no features enabled has to look like a
     * standard core course.
     */
    public function test_plain_layout_is_registered(): void {
        $this->resetAfterTest();

        $layouts = helper::get_all_layouts();
        $this->assertArrayHasKey('plain', $layouts);

        // The historical 'default' key is the "Text links" layout and must survive,
        // otherwise stored per-section values would stop resolving.
        $this->assertArrayHasKey('default', $layouts);
        $this->assertNotSame($layouts['plain'], $layouts['default']);

        // Every layout must be renderable: either the free plugin ships the template, or the
        // layout is provided by a layouts_* subplugin from Designer Pro.
        global $CFG;
        $prolayouts = helper::get_pro_layouts();
        foreach (array_keys($layouts) as $key) {
            if (in_array($key, $prolayouts, true)) {
                continue;
            }
            $template = $CFG->dirroot . '/course/format/designer/templates/layout/section_layout_' . $key . '.mustache';
            $this->assertFileExists($template, "Free layout '$key' has no section template");
        }
    }

    /**
     * The plain layout needs both halves of its template pair, otherwise the section
     * renders but its activity items fall back to another layout's markup.
     */
    public function test_plain_layout_ships_both_templates(): void {
        global $CFG;
        $this->resetAfterTest();

        $base = $CFG->dirroot . '/course/format/designer/templates';
        $this->assertFileExists($base . '/layout/section_layout_plain.mustache');
        $this->assertFileExists($base . '/cm/module_layout_plain.mustache');
    }

    /**
     * Disabling a feature must not delete anything that is already stored: an
     * administrator has to be able to turn a feature back on and find the courses
     * exactly as they were. This is also what keeps backup and restore lossless,
     * because core backs up every course_format_options row regardless of whether
     * the owning feature is currently switched on.
     */
    public function test_disabling_a_feature_keeps_stored_values(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'designer', 'numsections' => 2],
            ['createsections' => true]
        );
        $format = course_get_format($course);
        $format->update_course_format_options(['id' => $course->id, 'accordion' => 1, 'initialstate' => 1]);

        $stored = $DB->get_field('course_format_options', 'value', [
            'courseid' => $course->id, 'format' => 'designer', 'sectionid' => 0, 'name' => 'accordion',
        ]);
        $this->assertEquals(1, $stored);

        // Turn the owning feature off.
        set_config('feature_accordion', 0, 'format_designer');

        // The stored row is untouched.
        $after = $DB->get_field('course_format_options', 'value', [
            'courseid' => $course->id, 'format' => 'designer', 'sectionid' => 0, 'name' => 'accordion',
        ]);
        $this->assertEquals($stored, $after, 'Disabling a feature must not delete stored course options');

        // Re-enabling brings the value back to the form and to get_format_options().
        set_config('feature_accordion', 1, 'format_designer');
        $options = course_get_format($course->id)->get_format_options();
        $this->assertEquals(1, $options['accordion']);
    }

    /**
     * Body classes for a page in the given course, optionally an activity page.
     *
     * @param int $courseid
     * @param int|null $cmid
     * @return string
     */
    protected function body_classes_for(int $courseid, ?int $cmid = null): string {
        $page = new \moodle_page();
        $page->set_course(get_course($courseid));
        if ($cmid !== null) {
            $page->set_cm(get_fast_modinfo($courseid)->get_cm($cmid));
        }
        $property = new \ReflectionProperty(\moodle_page::class, '_bodyclasses');
        $property->setAccessible(true);
        return implode(' ', array_keys($property->getValue($page)));
    }

    /**
     * The course section layout feature decides whether Designer takes the full page width,
     * and it signals that with the format-designer-fullwidth body class. Designer Pro's hero
     * header stylesheet keys its full-bleed layout off that class, so it has to be present on
     * course, section and activity pages alike - and absent when the feature is off, otherwise
     * a boxed course ends up with its content jammed against the left edge.
     */
    public function test_fullwidth_body_class_follows_the_feature(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'designer', 'numsections' => 2],
            ['createsections' => true]
        );
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);

        set_config('feature_coursesectionlayout', 1, 'format_designer');
        $this->assertStringContainsString('format-designer-fullwidth', $this->body_classes_for($course->id));
        $this->assertStringContainsString(
            'format-designer-fullwidth',
            $this->body_classes_for($course->id, $module->cmid),
            'Activity pages need the class too, or the hero header loses its full-bleed layout'
        );

        set_config('feature_coursesectionlayout', 0, 'format_designer');
        $this->assertStringNotContainsString('format-designer-fullwidth', $this->body_classes_for($course->id));
        $this->assertStringNotContainsString(
            'format-designer-fullwidth',
            $this->body_classes_for($course->id, $module->cmid),
            'With the feature off the theme keeps the boxed width on activity pages as well'
        );
    }

    /**
     * A course copied while a feature is switched off must still carry that feature's
     * settings, so switching the feature back on after the copy finds them intact.
     * Feature gating only hides edit-form fields; it must never reach the backup.
     *
     * Scope note: this covers course level options. Per-section options are restored by
     * core's own section step and are covered by duplicate_course_test, which ships with
     * the DES-950 fix. Without that fix a copied course loses its per-section layout
     * regardless of any feature toggle, so asserting it here would only re-test that bug.
     */
    public function test_backup_restore_keeps_options_of_a_disabled_feature(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/course/externallib.php');

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'designer', 'numsections' => 2],
            ['createsections' => true]
        );
        $format = course_get_format($course);
        $format->update_course_format_options(['id' => $course->id, 'accordion' => 1, 'courseindex' => 2]);

        // Switch the owning features off, then copy the course.
        set_config('feature_accordion', 0, 'format_designer');
        set_config('feature_courseindex', 0, 'format_designer');

        $result = \core_course_external::duplicate_course(
            $course->id,
            $course->fullname . ' copy',
            $course->shortname . '_copy',
            $course->category
        );
        $newcourseid = (int) $result['id'];

        // Switch them back on: the copy must look exactly like the original.
        set_config('feature_accordion', 1, 'format_designer');
        set_config('feature_courseindex', 1, 'format_designer');

        $newoptions = course_get_format($newcourseid)->get_format_options();
        $this->assertEquals(1, $newoptions['accordion'], 'A disabled feature must still be backed up');
        $this->assertEquals(2, $newoptions['courseindex'], 'A disabled feature must still be backed up');
    }
}
